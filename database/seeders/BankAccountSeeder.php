<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BankAccount;
use Illuminate\Database\Seeder;

class BankAccountSeeder extends Seeder
{
    /**
     * Seed the gym's bank account for local development, opened now with R$ 10.000,00.
     *
     * Skipped when the account exists, so running it again adds nothing.
     */
    public function run(): void
    {
        if (BankAccount::current() !== null) {
            return;
        }

        BankAccount::factory()->create([
            'name' => 'Conta principal',
            'opening_balance_in_cents' => 1_000_000,
        ]);
    }
}
