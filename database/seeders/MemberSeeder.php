<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Member;
use Illuminate\Database\Seeder;

class MemberSeeder extends Seeder
{
    /**
     * Seed sample members for local development.
     */
    public function run(): void
    {
        Member::factory(10)->create();
    }
}
