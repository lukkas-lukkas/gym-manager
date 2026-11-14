<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BillingPeriod;
use App\Models\MembershipPlan;
use Illuminate\Database\Seeder;

class MembershipPlanSeeder extends Seeder
{
    /**
     * Seed the plans a typical gym sells, for local development.
     *
     * Matched by name, so running the seeder again updates them instead of creating duplicates.
     */
    public function run(): void
    {
        $plans = [
            ['name' => 'Mensal', 'price_in_cents' => 12_000, 'billing_period' => BillingPeriod::Monthly],
            ['name' => 'Trimestral', 'price_in_cents' => 33_000, 'billing_period' => BillingPeriod::Quarterly],
            ['name' => 'Anual', 'price_in_cents' => 110_000, 'billing_period' => BillingPeriod::Yearly],
        ];

        foreach ($plans as $plan) {
            MembershipPlan::updateOrCreate(['name' => $plan['name']], $plan);
        }
    }
}
