<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Member;
use Illuminate\Database\Seeder;

class MemberSeeder extends Seeder
{
    /**
     * A few sample members for local development, with no enrollments yet.
     *
     * @var list<array{name: string, email: string, birth_date: string}>
     */
    private const MEMBERS = [
        ['name' => 'Ana Souza', 'email' => 'ana.souza@example.com', 'birth_date' => '1995-03-12'],
        ['name' => 'Bruno Lima', 'email' => 'bruno.lima@example.com', 'birth_date' => '1988-07-25'],
        ['name' => 'Carla Mendes', 'email' => 'carla.mendes@example.com', 'birth_date' => '2001-11-04'],
    ];

    /**
     * Seed the sample members.
     *
     * Matched by email, so running the seeder again updates them instead of creating duplicates.
     */
    public function run(): void
    {
        foreach (self::MEMBERS as $member) {
            Member::updateOrCreate(['email' => $member['email']], $member);
        }
    }
}
