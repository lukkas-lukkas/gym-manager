<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Plans come before enrollments, which pick one of them for each member.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            MemberSeeder::class,
            MembershipPlanSeeder::class,
            EnrollmentSeeder::class,
            ExpenseSeeder::class,
            BankAccountSeeder::class,
        ]);
    }
}
