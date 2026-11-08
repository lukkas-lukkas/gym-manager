<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MemberStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_change_member_status(): void
    {
        $member = Member::factory()->create();

        $this->post(route('members.deactivate', $member))->assertRedirect(route('login'));
        $this->post(route('members.activate', $member))->assertRedirect(route('login'));

        $this->assertTrue($member->fresh()->isActive());
    }

    public function test_member_can_be_deactivated(): void
    {
        $this->freezeSecond();
        $member = Member::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('members.deactivate', $member))
            ->assertRedirect(route('members.edit', $member))
            ->assertInertiaFlash('success', 'Aluno desativado.');

        $member->refresh();
        $this->assertFalse($member->isActive());
        $this->assertTrue($member->deactivated_at->equalTo(now()));
    }

    public function test_deactivating_twice_keeps_the_original_date(): void
    {
        $member = Member::factory()->create(['deactivated_at' => '2026-01-10 08:00:00']);

        $this->actingAs(User::factory()->create())
            ->post(route('members.deactivate', $member))
            ->assertRedirect(route('members.edit', $member));

        $this->assertSame('2026-01-10 08:00:00', $member->fresh()->deactivated_at->toDateTimeString());
    }

    public function test_member_can_be_activated(): void
    {
        $member = Member::factory()->create(['deactivated_at' => now()]);

        $this->actingAs(User::factory()->create())
            ->post(route('members.activate', $member))
            ->assertRedirect(route('members.edit', $member))
            ->assertInertiaFlash('success', 'Aluno reativado.');

        $this->assertTrue($member->fresh()->isActive());
    }

    public function test_edit_page_exposes_the_member_status(): void
    {
        $member = Member::factory()->create(['deactivated_at' => '2026-01-10 08:00:00']);

        $this->actingAs(User::factory()->create())
            ->get(route('members.edit', $member))
            ->assertInertia(fn (Assert $page) => $page
                ->where('member.deactivated_at', $member->deactivated_at->toIso8601String())
            );
    }
}
