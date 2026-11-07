<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_members(): void
    {
        $this->get(route('members.index'))->assertRedirect(route('login'));
        $this->get(route('members.create'))->assertRedirect(route('login'));
        $this->post(route('members.store'))->assertRedirect(route('login'));
    }

    public function test_members_are_listed_alphabetically_and_paginated(): void
    {
        Member::factory()->create(['name' => 'Zeca']);
        Member::factory()->create(['name' => 'Ana', 'birth_date' => '1990-05-20']);
        Member::factory(14)->create(['name' => 'Bruno']);

        $this->actingAs(User::factory()->create())
            ->get(route('members.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('members/Index')
                ->has('members.data', 15)
                ->where('members.total', 16)
                ->where('members.data.0.name', 'Ana')
                ->where('members.data.0.birth_date', '1990-05-20')
            );
    }

    public function test_create_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('members.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('members/Create'));
    }

    public function test_member_can_be_created(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('members.store'), [
                'name' => 'Maria Silva',
                'email' => '  Maria@Example.com ',
                'birth_date' => '1995-03-10',
            ])
            ->assertRedirect(route('members.index'))
            ->assertInertiaFlash('success', 'Aluno cadastrado com sucesso.');

        $this->assertDatabaseHas('members', [
            'name' => 'Maria Silva',
            'email' => 'maria@example.com',
            'birth_date' => '1995-03-10',
        ]);
    }

    public function test_required_fields_are_validated(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('members.store'), [])
            ->assertSessionHasErrors([
                'name' => 'O campo nome é obrigatório.',
                'email' => 'O campo e-mail é obrigatório.',
                'birth_date' => 'O campo data de nascimento é obrigatório.',
            ]);

        $this->assertDatabaseCount('members', 0);
    }

    public function test_email_must_be_unique_regardless_of_case(): void
    {
        Member::factory()->create(['email' => 'maria@example.com']);

        $this->actingAs(User::factory()->create())
            ->post(route('members.store'), [
                'name' => 'Maria Silva',
                'email' => 'MARIA@example.com',
                'birth_date' => '1995-03-10',
            ])
            ->assertSessionHasErrors(['email' => 'Este e-mail já está em uso.']);

        $this->assertDatabaseCount('members', 1);
    }

    public function test_birth_date_must_be_in_the_past(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('members.store'), [
                'name' => 'Maria Silva',
                'email' => 'maria@example.com',
                'birth_date' => now()->addDay()->toDateString(),
            ])
            ->assertSessionHasErrors(['birth_date' => 'O campo data de nascimento deve ser uma data anterior a hoje.']);
    }
}
