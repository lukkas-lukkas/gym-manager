<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ExpenseStatus;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Marcar como pago" button of the expenses list. Paying through the edit form is covered by {@see ExpensePaymentTest}.
 */
class MarkExpenseAsPaidTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_pay_an_expense(): void
    {
        $expense = Expense::factory()->create();

        $this->post(route('expenses.pay', $expense))->assertRedirect(route('login'));

        $this->assertSame(ExpenseStatus::Pending, $expense->fresh()->status);
    }

    public function test_pending_expense_is_marked_as_paid_now(): void
    {
        $this->travelTo('2026-11-12 15:30:00');

        $expense = Expense::factory()->create(['due_on' => '2026-12-05']);

        $this->actingAs(User::factory()->create())
            ->post(route('expenses.pay', $expense))
            ->assertRedirect(route('expenses.index', ['month' => '2026-12']))
            ->assertInertiaFlash('success', 'Pagamento registrado.');

        $expense->refresh();
        $this->assertSame(ExpenseStatus::Paid, $expense->status);
        $this->assertSame('2026-11-12 15:30:00', $expense->paid_at->toDateTimeString());
    }

    public function test_paying_from_the_expenses_list_goes_back_to_the_same_month_and_page(): void
    {
        $expense = Expense::factory()->create();
        $expensesUrl = route('expenses.index', ['month' => '2026-11', 'page' => 2]);

        $this->actingAs(User::factory()->create())
            ->from($expensesUrl)
            ->post(route('expenses.pay', $expense))
            ->assertRedirect($expensesUrl);
    }

    public function test_paying_twice_keeps_the_first_payment_date(): void
    {
        $this->travelTo('2026-11-12 15:30:00');

        $expense = Expense::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('expenses.pay', $expense));

        $this->travelTo('2026-11-13 09:00:00');

        $this->actingAs($user)
            ->post(route('expenses.pay', $expense))
            ->assertInertiaFlash('success', 'Este pagamento já estava registrado.');

        $this->assertSame('2026-11-12 15:30:00', $expense->fresh()->paid_at->toDateTimeString());
    }

    public function test_paying_only_changes_the_given_expense(): void
    {
        $expense = Expense::factory()->create();
        $otherExpense = Expense::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('expenses.pay', $expense));

        $this->assertSame(ExpenseStatus::Pending, $otherExpense->fresh()->status);
    }

    public function test_paying_a_missing_expense_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('expenses.pay', 999))
            ->assertNotFound();
    }

    public function test_paying_takes_the_expense_amount_out_of_the_bank_account_balance(): void
    {
        $account = BankAccount::factory()->create(['opening_balance_in_cents' => 80_000, 'current_balance_in_cents' => 100_000]);
        $expense = Expense::factory()->create(['amount_in_cents' => 35_000]);

        $this->actingAs(User::factory()->create())->post(route('expenses.pay', $expense));

        $account->refresh();
        $this->assertSame(65_000, $account->current_balance_in_cents);
        $this->assertSame(80_000, $account->opening_balance_in_cents);
    }

    public function test_paying_twice_takes_the_amount_out_of_the_balance_only_once(): void
    {
        $account = BankAccount::factory()->create(['current_balance_in_cents' => 100_000]);
        $expense = Expense::factory()->create(['amount_in_cents' => 35_000]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('expenses.pay', $expense));
        $this->actingAs($user)->post(route('expenses.pay', $expense));

        $this->assertSame(65_000, $account->refresh()->current_balance_in_cents);
    }

    public function test_paying_an_expense_already_paid_through_the_edit_form_leaves_the_balance_untouched(): void
    {
        $account = BankAccount::factory()->create(['current_balance_in_cents' => 100_000]);
        $expense = Expense::factory()->paid()->create(['amount_in_cents' => 35_000]);

        $this->actingAs(User::factory()->create())
            ->post(route('expenses.pay', $expense))
            ->assertInertiaFlash('success', 'Este pagamento já estava registrado.');

        $this->assertSame(100_000, $account->refresh()->current_balance_in_cents);
    }

    public function test_paying_without_a_bank_account_still_registers_the_payment(): void
    {
        $expense = Expense::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('expenses.pay', $expense))
            ->assertInertiaFlash('success', 'Pagamento registrado.');

        $this->assertSame(ExpenseStatus::Paid, $expense->fresh()->status);
        $this->assertDatabaseCount('bank_accounts', 0);
    }

    public function test_expense_stays_pending_when_the_balance_cannot_be_updated(): void
    {
        // The smallest value a signed BIGINT holds: taking out even one cent makes the database reject the update.
        $account = BankAccount::factory()->create(['current_balance_in_cents' => PHP_INT_MIN]);
        $expense = Expense::factory()->create(['amount_in_cents' => 1]);

        $this->actingAs(User::factory()->create())
            ->post(route('expenses.pay', $expense))
            ->assertServerError();

        $expense->refresh();
        $this->assertSame(ExpenseStatus::Pending, $expense->status);
        $this->assertNull($expense->paid_at);
        $this->assertSame(PHP_INT_MIN, $account->refresh()->current_balance_in_cents);
    }
}
