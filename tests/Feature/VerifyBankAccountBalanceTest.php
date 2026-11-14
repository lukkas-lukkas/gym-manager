<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ExpenseStatus;
use App\Enums\RevenueStatus;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\Revenue;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class VerifyBankAccountBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_succeeds_when_no_account_is_registered(): void
    {
        $this->artisan('bank-accounts:verify')
            ->expectsOutput('No bank account registered yet. Nothing to verify.')
            ->assertSuccessful();
    }

    public function test_balance_matches_the_opening_balance_plus_revenues_minus_expenses_paid_since_then(): void
    {
        $this->createAccountAndPayments(currentBalanceInCents: 77_000);

        $this->artisan('bank-accounts:verify')
            ->expectsOutput('Balance matches: 77000 cents.')
            ->assertSuccessful();
    }

    public function test_mismatch_is_logged_and_the_command_fails(): void
    {
        Log::spy();
        $account = $this->createAccountAndPayments(currentBalanceInCents: 80_000);

        $this->artisan('bank-accounts:verify')
            ->expectsOutput('Balance mismatch: stored 80000 cents, expected 77000 cents (difference 3000 cents).')
            ->assertFailed();

        Log::shouldHaveReceived('error')->once()->with('Bank account balance does not match its payments.', [
            'bank_account_id' => $account->id,
            'stored_in_cents' => 80_000,
            'expected_in_cents' => 77_000,
            'difference_in_cents' => 3_000,
        ]);
    }

    public function test_mismatch_is_reported_but_never_fixed(): void
    {
        $account = $this->createAccountAndPayments(currentBalanceInCents: 80_000);

        $this->artisan('bank-accounts:verify')->assertFailed();

        $this->assertSame(80_000, $account->refresh()->current_balance_in_cents);
    }

    public function test_balance_kept_by_the_payment_flows_always_matches(): void
    {
        $this->travelTo('2026-11-01 08:00:00');
        BankAccount::factory()->create(['opening_balance_in_cents' => 100_000]);

        $this->travelTo('2026-11-10 10:00:00');
        Revenue::factory()->create(['price_in_cents' => 12_000])->markAsPaid();
        $expense = Expense::factory()->create(['amount_in_cents' => 35_000]);
        $expense->applyChanges($expense->description, 35_000, $expense->due_on->toDateString(), ExpenseStatus::Paid);
        $refundedExpense = Expense::factory()->create(['amount_in_cents' => 9_000]);
        $refundedExpense->applyChanges($refundedExpense->description, 9_000, $refundedExpense->due_on->toDateString(), ExpenseStatus::Paid);
        $refundedExpense->applyChanges($refundedExpense->description, 9_000, $refundedExpense->due_on->toDateString(), ExpenseStatus::Pending);

        $this->artisan('bank-accounts:verify')
            ->expectsOutput('Balance matches: 77000 cents.')
            ->assertSuccessful();
    }

    public function test_command_is_scheduled_daily_at_3am_in_sao_paulo_on_one_server(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains($event->command, 'bank-accounts:verify'));

        $this->assertNotNull($event);
        $this->assertSame('0 3 * * *', $event->expression);
        $this->assertSame('America/Sao_Paulo', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }

    /**
     * An account opened on 01/11 with R$ 1.000,00, a revenue of R$ 120,00 and an expense of R$ 350,00 paid since then
     * (expected balance R$ 770,00), and payments that must not count: before the opening, or still pending.
     */
    private function createAccountAndPayments(int $currentBalanceInCents): BankAccount
    {
        $account = BankAccount::factory()->create([
            'opening_balance_in_cents' => 100_000,
            'current_balance_in_cents' => $currentBalanceInCents,
            'opened_at' => '2026-11-01 08:00:00',
        ]);

        Revenue::factory()->create(['price_in_cents' => 12_000, 'status' => RevenueStatus::Paid, 'paid_at' => '2026-11-01 08:00:00']);
        Revenue::factory()->create(['price_in_cents' => 50_000, 'status' => RevenueStatus::Paid, 'paid_at' => '2026-11-01 07:59:59']);
        Revenue::factory()->create(['price_in_cents' => 70_000, 'status' => RevenueStatus::Pending]);

        Expense::factory()->create(['amount_in_cents' => 35_000, 'status' => ExpenseStatus::Paid, 'paid_at' => '2026-11-05 10:00:00']);
        Expense::factory()->create(['amount_in_cents' => 20_000, 'status' => ExpenseStatus::Paid, 'paid_at' => '2026-10-31 18:00:00']);
        Expense::factory()->create(['amount_in_cents' => 90_000, 'status' => ExpenseStatus::Pending]);

        return $account;
    }
}
