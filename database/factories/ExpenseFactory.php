<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'description' => fake()->sentence(3),
            'amount_in_cents' => fake()->numberBetween(1_000, 500_000),
            'due_on' => fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'status' => ExpenseStatus::Pending,
        ];
    }

    /**
     * An expense that has already been paid.
     */
    public function paid(): static
    {
        return $this->state(fn (): array => ['status' => ExpenseStatus::Paid, 'paid_at' => now()]);
    }
}
