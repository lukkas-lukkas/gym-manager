<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BillingPeriod;
use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipPlan>
 */
class MembershipPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Plano '.fake()->unique()->words(2, true),
            'price_in_cents' => fake()->numberBetween(5_000, 30_000),
            'billing_period' => fake()->randomElement(BillingPeriod::cases()),
        ];
    }

    /**
     * A plan that has been deactivated.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => ['deactivated_at' => now()]);
    }
}
