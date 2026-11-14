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
use App\Reports\ReportPeriod;
use App\Reports\RevenueProjection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RevenueProjectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-15 10:00:00');
    }

    public function test_monthly_plan_renews_the_day_after_each_enrollment_ends_until_the_period_ends(): void
    {
        $plan = MembershipPlan::factory()->create(['price_in_cents' => 10_000, 'billing_period' => BillingPeriod::Monthly]);
        $this->enroll(Member::factory()->create(), $plan, '2026-11-01', '2026-12-01');

        // Renewals due on 02/12, 03/01, 04/02 and 05/03: the last one is after the period.
        $this->assertSame(
            ['2026-12' => 10_000, '2027-01' => 10_000, '2027-02' => 10_000],
            $this->projection('2026-11', '2027-02'),
        );
    }

    public function test_quarterly_plan_renews_every_three_months(): void
    {
        $plan = MembershipPlan::factory()->create(['price_in_cents' => 30_000, 'billing_period' => BillingPeriod::Quarterly]);
        $this->enroll(Member::factory()->create(), $plan, '2026-11-01', '2027-02-01');

        $this->assertSame(
            ['2027-02' => 30_000, '2027-05' => 30_000],
            $this->projection('2026-11', '2027-06'),
        );
    }

    public function test_renewals_use_the_current_plan_price(): void
    {
        $plan = MembershipPlan::factory()->create(['price_in_cents' => 10_000, 'billing_period' => BillingPeriod::Monthly]);
        $this->enroll(Member::factory()->create(), $plan, '2026-11-01', '2026-12-01');

        $plan->update(['price_in_cents' => 15_000]);

        $this->assertSame(['2026-12' => 15_000], $this->projection('2026-12', '2026-12'));
    }

    public function test_deactivated_members_are_not_renewed(): void
    {
        $plan = MembershipPlan::factory()->create(['billing_period' => BillingPeriod::Monthly]);
        $member = Member::factory()->create();
        $this->enroll($member, $plan, '2026-11-01', '2026-12-01');

        $member->deactivate();

        $this->assertSame([], $this->projection('2026-11', '2027-02'));
    }

    public function test_renewals_start_after_the_upcoming_enrollment_which_already_has_its_revenue(): void
    {
        $plan = MembershipPlan::factory()->create(['price_in_cents' => 10_000, 'billing_period' => BillingPeriod::Monthly]);
        $member = Member::factory()->create();
        $this->enroll($member, $plan, '2026-11-01', '2026-12-01');
        $this->enroll($member, $plan, '2026-12-02', '2027-01-02', EnrollmentStatus::Inactive);

        $this->assertSame(
            ['2027-01' => 10_000, '2027-02' => 10_000],
            $this->projection('2026-12', '2027-02'),
        );
    }

    public function test_ended_enrollments_are_ignored(): void
    {
        $plan = MembershipPlan::factory()->create(['billing_period' => BillingPeriod::Monthly]);
        $this->enroll(Member::factory()->create(), $plan, '2026-09-01', '2026-10-01', EnrollmentStatus::Inactive);

        $this->assertSame([], $this->projection('2026-11', '2027-02'));
    }

    public function test_members_renewing_on_the_same_day_and_plan_add_up(): void
    {
        $plan = MembershipPlan::factory()->create(['price_in_cents' => 10_000, 'billing_period' => BillingPeriod::Monthly]);

        foreach (Member::factory(3)->create() as $member) {
            $this->enroll($member, $plan, '2026-11-01', '2026-12-01');
        }

        $this->assertSame(['2026-12' => 30_000], $this->projection('2026-12', '2026-12'));
    }

    public function test_projection_matches_the_revenues_the_real_renewals_create(): void
    {
        // Starting near the end of the month, a renewal date that is off by a single day already lands in the
        // wrong month, so the totals per month expose it.
        $this->travelTo('2026-11-28 10:00:00');

        $plan = MembershipPlan::factory()->create(['price_in_cents' => 10_000, 'billing_period' => BillingPeriod::Monthly]);
        $member = Member::factory()->create();
        $member->enroll($plan);
        $period = ReportPeriod::fromMonths('2026-12', '2027-11');

        $projected = (new RevenueProjection($period))->byMonth();
        ksort($projected);

        foreach (range(1, 14) as $renewal) {
            $member->enroll($plan);
        }

        $created = Revenue::query()
            ->whereBetween('due_on', [$period->startsOn->toDateString(), $period->endsOn->toDateString()])
            ->get()
            ->groupBy(fn (Revenue $revenue): string => $revenue->due_on->format('Y-m'))
            ->map(fn ($revenues): int => $revenues->sum('price_in_cents'))
            ->all();

        $this->assertSame($created, $projected);
    }

    public function test_reports_page_shows_the_projection_apart_from_the_revenues_already_due(): void
    {
        $plan = MembershipPlan::factory()->create(['price_in_cents' => 10_000, 'billing_period' => BillingPeriod::Monthly]);
        $enrollment = $this->enroll(Member::factory()->create(), $plan, '2026-11-01', '2026-12-01');
        Revenue::factory()->for($enrollment)->create(['price_in_cents' => 10_000, 'due_on' => '2026-11-01', 'status' => RevenueStatus::Pending]);

        $this->actingAs(User::factory()->create())
            ->get(route('reports.index', ['from' => '2026-11', 'to' => '2026-12']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('revenueProjection')
                ->loadDeferredProps(['revenues', 'revenueProjection'], fn (Assert $reload) => $reload
                    ->where('revenues.forecast', [10_000, 0])
                    ->where('revenueProjection', [0, 10_000])
                )
            );
    }

    /**
     * @return array<string, int>
     */
    private function projection(string $firstMonth, string $lastMonth): array
    {
        $projection = (new RevenueProjection(ReportPeriod::fromMonths($firstMonth, $lastMonth)))->byMonth();
        ksort($projection);

        return $projection;
    }

    private function enroll(Member $member, MembershipPlan $plan, string $startsOn, string $endsOn, EnrollmentStatus $status = EnrollmentStatus::Active): Enrollment
    {
        return Enrollment::factory()->for($member)->for($plan)->create([
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'status' => $status,
        ]);
    }
}
