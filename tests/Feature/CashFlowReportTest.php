<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Enums\EnrollmentStatus;
use App\Enums\ExpenseStatus;
use App\Enums\RevenueStatus;
use App\Models\BankAccount;
use App\Models\Enrollment;
use App\Models\Expense;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Revenue;
use App\Models\User;
use App\Reports\CashFlowReport;
use App\Reports\ReportPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Every test runs on 15/11/2026, with the payments below (see {@see self::createPayments()}):
 *
 * | Month    | Paid in | Paid out | Still to receive | Still to pay           |
 * |----------|---------|----------|------------------|------------------------|
 * | Sep/2026 | 600,00 (500,00 before the account) | — | — | —            |
 * | Oct/2026 | 200,00  | 80,00    | —                | —                      |
 * | Nov/2026 | 300,00  | 50,00    | 40,00 (70,00 overdue, left out) | 20,00 + 30,00 overdue |
 * | Dec/2026 | —       | —        | 90,00            | —                      |
 * | Jan/2027 | —       | —        | —                | 60,00                  |
 */
class CashFlowReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-15 12:00:00');
    }

    public function test_bars_show_paid_amounts_in_the_past_and_due_amounts_from_the_current_month_on(): void
    {
        $this->createAccount();
        $this->createPayments();

        $cashFlow = $this->cashFlow('2026-08', '2027-01');

        $this->assertSame('2026-11', $cashFlow['currentMonth']);
        $this->assertSame([0, 60_000, 20_000, 34_000, 9_000, 0], $cashFlow['inflow']);
        $this->assertSame([0, 0, 8_000, 10_000, 0, 6_000], $cashFlow['outflow']);
    }

    public function test_balance_is_real_up_to_last_month_and_forecast_from_the_current_month_on(): void
    {
        $this->createAccount();
        $this->createPayments();

        $cashFlow = $this->cashFlow('2026-08', '2027-01');

        $this->assertSame(147_000, $cashFlow['currentBalance']);
        // August is before the opening balance (10/09): unknown.
        $this->assertSame([null, 110_000, 122_000, null, null, null], $cashFlow['realizedBalance']);
        // Repeats the end of October, so the lines join; then 1.470 + 40 − 20 − 30 = 1.460, + 90 = 1.550, − 60 = 1.490.
        $this->assertSame([null, null, 122_000, 146_000, 155_000, 149_000], $cashFlow['forecastBalance']);
    }

    public function test_balance_of_a_past_period_is_worked_out_back_from_today(): void
    {
        $this->createAccount();
        $this->createPayments();

        $cashFlow = $this->cashFlow('2026-08', '2026-09');

        $this->assertSame([null, 110_000], $cashFlow['realizedBalance']);
        $this->assertSame([null, null], $cashFlow['forecastBalance']);
        $this->assertSame([0, 60_000], $cashFlow['inflow']);
    }

    public function test_balance_of_a_future_period_starts_from_today(): void
    {
        $this->createAccount();
        $this->createPayments();

        $cashFlow = $this->cashFlow('2026-12', '2027-01');

        $this->assertSame([null, null], $cashFlow['realizedBalance']);
        $this->assertSame([155_000, 149_000], $cashFlow['forecastBalance']);
        $this->assertSame([9_000, 0], $cashFlow['inflow']);
        $this->assertSame([0, 6_000], $cashFlow['outflow']);
    }

    public function test_without_an_account_the_bars_are_shown_without_a_balance(): void
    {
        $this->createPayments();

        $cashFlow = $this->cashFlow('2026-10', '2026-12');

        $this->assertNull($cashFlow['currentBalance']);
        $this->assertSame([20_000, 34_000, 9_000], $cashFlow['inflow']);
        $this->assertSame([null, null, null], $cashFlow['realizedBalance']);
        $this->assertSame([null, null, null], $cashFlow['forecastBalance']);
    }

    public function test_renewals_count_in_the_inflow_and_in_the_forecast_balance(): void
    {
        $this->createAccount();
        $this->createPayments();
        $plan = MembershipPlan::factory()->create(['price_in_cents' => 10_000, 'billing_period' => BillingPeriod::Monthly]);
        Enrollment::factory()->for(Member::factory())->for($plan)->create(['starts_on' => '2026-11-01', 'ends_on' => '2026-12-01']);

        $cashFlow = $this->cashFlow('2026-08', '2027-01');

        // Renewals due on 02/12 and 03/01, R$ 100,00 each: in the inflow of their month, and carried on in the balance.
        $this->assertSame([0, 60_000, 20_000, 34_000, 19_000, 10_000], $cashFlow['inflow']);
        $this->assertSame([null, null, 122_000, 146_000, 165_000, 169_000], $cashFlow['forecastBalance']);
    }

    public function test_renewals_before_a_future_period_still_count_in_its_balance(): void
    {
        $this->createAccount();
        $this->createPayments();
        $plan = MembershipPlan::factory()->create(['price_in_cents' => 10_000, 'billing_period' => BillingPeriod::Monthly]);
        Enrollment::factory()->for(Member::factory())->for($plan)->create(['starts_on' => '2026-11-01', 'ends_on' => '2026-12-01']);

        $cashFlow = $this->cashFlow('2027-01', '2027-01');

        // The December renewal happens before the period, but the January balance still has it.
        $this->assertSame([169_000], $cashFlow['forecastBalance']);
    }

    public function test_account_opened_this_month_has_no_real_balance_yet(): void
    {
        BankAccount::factory()->create(['current_balance_in_cents' => 50_000, 'opened_at' => '2026-11-03 09:00:00']);

        $cashFlow = $this->cashFlow('2026-10', '2026-11');

        $this->assertSame([null, null], $cashFlow['realizedBalance']);
        $this->assertSame([null, 50_000], $cashFlow['forecastBalance']);
    }

    public function test_reports_page_loads_the_cash_flow_as_a_deferred_prop(): void
    {
        $this->createAccount();
        $this->createPayments();

        $this->actingAs(User::factory()->create())
            ->get(route('reports.index', ['from' => '2026-10', 'to' => '2026-12']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('cashFlow')
                ->loadDeferredProps('cashFlow', fn (Assert $reload) => $reload
                    ->where('cashFlow.currentBalance', 147_000)
                    ->where('cashFlow.inflow', [20_000, 34_000, 9_000])
                )
            );
    }

    /**
     * @return array{currentMonth: string, currentBalance: int|null, inflow: list<int>, outflow: list<int>, realizedBalance: list<int|null>, forecastBalance: list<int|null>}
     */
    private function cashFlow(string $firstMonth, string $lastMonth): array
    {
        return (new CashFlowReport(ReportPeriod::fromMonths($firstMonth, $lastMonth)))->toArray();
    }

    /**
     * Opened on 10/09 with R$ 1.000,00. Since then: + 100 + 200 + 300 − 80 − 50 = R$ 1.470,00 today.
     */
    private function createAccount(): BankAccount
    {
        return BankAccount::factory()->create([
            'opening_balance_in_cents' => 100_000,
            'current_balance_in_cents' => 147_000,
            'opened_at' => '2026-09-10 08:00:00',
        ]);
    }

    private function createPayments(): void
    {
        Revenue::factory()->create(['price_in_cents' => 50_000, 'status' => RevenueStatus::Paid, 'paid_at' => '2026-09-05 10:00:00']);
        Revenue::factory()->create(['price_in_cents' => 10_000, 'status' => RevenueStatus::Paid, 'paid_at' => '2026-09-20 10:00:00']);
        Revenue::factory()->create(['price_in_cents' => 20_000, 'status' => RevenueStatus::Paid, 'paid_at' => '2026-10-15 10:00:00']);
        Revenue::factory()->create(['price_in_cents' => 30_000, 'status' => RevenueStatus::Paid, 'paid_at' => '2026-11-05 10:00:00']);
        Revenue::factory()->create(['price_in_cents' => 4_000, 'due_on' => '2026-11-20']);
        Revenue::factory()->create(['price_in_cents' => 7_000, 'due_on' => '2026-11-10']);
        Revenue::factory()->create(['price_in_cents' => 9_000, 'due_on' => '2026-12-05']);

        Expense::factory()->create(['amount_in_cents' => 8_000, 'status' => ExpenseStatus::Paid, 'paid_at' => '2026-10-20 10:00:00']);
        Expense::factory()->create(['amount_in_cents' => 5_000, 'status' => ExpenseStatus::Paid, 'paid_at' => '2026-11-02 10:00:00']);
        Expense::factory()->create(['amount_in_cents' => 3_000, 'due_on' => '2026-11-01']);
        Expense::factory()->create(['amount_in_cents' => 2_000, 'due_on' => '2026-11-25']);
        Expense::factory()->create(['amount_in_cents' => 6_000, 'due_on' => '2027-01-10']);

        // Each sample revenue comes with an active enrollment of a random plan: close them all, so the forecast only
        // has the renewals a test creates on purpose.
        Enrollment::query()->update(['status' => EnrollmentStatus::Inactive]);
    }
}
