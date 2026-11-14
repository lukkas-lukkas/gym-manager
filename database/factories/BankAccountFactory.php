<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * A freshly opened account: nothing was paid since `opened_at` yet, so the current balance equals the opening one.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Banco do Brasil', 'Itaú', 'Nubank', 'Caixa', 'Bradesco']),
            'opening_balance_in_cents' => fn (): int => fake()->numberBetween(0, 5_000_000),
            'current_balance_in_cents' => fn (array $attributes): int => $attributes['opening_balance_in_cents'],
            'opened_at' => now(),
        ];
    }
}
