<?php

namespace Tests\Feature;

use App\Enums\ExpenseStatus;
use App\Enums\RevenueStatus;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\Member;
use App\Models\Revenue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_dashboard(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_dashboard_shows_zeros_and_no_balance_when_the_gym_has_no_data(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('name', config('app.name'))
                ->where('upcomingExpenseDays', 7)
                ->where('summary', [
                    'balanceInCents' => null,
                    'activeMembers' => 0,
                    'month' => ['receivedInCents' => 0, 'toReceiveInCents' => 0, 'paidInCents' => 0, 'toPayInCents' => 0],
                    'overdueRevenues' => ['count' => 0, 'totalInCents' => 0, 'oldest' => []],
                    'upcomingExpenses' => [],
                ])
            );
    }

    public function test_dashboard_shows_the_balance_and_counts_only_active_members(): void
    {
        BankAccount::factory()->create(['opening_balance_in_cents' => 150_000]);
        Member::factory()->count(2)->create();
        Member::factory()->create(['deactivated_at' => now()]);

        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.balanceInCents', 150_000)
                ->where('summary.activeMembers', 2)
            );
    }

    public function test_month_totals_count_what_was_paid_this_month_and_what_is_still_due_until_its_end(): void
    {
        $this->travelTo('2026-11-15 10:00:00');

        Revenue::factory()->create(['price_in_cents' => 10_000, 'due_on' => '2026-10-20', 'status' => RevenueStatus::Paid, 'paid_at' => '2026-11-01 00:00:00']);
        Revenue::factory()->create(['price_in_cents' => 99, 'due_on' => '2026-10-20', 'status' => RevenueStatus::Paid, 'paid_at' => '2026-10-31 23:59:59']);
        Revenue::factory()->create(['price_in_cents' => 5_000, 'due_on' => '2026-11-15']);
        Revenue::factory()->create(['price_in_cents' => 3_000, 'due_on' => '2026-11-30']);
        // Overdue (left to its own block) and next month: neither is "to receive" this month.
        Revenue::factory()->create(['price_in_cents' => 99, 'due_on' => '2026-11-14']);
        Revenue::factory()->create(['price_in_cents' => 99, 'due_on' => '2026-12-01']);

        Expense::factory()->create(['amount_in_cents' => 20_000, 'status' => ExpenseStatus::Paid, 'paid_at' => '2026-11-30 23:59:59']);
        Expense::factory()->create(['amount_in_cents' => 99, 'status' => ExpenseStatus::Paid, 'paid_at' => '2026-12-01 00:00:00']);
        Expense::factory()->create(['amount_in_cents' => 7_000, 'due_on' => '2026-11-20']);
        Expense::factory()->create(['amount_in_cents' => 99, 'due_on' => '2026-11-10']);

        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.month', [
                    'receivedInCents' => 10_000,
                    'toReceiveInCents' => 8_000,
                    'paidInCents' => 20_000,
                    'toPayInCents' => 7_000,
                ])
            );
    }

    public function test_overdue_revenues_show_the_totals_and_the_five_oldest(): void
    {
        $this->travelTo('2026-11-15 10:00:00');

        $member = Member::factory()->create(['name' => 'Ana Souza']);

        foreach (range(1, 6) as $day) {
            Revenue::factory()->for($member)->create(['price_in_cents' => 1_000, 'due_on' => "2026-11-0{$day}"]);
        }

        // Due today and already paid: not overdue.
        Revenue::factory()->create(['due_on' => '2026-11-15']);
        Revenue::factory()->create(['due_on' => '2026-10-01', 'status' => RevenueStatus::Paid, 'paid_at' => now()]);

        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.overdueRevenues.count', 6)
                ->where('summary.overdueRevenues.totalInCents', 6_000)
                ->has('summary.overdueRevenues.oldest', 5)
                ->where('summary.overdueRevenues.oldest.0.member_name', 'Ana Souza')
                ->where('summary.overdueRevenues.oldest.0.member_id', $member->id)
                ->where('summary.overdueRevenues.oldest.0.due_on', '2026-11-01')
                ->where('summary.overdueRevenues.oldest.4.due_on', '2026-11-05')
            );
    }

    public function test_upcoming_expenses_list_pending_ones_due_within_seven_days_overdue_first(): void
    {
        $this->travelTo('2026-11-15 10:00:00');

        Expense::factory()->create(['description' => 'Aluguel', 'due_on' => '2026-11-10']);
        Expense::factory()->create(['description' => 'Energia', 'due_on' => '2026-11-22']);
        Expense::factory()->create(['description' => 'Água', 'due_on' => '2026-11-23']);
        Expense::factory()->paid()->create(['due_on' => '2026-11-16']);

        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('summary.upcomingExpenses', 2)
                ->where('summary.upcomingExpenses.0.description', 'Aluguel')
                ->where('summary.upcomingExpenses.0.is_overdue', true)
                ->where('summary.upcomingExpenses.1.description', 'Energia')
                ->where('summary.upcomingExpenses.1.is_overdue', false)
            );
    }
}
