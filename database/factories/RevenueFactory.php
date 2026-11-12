<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RevenueStatus;
use App\Models\Enrollment;
use App\Models\Revenue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Revenue>
 */
class RevenueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'member_id' => fn (array $attributes): int => Enrollment::findOrFail($attributes['enrollment_id'])->member_id,
            'price_in_cents' => fake()->numberBetween(5_000, 30_000),
            'due_on' => fn (array $attributes): string => Enrollment::findOrFail($attributes['enrollment_id'])->starts_on->toDateString(),
            'status' => RevenueStatus::Pending,
        ];
    }
}
