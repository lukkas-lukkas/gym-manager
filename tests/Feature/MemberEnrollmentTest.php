<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Enums\EnrollmentStatus;
use App\Enums\RevenueStatus;
use App\Models\Enrollment;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Revenue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MemberEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_see_plan_history(): void
    {
        $member = Member::factory()->create();

        $this->get(route('members.enrollments.index', $member))->assertRedirect(route('login'));
    }

    public function test_plan_history_is_listed_most_recent_first_with_plan_data(): void
    {
        $member = Member::factory()->create(['name' => 'Maria Silva']);
        $monthlyPlan = MembershipPlan::factory()->create(['name' => 'Plano Mensal']);
        $yearlyPlan = MembershipPlan::factory()->create([
            'name' => 'Plano Anual',
            'price_in_cents' => 99_990,
            'billing_period' => BillingPeriod::Yearly,
        ]);

        $endedEnrollment = Enrollment::factory()->for($member)->for($monthlyPlan)->create([
            'starts_on' => '2026-01-10',
            'ends_on' => '2026-06-10',
            'status' => EnrollmentStatus::Inactive,
        ]);
        $activeEnrollment = Enrollment::factory()->for($member)->for($yearlyPlan)->create([
            'starts_on' => '2026-06-10',
            'ends_on' => '2027-06-10',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('members.enrollments.index', $member))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('members/Enrollments')
                ->where('member', ['id' => $member->id, 'name' => 'Maria Silva', 'deactivated_at' => null])
                ->has('enrollments.data', 2)
                ->where('enrollments.data.0', [
                    'id' => $activeEnrollment->id,
                    'plan_name' => 'Plano Anual',
                    'price_in_cents' => 99_990,
                    'billing_period_label' => 'Anual',
                    'starts_on' => '2026-06-10',
                    'ends_on' => '2027-06-10',
                    'status' => 'active',
                    'revenue' => null,
                ])
                ->where('enrollments.data.1.id', $endedEnrollment->id)
                ->where('enrollments.data.1.ends_on', '2026-06-10')
                ->where('enrollments.data.1.status', 'inactive')
            );
    }

    public function test_history_shows_the_payment_of_each_plan_with_the_price_of_the_enrollment_day(): void
    {
        $this->travelTo('2026-11-10 12:00:00');

        $member = Member::factory()->create();
        $plan = MembershipPlan::factory()->create(['price_in_cents' => 15_000]);
        $enrollment = Enrollment::factory()->for($member)->for($plan)->create(['starts_on' => '2026-11-05', 'ends_on' => '2026-12-05']);
        $revenue = Revenue::factory()->for($enrollment)->create(['price_in_cents' => 12_000, 'due_on' => '2026-11-05']);

        $this->actingAs(User::factory()->create())
            ->get(route('members.enrollments.index', $member))
            ->assertInertia(fn (Assert $page) => $page
                ->where('enrollments.data.0.price_in_cents', 12_000)
                ->where('enrollments.data.0.revenue', [
                    'id' => $revenue->id,
                    'due_on' => '2026-11-05',
                    'status' => 'pending',
                    'status_label' => 'Pendente',
                    'is_overdue' => true,
                    'paid_at' => null,
                ])
            );
    }

    public function test_history_does_not_mark_a_revenue_due_today_as_overdue(): void
    {
        $this->travelTo('2026-11-10 12:00:00');

        $member = Member::factory()->create();
        $enrollment = Enrollment::factory()->for($member)->create(['starts_on' => '2026-11-10', 'ends_on' => '2026-12-10']);
        Revenue::factory()->for($enrollment)->create(['due_on' => '2026-11-10']);

        $this->actingAs(User::factory()->create())
            ->get(route('members.enrollments.index', $member))
            ->assertInertia(fn (Assert $page) => $page->where('enrollments.data.0.revenue.is_overdue', false));
    }

    public function test_history_keeps_showing_plans_that_were_deactivated(): void
    {
        $member = Member::factory()->create();
        $inactivePlan = MembershipPlan::factory()->inactive()->create(['name' => 'Plano Antigo']);
        Enrollment::factory()->for($member)->for($inactivePlan)->create(['status' => EnrollmentStatus::Inactive]);

        $this->actingAs(User::factory()->create())
            ->get(route('members.enrollments.index', $member))
            ->assertInertia(fn (Assert $page) => $page
                ->has('enrollments.data', 1)
                ->where('enrollments.data.0.plan_name', 'Plano Antigo')
            );
    }

    public function test_history_only_shows_the_given_members_enrollments(): void
    {
        $member = Member::factory()->create();
        Enrollment::factory()->for($member)->create();
        Enrollment::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('members.enrollments.index', $member))
            ->assertInertia(fn (Assert $page) => $page->has('enrollments.data', 1));
    }

    public function test_member_without_enrollments_has_empty_history(): void
    {
        $member = Member::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('members.enrollments.index', $member))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('enrollments.data', 0));
    }

    public function test_history_is_paginated(): void
    {
        $member = Member::factory()->create();
        Enrollment::factory(16)->for($member)->create(['status' => EnrollmentStatus::Inactive]);

        $this->actingAs(User::factory()->create())
            ->get(route('members.enrollments.index', $member))
            ->assertInertia(fn (Assert $page) => $page
                ->has('enrollments.data', 15)
                ->where('enrollments.total', 16)
            );
    }

    public function test_guest_cannot_see_the_new_plan_page(): void
    {
        $member = Member::factory()->create();

        $this->get(route('members.enrollments.create', $member))->assertRedirect(route('login'));
    }

    public function test_new_plan_starts_the_day_after_the_current_plan_ends(): void
    {
        $this->travelTo('2026-11-10 12:00:00');

        $member = Member::factory()->create(['name' => 'Maria Silva']);
        $currentPlan = MembershipPlan::factory()->create(['name' => 'Plano Mensal']);
        Enrollment::factory()->for($member)->for($currentPlan)->create([
            'starts_on' => '2026-10-31',
            'ends_on' => '2026-11-30',
        ]);
        Enrollment::factory()->for($member)->create([
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-02-01',
            'status' => EnrollmentStatus::Inactive,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('members.enrollments.create', $member))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('members/CreateEnrollment')
                ->where('member', ['id' => $member->id, 'name' => 'Maria Silva'])
                ->where('previousEnrollment', ['plan_name' => 'Plano Mensal', 'ends_on' => '2026-11-30'])
                ->where('startsOn', '2026-12-01')
            );
    }

    public function test_member_without_active_plan_starts_the_new_plan_today(): void
    {
        $this->travelTo('2026-11-10 12:00:00');

        $member = Member::factory()->create();
        Enrollment::factory()->for($member)->create([
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-02-01',
            'status' => EnrollmentStatus::Inactive,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('members.enrollments.create', $member))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('previousEnrollment', null)
                ->where('startsOn', '2026-11-10')
            );
    }

    public function test_new_plan_starts_after_the_upcoming_plan_when_there_is_one(): void
    {
        $this->travelTo('2026-11-10 12:00:00');

        $member = Member::factory()->create();
        Enrollment::factory()->for($member)->create(['starts_on' => '2026-10-31', 'ends_on' => '2026-11-30']);
        $upcomingPlan = MembershipPlan::factory()->create(['name' => 'Plano Trimestral']);
        Enrollment::factory()->for($member)->for($upcomingPlan)->create([
            'starts_on' => '2026-12-01',
            'ends_on' => '2027-03-01',
            'status' => EnrollmentStatus::Inactive,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('members.enrollments.create', $member))
            ->assertInertia(fn (Assert $page) => $page
                ->where('previousEnrollment', ['plan_name' => 'Plano Trimestral', 'ends_on' => '2027-03-01'])
                ->where('startsOn', '2027-03-02')
            );
    }

    public function test_new_plan_page_lists_only_active_plans_by_name(): void
    {
        $member = Member::factory()->create();
        $yearlyPlan = MembershipPlan::factory()->create([
            'name' => 'B Plano Anual',
            'price_in_cents' => 99_990,
            'billing_period' => BillingPeriod::Yearly,
        ]);
        $monthlyPlan = MembershipPlan::factory()->create(['name' => 'A Plano Mensal']);
        MembershipPlan::factory()->inactive()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('members.enrollments.create', $member))
            ->assertInertia(fn (Assert $page) => $page
                ->has('plans', 2)
                ->where('plans.0.id', $monthlyPlan->id)
                ->where('plans.1', [
                    'id' => $yearlyPlan->id,
                    'name' => 'B Plano Anual',
                    'price_in_cents' => 99_990,
                    'billing_period' => 'yearly',
                    'billing_period_label' => 'Anual',
                ])
            );
    }

    public function test_guest_cannot_enroll_a_member(): void
    {
        $member = Member::factory()->create();
        $plan = MembershipPlan::factory()->create();

        $this->post(route('members.enrollments.store', $member), ['membership_plan_id' => $plan->id])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_member_without_active_plan_is_enrolled_starting_today_as_active(): void
    {
        $this->travelTo('2026-11-10 12:00:00');

        $member = Member::factory()->create();
        $plan = MembershipPlan::factory()->create(['billing_period' => BillingPeriod::Quarterly]);

        $this->actingAs(User::factory()->create())
            ->post(route('members.enrollments.store', $member), ['membership_plan_id' => $plan->id])
            ->assertRedirect(route('members.enrollments.index', $member))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('enrollments', [
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'starts_on' => '2026-11-10',
            'ends_on' => '2027-02-10',
            'status' => 'active',
        ]);
    }

    public function test_member_with_active_plan_gets_the_new_plan_as_inactive_starting_after_it(): void
    {
        $this->travelTo('2026-11-10 12:00:00');

        $member = Member::factory()->create();
        $currentEnrollment = Enrollment::factory()->for($member)->create(['starts_on' => '2026-10-31', 'ends_on' => '2026-11-30']);
        $plan = MembershipPlan::factory()->create(['billing_period' => BillingPeriod::Monthly]);

        $this->actingAs(User::factory()->create())
            ->post(route('members.enrollments.store', $member), ['membership_plan_id' => $plan->id])
            ->assertRedirect(route('members.enrollments.index', $member));

        $this->assertDatabaseHas('enrollments', [
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'starts_on' => '2026-12-01',
            'ends_on' => '2027-01-01',
            'status' => 'inactive',
        ]);
        $this->assertSame(EnrollmentStatus::Active, $currentEnrollment->fresh()->status);
        $this->assertSame(1, $member->enrollments()->where('status', EnrollmentStatus::Active)->count());
    }

    public function test_new_enrollments_are_chained_after_the_upcoming_one(): void
    {
        $this->travelTo('2026-11-10 12:00:00');

        $member = Member::factory()->create();
        Enrollment::factory()->for($member)->create(['starts_on' => '2026-10-31', 'ends_on' => '2026-11-30']);
        $plan = MembershipPlan::factory()->create(['billing_period' => BillingPeriod::Monthly]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('members.enrollments.store', $member), ['membership_plan_id' => $plan->id]);
        $this->actingAs($user)->post(route('members.enrollments.store', $member), ['membership_plan_id' => $plan->id]);

        $this->assertSame(
            [['2026-10-31', 'active'], ['2026-12-01', 'inactive'], ['2027-01-02', 'inactive']],
            $member->enrollments()->orderBy('starts_on')->get()
                ->map(fn (Enrollment $enrollment): array => [$enrollment->starts_on->toDateString(), $enrollment->status->value])
                ->all(),
        );
    }

    public function test_member_cannot_be_enrolled_in_an_inactive_plan(): void
    {
        $member = Member::factory()->create();
        $plan = MembershipPlan::factory()->inactive()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('members.enrollments.store', $member), ['membership_plan_id' => $plan->id])
            ->assertSessionHasErrors('membership_plan_id');

        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_enrollment_requires_an_existing_plan(): void
    {
        $member = Member::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('members.enrollments.store', $member), ['membership_plan_id' => 999])
            ->assertSessionHasErrors('membership_plan_id');

        $this->actingAs(User::factory()->create())
            ->post(route('members.enrollments.store', $member), [])
            ->assertSessionHasErrors('membership_plan_id');

        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_enrolling_creates_a_pending_revenue_with_the_plan_price_due_when_the_plan_starts(): void
    {
        $this->travelTo('2026-11-10 12:00:00');

        $member = Member::factory()->create();
        Enrollment::factory()->for($member)->create(['starts_on' => '2026-10-31', 'ends_on' => '2026-11-30']);
        $plan = MembershipPlan::factory()->create(['price_in_cents' => 12_990, 'billing_period' => BillingPeriod::Monthly]);

        $this->actingAs(User::factory()->create())
            ->post(route('members.enrollments.store', $member), ['membership_plan_id' => $plan->id])
            ->assertSessionHasNoErrors();

        $revenue = $member->enrollments()->where('membership_plan_id', $plan->id)->sole()->revenue;
        $this->assertSame($member->id, $revenue->member_id);
        $this->assertSame(12_990, $revenue->price_in_cents);
        $this->assertSame('2026-12-01', $revenue->due_on->toDateString());
        $this->assertSame(RevenueStatus::Pending, $revenue->status);
        $this->assertNull($revenue->paid_at);
    }

    public function test_revenue_keeps_the_price_of_the_enrollment_day_when_the_plan_price_changes(): void
    {
        $member = Member::factory()->create();
        $plan = MembershipPlan::factory()->create(['price_in_cents' => 10_000]);

        $this->actingAs(User::factory()->create())
            ->post(route('members.enrollments.store', $member), ['membership_plan_id' => $plan->id]);

        $plan->update(['price_in_cents' => 15_000]);

        $this->assertSame(10_000, $member->revenues()->sole()->price_in_cents);
    }

    public function test_new_plan_page_of_a_missing_member_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('members.enrollments.create', 999))
            ->assertNotFound();
    }

    public function test_history_of_a_missing_member_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('members.enrollments.index', 999))
            ->assertNotFound();
    }
}
