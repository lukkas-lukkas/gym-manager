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

        $member = Member::factory()->create();

        $this->get(route('members.show', $member))->assertRedirect(route('login'));
        $this->get(route('members.edit', $member))->assertRedirect(route('login'));
        $this->put(route('members.update', $member))->assertRedirect(route('login'));
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

    public function test_details_page_shows_the_member_data(): void
    {
        $member = Member::factory()->create([
            'name' => 'Maria Silva',
            'email' => 'maria@example.com',
            'birth_date' => '1995-03-10',
            'deactivated_at' => '2026-10-01 12:00:00',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('members.show', $member))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('members/Show')
                ->where('member.id', $member->id)
                ->where('member.name', 'Maria Silva')
                ->where('member.email', 'maria@example.com')
                ->where('member.birth_date', '1995-03-10')
                ->where('member.deactivated_at', $member->deactivated_at->toIso8601String())
            );
    }

    public function test_details_page_of_a_missing_member_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('members.show', 999))
            ->assertNotFound();
    }

    public function test_edit_page_shows_the_member_data(): void
    {
        $member = Member::factory()->create([
            'name' => 'Maria Silva',
            'email' => 'maria@example.com',
            'birth_date' => '1995-03-10',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('members.edit', $member))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('members/Edit')
                ->where('member', [
                    'id' => $member->id,
                    'name' => 'Maria Silva',
                    'email' => 'maria@example.com',
                    'birth_date' => '1995-03-10',
                    'deactivated_at' => null,
                ])
            );
    }

    public function test_editing_a_missing_member_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('members.edit', 999))
            ->assertNotFound();
    }

    public function test_member_can_be_updated(): void
    {
        $member = Member::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put(route('members.update', $member), [
                'name' => 'Maria Souza',
                'email' => ' Maria.Souza@Example.com ',
                'birth_date' => '1990-12-01',
            ])
            ->assertRedirect(route('members.index'))
            ->assertInertiaFlash('success', 'Aluno atualizado com sucesso.');

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'name' => 'Maria Souza',
            'email' => 'maria.souza@example.com',
            'birth_date' => '1990-12-01',
        ]);
    }

    public function test_member_can_keep_their_own_email_when_updated(): void
    {
        $member = Member::factory()->create(['email' => 'maria@example.com']);

        $this->actingAs(User::factory()->create())
            ->put(route('members.update', $member), [
                'name' => 'Maria Souza',
                'email' => 'maria@example.com',
                'birth_date' => '1990-12-01',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('members.index'));
    }

    public function test_member_cannot_take_another_members_email(): void
    {
        Member::factory()->create(['email' => 'joao@example.com']);
        $member = Member::factory()->create(['email' => 'maria@example.com']);

        $this->actingAs(User::factory()->create())
            ->put(route('members.update', $member), [
                'name' => 'Maria Souza',
                'email' => 'JOAO@example.com',
                'birth_date' => '1990-12-01',
            ])
            ->assertSessionHasErrors(['email' => 'Este e-mail já está em uso.']);

        $this->assertSame('maria@example.com', $member->fresh()->email);
    }

    public function test_update_validates_required_fields(): void
    {
        $member = Member::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put(route('members.update', $member), [])
            ->assertSessionHasErrors(['name', 'email', 'birth_date']);
    }

    public function test_members_can_be_searched_by_name(): void
    {
        Member::factory()->create(['name' => 'Maria Silva']);
        Member::factory()->create(['name' => 'João Souza']);

        $this->actingAs(User::factory()->create())
            ->get(route('members.index', ['search' => 'silva']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('members.data', 1)
                ->where('members.data.0.name', 'Maria Silva')
                ->where('filters.search', 'silva')
            );
    }

    public function test_members_can_be_searched_by_email(): void
    {
        Member::factory()->create(['email' => 'maria@gym.com']);
        Member::factory()->create(['email' => 'joao@example.com']);

        $this->actingAs(User::factory()->create())
            ->get(route('members.index', ['search' => '@gym']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('members.data', 1)
                ->where('members.data.0.email', 'maria@gym.com')
            );
    }

    public function test_members_can_be_searched_by_id_with_or_without_hash(): void
    {
        $member = Member::factory()->create(['name' => 'Maria Silva']);
        Member::factory()->create(['name' => 'João Souza']);

        foreach ([(string) $member->id, '#'.$member->id] as $term) {
            $this->actingAs(User::factory()->create())
                ->get(route('members.index', ['search' => $term]))
                ->assertInertia(fn (Assert $page) => $page
                    ->has('members.data', 1)
                    ->where('members.data.0.id', $member->id)
                );
        }
    }

    public function test_search_treats_like_wildcards_as_plain_text(): void
    {
        Member::factory()->create(['name' => 'Maria Silva']);

        $this->actingAs(User::factory()->create())
            ->get(route('members.index', ['search' => '%']))
            ->assertInertia(fn (Assert $page) => $page->has('members.data', 0));
    }

    public function test_pagination_links_keep_the_search_term(): void
    {
        Member::factory(16)->create(['name' => 'Maria']);

        $this->actingAs(User::factory()->create())
            ->get(route('members.index', ['search' => 'maria']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('members.next_page_url', fn (string $url) => str_contains($url, 'search=maria'))
            );
    }

    public function test_search_term_cannot_be_too_long(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('members.index', ['search' => str_repeat('a', 101)]))
            ->assertSessionHasErrors('search');
    }

    public function test_list_shows_only_active_members_by_default(): void
    {
        Member::factory()->create(['name' => 'Ana Ativa']);
        Member::factory()->create(['name' => 'Ina Inativa', 'deactivated_at' => now()]);

        $this->actingAs(User::factory()->create())
            ->get(route('members.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('members.data', 1)
                ->where('members.data.0.name', 'Ana Ativa')
                ->where('filters.status', 'active')
            );
    }

    public function test_inactive_tab_shows_only_deactivated_members(): void
    {
        Member::factory()->create(['name' => 'Ana Ativa']);
        Member::factory()->create(['name' => 'Ina Inativa', 'deactivated_at' => now()]);

        $this->actingAs(User::factory()->create())
            ->get(route('members.index', ['status' => 'inactive']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('members.data', 1)
                ->where('members.data.0.name', 'Ina Inativa')
                ->where('filters.status', 'inactive')
            );
    }

    public function test_search_is_applied_within_the_selected_tab(): void
    {
        Member::factory()->create(['name' => 'Maria Ativa']);
        Member::factory()->create(['name' => 'Maria Inativa', 'deactivated_at' => now()]);

        $this->actingAs(User::factory()->create())
            ->get(route('members.index', ['status' => 'inactive', 'search' => 'maria']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('members.data', 1)
                ->where('members.data.0.name', 'Maria Inativa')
            );
    }

    public function test_pagination_links_keep_the_selected_tab(): void
    {
        Member::factory(16)->create(['deactivated_at' => now()]);

        $this->actingAs(User::factory()->create())
            ->get(route('members.index', ['status' => 'inactive']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('members.next_page_url', fn (string $url) => str_contains($url, 'status=inactive'))
            );
    }

    public function test_status_must_be_active_or_inactive(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('members.index', ['status' => 'deleted']))
            ->assertSessionHasErrors('status');
    }
}
