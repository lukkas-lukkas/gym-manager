<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a gym with history: a user to log in and a year and a half of members, plans, enrollments, revenues,
     * expenses and the bank account, so the dashboard and the reports show real-looking numbers from the start.
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
