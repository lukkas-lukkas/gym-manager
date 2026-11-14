<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RevenueStatus;
use App\Models\Enrollment;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Revenue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RevenueTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_revenues(): void
    {
        $this->get(route('revenues.index'))->assertRedirect(route('login'));
    }

    public function test_index_shows_the_current_month_by_default_with_member_and_plan(): void
    {
        $this->travelTo('2026-11-15 10:00:00');

        $member = Member::factory()->create(['name' => 'Maria Silva']);
        $plan = MembershipPlan::factory()->create(['name' => 'Plano Mensal']);
        $enrollment = Enrollment::factory()->for($member)->for($plan)->create();

        Revenue::factory()->create(['due_on' => '2026-11-30']);
        Revenue::factory()->for($enrollment)->create(['due_on' => '2026-11-01', 'price_in_cents' => 12_990]);
        Revenue::factory()->create(['due_on' => '2026-11-10', 'status' => RevenueStatus::Paid, 'paid_at' => now()]);
        Revenue::factory()->create(['due_on' => '2026-10-31']);
        Revenue::factory()->create(['due_on' => '2026-12-01']);

        $this->actingAs(User::factory()->create())
            ->get(route('revenues.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('revenues/Index')
                ->where('month', '2026-11')
                ->where('previousMonth', '2026-10')
                ->where('nextMonth', '2026-12')
                ->has('revenues.data', 3)
                ->where('revenues.data.0', [
                    'id' => $enrollment->revenue->id,
                    'member_id' => $member->id,
                    'member_name' => 'Maria Silva',
                    'plan_name' => 'Plano Mensal',
                    'price_in_cents' => 12_990,
                    'due_on' => '2026-11-01',
                    'status' => 'pending',
                    'status_label' => 'Pendente',
                    'is_overdue' => true,
                ])
                ->where('revenues.data.1.due_on', '2026-11-10')
                ->where('revenues.data.1.status_label', 'Pago')
                ->where('revenues.data.1.is_overdue', false)
                ->where('revenues.data.2.due_on', '2026-11-30')
                ->where('revenues.data.2.is_overdue', false)
            );
    }

    public function test_index_shows_the_month_from_the_url(): void
    {
        Revenue::factory()->create(['due_on' => '2027-01-31']);
        Revenue::factory()->create(['due_on' => '2027-02-01']);

        $this->actingAs(User::factory()->create())
            ->get(route('revenues.index', ['month' => '2027-01']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('month', '2027-01')
                ->where('previousMonth', '2026-12')
                ->where('nextMonth', '2027-02')
                ->has('revenues.data', 1)
                ->where('revenues.data.0.due_on', '2027-01-31')
            );
    }

    public function test_index_keeps_the_month_from_the_url_on_the_last_day_of_a_month(): void
    {
        $this->travelTo('2026-10-31 10:00:00');

        $this->actingAs(User::factory()->create())
            ->get(route('revenues.index', ['month' => '2026-11']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('month', '2026-11')
                ->where('previousMonth', '2026-10')
                ->where('nextMonth', '2026-12')
            );
    }

    public function test_index_is_paginated_keeping_the_month_in_the_links(): void
    {
        Revenue::factory(16)->create(['due_on' => '2027-03-10']);

        $this->actingAs(User::factory()->create())
            ->get(route('revenues.index', ['month' => '2027-03']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('revenues.data', 15)
                ->where('revenues.total', 16)
                ->where('revenues.next_page_url', fn (string $url): bool => str_contains($url, 'month=2027-03') && str_contains($url, 'page=2'))
            );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidMonths(): array
    {
        return [
            'month 13' => ['2026-13'],
            'full date' => ['2026-11-01'],
            'text' => ['novembro'],
        ];
    }

    #[DataProvider('invalidMonths')]
    public function test_index_rejects_an_invalid_month(string $month): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('revenues.index', ['month' => $month]))
            ->assertSessionHasErrors('month');
    }
}
