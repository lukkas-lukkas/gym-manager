<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ExpenseStatus;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use LogicException;
use Tests\TestCase;

/**
 * How paying an expense, or undoing its payment, moves the bank account balance (through the expense edit form).
 */
class ExpensePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_paying_an_expense_takes_its_amount_out_of_the_balance(): void
    {
        $account = BankAccount::factory()->create(['opening_balance_in_cents' => 80_000, 'current_balance_in_cents' => 100_000]);
        $expense = Expense::factory()->create(['amount_in_cents' => 35_000]);

        $this->updateExpense($expense, ['status' => 'paid'])
            ->assertRedirect()
            ->assertInertiaFlash('success', 'Despesa atualizada com sucesso.');

        $account->refresh();
        $this->assertSame(65_000, $account->current_balance_in_cents);
        $this->assertSame(80_000, $account->opening_balance_in_cents);
    }

    public function test_paying_with_a_new_amount_takes_the_new_amount_out_of_the_balance(): void
    {
        $account = BankAccount::factory()->create(['current_balance_in_cents' => 100_000]);
        $expense = Expense::factory()->create(['amount_in_cents' => 35_000]);

        $this->updateExpense($expense, ['amount_in_cents' => '40000', 'status' => 'paid']);

        $this->assertSame(60_000, $account->refresh()->current_balance_in_cents);
    }

    public function test_saving_a_paid_expense_twice_takes_the_money_out_only_once(): void
    {
        $account = BankAccount::factory()->create(['current_balance_in_cents' => 100_000]);
        $expense = Expense::factory()->create(['amount_in_cents' => 35_000]);

        $this->updateExpense($expense, ['status' => 'paid']);
        $this->updateExpense($expense, ['status' => 'paid']);

        $this->assertSame(65_000, $account->refresh()->current_balance_in_cents);
    }

    public function test_setting_a_paid_expense_back_to_pending_gives_the_money_back(): void
    {
        $account = BankAccount::factory()->create(['current_balance_in_cents' => 65_000, 'opened_at' => '2026-11-01 08:00:00']);
        $expense = Expense::factory()->paid()->create(['amount_in_cents' => 35_000, 'paid_at' => '2026-11-10 14:00:00']);

        $this->updateExpense($expense, ['status' => 'pending']);

        $this->assertSame(100_000, $account->refresh()->current_balance_in_cents);
        $this->assertNull($expense->fresh()->paid_at);
    }

    public function test_undoing_a_payment_made_before_the_opening_balance_leaves_the_balance_untouched(): void
    {
        $account = BankAccount::factory()->create(['current_balance_in_cents' => 100_000, 'opened_at' => '2026-11-01 08:00:00']);
        $expense = Expense::factory()->paid()->create(['amount_in_cents' => 35_000, 'paid_at' => '2026-10-31 18:00:00']);

        $this->updateExpense($expense, ['status' => 'pending']);

        $this->assertSame(100_000, $account->refresh()->current_balance_in_cents);
        $this->assertSame(ExpenseStatus::Pending, $expense->fresh()->status);
    }

    public function test_editing_a_paid_expense_without_changing_the_amount_leaves_the_balance_untouched(): void
    {
        $account = BankAccount::factory()->create(['current_balance_in_cents' => 100_000]);
        $expense = Expense::factory()->paid()->create(['amount_in_cents' => 35_000]);

        $this->updateExpense($expense, ['description' => 'Novo nome', 'due_on' => '2026-12-10', 'status' => 'paid']);

        $this->assertSame(100_000, $account->refresh()->current_balance_in_cents);
        $this->assertSame('Novo nome', $expense->fresh()->description);
    }

    public function test_editing_a_pending_expense_leaves_the_balance_untouched(): void
    {
        $account = BankAccount::factory()->create(['current_balance_in_cents' => 100_000]);
        $expense = Expense::factory()->create(['amount_in_cents' => 35_000]);

        $this->updateExpense($expense, ['amount_in_cents' => '50000', 'status' => 'pending']);

        $this->assertSame(100_000, $account->refresh()->current_balance_in_cents);
        $this->assertSame(50_000, $expense->fresh()->amount_in_cents);
    }

    public function test_changing_the_amount_of_an_expense_that_stays_paid_is_refused(): void
    {
        $account = BankAccount::factory()->create(['current_balance_in_cents' => 100_000]);
        $expense = Expense::factory()->paid()->create(['description' => 'Aluguel', 'amount_in_cents' => 35_000]);

        $this->updateExpense($expense, ['description' => 'Aluguel novo', 'amount_in_cents' => '40000', 'status' => 'paid'])
            ->assertSessionHasErrors(['amount_in_cents' => 'Esta despesa já foi paga. Para mudar o valor, salve-a primeiro como pendente.']);

        $expense->refresh();
        $this->assertSame('Aluguel', $expense->description);
        $this->assertSame(35_000, $expense->amount_in_cents);
        $this->assertSame(100_000, $account->refresh()->current_balance_in_cents);
    }

    public function test_paying_can_leave_the_balance_negative(): void
    {
        $account = BankAccount::factory()->create(['current_balance_in_cents' => 10_000]);
        $expense = Expense::factory()->create(['amount_in_cents' => 35_000]);

        $this->updateExpense($expense, ['status' => 'paid']);

        $this->assertSame(-25_000, $account->refresh()->current_balance_in_cents);
    }

    public function test_paying_without_a_bank_account_still_saves_the_expense(): void
    {
        $expense = Expense::factory()->create();

        $this->updateExpense($expense, ['status' => 'paid'])
            ->assertInertiaFlash('success', 'Despesa atualizada com sucesso.');

        $this->assertSame(ExpenseStatus::Paid, $expense->fresh()->status);
        $this->assertDatabaseCount('bank_accounts', 0);
    }

    public function test_expense_stays_pending_when_the_balance_cannot_be_updated(): void
    {
        // The smallest value a signed BIGINT holds: taking out even one cent makes the database reject the update.
        $account = BankAccount::factory()->create(['current_balance_in_cents' => PHP_INT_MIN]);
        $expense = Expense::factory()->create(['amount_in_cents' => 1]);

        $this->updateExpense($expense, ['status' => 'paid'])
            ->assertServerError();

        $expense->refresh();
        $this->assertSame(ExpenseStatus::Pending, $expense->status);
        $this->assertNull($expense->paid_at);
        $this->assertSame(PHP_INT_MIN, $account->refresh()->current_balance_in_cents);
    }

    public function test_apply_changes_refuses_a_new_amount_for_an_expense_that_stays_paid(): void
    {
        $account = BankAccount::factory()->create(['current_balance_in_cents' => 100_000]);
        $expense = Expense::factory()->paid()->create(['amount_in_cents' => 35_000]);

        try {
            $expense->applyChanges($expense->description, 40_000, $expense->due_on->toDateString(), ExpenseStatus::Paid);
            $this->fail('A new amount for a paid expense should be refused.');
        } catch (LogicException) {
            $this->assertSame(35_000, $expense->fresh()->amount_in_cents);
            $this->assertSame(100_000, $account->refresh()->current_balance_in_cents);
        }
    }

    /**
     * Send the edit form with the expense's current values, replaced by `$changes`.
     *
     * @param  array<string, string>  $changes
     */
    private function updateExpense(Expense $expense, array $changes): TestResponse
    {
        return $this->actingAs(User::factory()->create())
            ->put(route('expenses.update', $expense), [
                'description' => $expense->description,
                'amount_in_cents' => (string) $expense->amount_in_cents,
                'due_on' => $expense->due_on->toDateString(),
                'status' => $expense->status->value,
                ...$changes,
            ]);
    }
}
