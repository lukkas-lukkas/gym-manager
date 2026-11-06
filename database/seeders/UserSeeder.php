<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed the default user used to access the application.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'john.doe@gmail.com'],
            [
                'name' => 'John Doe',
                'password' => 'unicesumar@tcc#123',
            ],
        );
    }
}
