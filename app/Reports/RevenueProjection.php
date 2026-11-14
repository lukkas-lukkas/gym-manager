<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\BillingPeriod;
use App\Enums\EnrollmentStatus;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The revenues that don't exist yet but will: the renewals of the members' current plans.
 *
 * A revenue is only created when an enrollment is, so the months after a member's last enrollment have nothing
 * due yet. This projects those renewals with the same rules the roll-over uses (see
 * {@see Member::rollOverExpiredEnrollment()}):
 *
 * - Only active members, starting from the end of their last scheduled enrollment (active or upcoming). Upcoming
 *   enrollments already have their revenue, so they are never projected again.
 * - Same plan, at the plan's current price, renewed every billing period: each renewal starts (and is due) the
 *   day after the previous one ends.
 * - Deactivated members are left out: the roll-over doesn't renew them either.
 *
 * Nothing is saved: the projection is worked out on every read, so it never gets out of date.
 */
final readonly class RevenueProjection
{
    public function __construct(private ReportPeriod $period) {}

    /**
     * One projected amount in cents per month of the period, in the order of {@see ReportPeriod::months()}.
     *
     * @return list<int>
     */
    public function toArray(): array
    {
        return $this->period->alignToMonths($this->byMonth());
    }

    /**
     * Projected amount in cents keyed by `YYYY-MM`, only for months of the period that have renewals.
     *
     * @return array<string, int>
     */
    public function byMonth(): array
    {
        $totalsByMonth = [];

        foreach ($this->renewalGroups() as $group) {
            $billingPeriodMonths = BillingPeriod::from($group->billing_period)->months();
            $amountInCents = (int) $group->price_in_cents * (int) $group->member_count;
            $dueOn = CarbonImmutable::parse($group->ends_on)->addDay();

            while ($dueOn->lessThanOrEqualTo($this->period->endsOn)) {
                if ($dueOn->greaterThanOrEqualTo($this->period->startsOn)) {
                    $month = $dueOn->format('Y-m');
                    $totalsByMonth[$month] = ($totalsByMonth[$month] ?? 0) + $amountInCents;
                }

                $dueOn = $dueOn->addMonthsNoOverflow($billingPeriodMonths)->addDay();
            }
        }

        return $totalsByMonth;
    }

    /**
     * Members whose plan renews inside the period, grouped by the day their last enrollment ends and by plan.
     *
     * Grouping makes the result size depend on how many distinct end dates and plans there are (at most a few
     * hundred rows a year), never on how many members the gym has. Members who end on the same day on the same
     * plan renew together, so one row with their count is enough.
     *
     * @return iterable<object{ends_on: string, price_in_cents: int|string, billing_period: string, member_count: int|string}>
     */
    private function renewalGroups(): iterable
    {
        return DB::query()
            ->fromSub($this->lastScheduledEnrollments(), 'last_enrollment')
            ->join('members', 'members.id', '=', 'last_enrollment.member_id')
            ->join('membership_plans', 'membership_plans.id', '=', 'last_enrollment.membership_plan_id')
            ->where('last_enrollment.position', 1)
            ->whereNull('members.deactivated_at')
            ->where('last_enrollment.ends_on', '<', $this->period->endsOn->toDateString())
            ->groupBy('last_enrollment.ends_on', 'membership_plans.id', 'membership_plans.price_in_cents', 'membership_plans.billing_period')
            ->select(['last_enrollment.ends_on', 'membership_plans.price_in_cents', 'membership_plans.billing_period'])
            ->selectRaw('COUNT(*) as member_count')
            ->get();
    }

    /**
     * Every active or upcoming enrollment, numbered per member from the one that ends last (`position` 1).
     *
     * Same choice as {@see Member::lastScheduledEnrollment()}, done for all members in one query.
     *
     * The two kinds are read separately and joined with `UNION ALL` instead of `status = active OR starts_on > today`:
     * an `OR` across two columns makes MySQL read the whole table, and enrollments keep every member's history, so
     * the table only grows. Split this way, both parts use the `(status, ends_on)` index and only read the current
     * and upcoming enrollments, about one or two per member. Upcoming enrollments are always saved as inactive
     * (see {@see Member::enroll()}) and end after today.
     */
    private function lastScheduledEnrollments(): Builder
    {
        $today = today()->toDateString();
        $columns = ['id', 'member_id', 'membership_plan_id', 'ends_on'];

        $activeEnrollments = DB::table('enrollments')
            ->select($columns)
            ->where('status', EnrollmentStatus::Active->value);

        $upcomingEnrollments = DB::table('enrollments')
            ->select($columns)
            ->where('status', EnrollmentStatus::Inactive->value)
            ->where('ends_on', '>', $today)
            ->where('starts_on', '>', $today);

        return DB::query()
            ->fromSub($activeEnrollments->unionAll($upcomingEnrollments), 'scheduled_enrollment')
            ->select(['member_id', 'membership_plan_id', 'ends_on'])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY member_id ORDER BY ends_on DESC, id DESC) as position');
    }
}
