<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EnrollmentStatus;
use App\Enums\RevenueStatus;
use App\Models\Member;
use App\Models\MembershipPlan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class EnrollmentSeeder extends Seeder
{
    /**
     * How far back the oldest enrollment can start, so the reports have a few months of history.
     */
    private const HISTORY_MONTHS = 6;

    /**
     * Share of past revenues that end up paid; the rest stay pending, as overdue charges.
     */
    private const PAID_PERCENT = 85;

    /**
     * Give every member without enrollments a history of back-to-back enrollments, each with its revenue.
     *
     * It follows the same rules as {@see Member::enroll()}: the next enrollment starts the day after the previous
     * one ends, and the revenue keeps the plan price and is due on the first day. Enrollments are chained until one
     * covers today, which is the active one. Most past revenues are paid up to a week late; the rest stay pending.
     * Members that already have enrollments are skipped, so running it again adds nothing.
     */
    public function run(): void
    {
        $plans = MembershipPlan::query()->active()->get();

        if ($plans->isEmpty()) {
            return;
        }

        $today = CarbonImmutable::today();
        $historyStartsOn = $today->subMonthsNoOverflow(self::HISTORY_MONTHS);

        Member::query()->whereDoesntHave('enrollments')->each(function (Member $member) use ($plans, $today, $historyStartsOn): void {
            $plan = $plans->random();
            $startsOn = $historyStartsOn->addDays(fake()->numberBetween(0, (int) $historyStartsOn->diffInDays($today)));

            while ($startsOn->lessThanOrEqualTo($today)) {
                $endsOn = $startsOn->addMonthsNoOverflow($plan->billing_period->months());

                $enrollment = $member->enrollments()->create([
                    'membership_plan_id' => $plan->id,
                    'starts_on' => $startsOn,
                    'ends_on' => $endsOn,
                    'status' => $today->betweenIncluded($startsOn, $endsOn) ? EnrollmentStatus::Active : EnrollmentStatus::Inactive,
                ]);

                $paidAt = $startsOn->addDays(fake()->numberBetween(0, 7))->setTime(fake()->numberBetween(6, 22), fake()->numberBetween(0, 59));
                $isPaid = $paidAt->isPast() && fake()->boolean(self::PAID_PERCENT);

                $enrollment->revenue()->create([
                    'member_id' => $member->id,
                    'price_in_cents' => $plan->price_in_cents,
                    'due_on' => $startsOn,
                    'status' => $isPaid ? RevenueStatus::Paid : RevenueStatus::Pending,
                    'paid_at' => $isPaid ? $paidAt : null,
                ]);

                $startsOn = $endsOn->addDay();
            }
        });
    }
}
