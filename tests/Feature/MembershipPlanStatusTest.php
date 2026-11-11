<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MembershipPlanStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_deactivate_a_plan(): void
    {
        $plan = MembershipPlan::factory()->create();

        $this->post(route('membership-plans.deactivate', $plan))->assertRedirect(route('login'));

        $this->assertTrue($plan->fresh()->isActive());
    }

    public function test_plan_can_be_deactivated(): void
    {
        $this->freezeSecond();
        $plan = MembershipPlan::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('membership-plans.deactivate', $plan))
            ->assertRedirect(route('membership-plans.index'))
            ->assertInertiaFlash('success', 'Plano desativado.');

        $plan->refresh();
        $this->assertFalse($plan->isActive());
        $this->assertTrue($plan->deactivated_at->equalTo(now()));
    }

    public function test_deactivating_twice_keeps_the_original_date(): void
    {
        $plan = MembershipPlan::factory()->create(['deactivated_at' => '2026-01-10 08:00:00']);

        $this->actingAs(User::factory()->create())
            ->post(route('membership-plans.deactivate', $plan))
            ->assertRedirect(route('membership-plans.index'));

        $this->assertSame('2026-01-10 08:00:00', $plan->fresh()->deactivated_at->toDateTimeString());
    }

    public function test_deactivated_plan_leaves_the_list_and_the_enrollment_options(): void
    {
        $plan = MembershipPlan::factory()->create();
        $user = User::factory()->create();
        $enrollment = Enrollment::factory()->for($plan)->create();

        $this->actingAs($user)->post(route('membership-plans.deactivate', $plan));

        $this->actingAs($user)
            ->get(route('membership-plans.index'))
            ->assertInertia(fn (Assert $page) => $page->has('plans.data', 0));

        $this->actingAs($user)
            ->get(route('members.enrollments.create', $enrollment->member))
            ->assertInertia(fn (Assert $page) => $page->has('plans', 0));
    }

    public function test_deactivating_a_plan_keeps_existing_enrollments(): void
    {
        $plan = MembershipPlan::factory()->create();
        $enrollment = Enrollment::factory()->for($plan)->create();

        $this->actingAs(User::factory()->create())
            ->post(route('membership-plans.deactivate', $plan));

        $enrollment->refresh();
        $this->assertSame($plan->id, $enrollment->membership_plan_id);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
    }

    public function test_deactivating_a_missing_plan_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('membership-plans.deactivate', 999))
            ->assertNotFound();
    }
}
