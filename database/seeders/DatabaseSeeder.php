<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the minimum a newcomer needs to start: a user to log in, the bank account, a few members, plans and
     * this month's basic expenses. Enrollments and revenues are left to be created from the app.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            MemberSeeder::class,
            MembershipPlanSeeder::class,
            ExpenseSeeder::class,
            BankAccountSeeder::class,
        ]);
    }
}
