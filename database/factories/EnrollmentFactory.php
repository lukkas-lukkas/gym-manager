<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BillingPeriod;
use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Member;
use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * Define the model's default state: an active enrollment in a monthly plan, starting today.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'membership_plan_id' => MembershipPlan::factory()->state(['billing_period' => BillingPeriod::Monthly]),
            'starts_on' => now()->format('Y-m-d'),
            'ends_on' => now()->addMonthsNoOverflow(BillingPeriod::Monthly->months()),
            'status' => EnrollmentStatus::Active,
        ];
    }

    public function quarterly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'membership_plan_id' => MembershipPlan::factory()->state(['billing_period' => BillingPeriod::Quarterly]),
            'ends_on' => now()->addMonthsNoOverflow(BillingPeriod::Quarterly->months()),
        ]);
    }

    public function semiannual(): static
    {
        return $this->state(fn (array $attributes): array => [
            'membership_plan_id' => MembershipPlan::factory()->state(['billing_period' => BillingPeriod::Semiannual]),
            'ends_on' => now()->addMonthsNoOverflow(BillingPeriod::Semiannual->months()),
        ]);
    }

    public function yearly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'membership_plan_id' => MembershipPlan::factory()->state(['billing_period' => BillingPeriod::Yearly]),
            'ends_on' => now()->addMonthsNoOverflow(BillingPeriod::Yearly->months()),
        ]);
    }
}
