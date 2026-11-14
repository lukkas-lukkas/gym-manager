<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExpenseStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

#[Fillable(['description', 'amount_in_cents', 'due_on', 'status', 'paid_at'])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_in_cents' => 'integer',
            'due_on' => 'date:Y-m-d',
            'status' => ExpenseStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Only expenses due within the month of the given date.
     *
     * Compares the plain `due_on` column with a range, so the `due_on` index can be used.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function dueIn(Builder $query, CarbonImmutable $month): void
    {
        $query->whereBetween('due_on', [
            $month->startOfMonth()->toDateString(),
            $month->endOfMonth()->toDateString(),
        ]);
    }

    /**
     * Create the same expense once a month, starting on `$firstDueOn`. All of them start as pending.
     *
     * Each due date is counted from the first one (not from the previous month), so an expense due on
     * Jan 31 falls on Feb 28 and goes back to Mar 31. Everything is saved in a single insert inside a
     * transaction: either all months are created or none.
     */
    public static function createMonthly(string $description, int $amountInCents, CarbonImmutable $firstDueOn, int $months): void
    {
        $now = now();

        $rows = array_map(fn (int $month): array => [
            'description' => $description,
            'amount_in_cents' => $amountInCents,
            'due_on' => $firstDueOn->addMonthsNoOverflow($month)->toDateString(),
            'status' => ExpenseStatus::Pending->value,
            'created_at' => $now,
            'updated_at' => $now,
        ], range(0, $months - 1));

        DB::transaction(fn () => static::query()->insert($rows));
    }

    /**
     * Save the changes made in the edit form and keep the bank account balance in step with the status.
     *
     * | Status change            | Bank account balance                                                  |
     * |--------------------------|-----------------------------------------------------------------------|
     * | pending → paid           | the (new) amount goes out                                              |
     * | paid → pending           | the old amount comes back, if that payment had changed the balance     |
     * | paid → paid, same amount | untouched (only description or due date changed)                       |
     * | pending → pending        | untouched                                                              |
     *
     * Changing the amount of an expense that stays paid is refused: the form asks to set it back to pending first,
     * which gives the money back, so the balance never drifts. The expense row is locked for the whole transaction,
     * so two tabs saving at the same time can't take the money out twice. `paid_at` follows the status: becoming paid
     * stamps the current time, staying paid keeps the original time, and going back to pending clears it.
     *
     * @throws \LogicException when the amount of an expense that stays paid would change.
     */
    public function applyChanges(string $description, int $amountInCents, string $dueOn, ExpenseStatus $status): void
    {
        DB::transaction(function () use ($description, $amountInCents, $dueOn, $status): void {
            $lockedExpense = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            $wasPaid = $lockedExpense->status === ExpenseStatus::Paid;
            $isPaid = $status === ExpenseStatus::Paid;
            $previousAmountInCents = $lockedExpense->amount_in_cents;
            $previousPaidAt = $lockedExpense->paid_at;

            if ($wasPaid && $isPaid && $amountInCents !== $previousAmountInCents) {
                throw new \LogicException('The amount of a paid expense can only change after setting it back to pending.');
            }

            $lockedExpense->update([
                'description' => $description,
                'amount_in_cents' => $amountInCents,
                'due_on' => $dueOn,
                'status' => $status,
                'paid_at' => $isPaid ? ($previousPaidAt ?? now()) : null,
            ]);

            $account = BankAccount::current();

            if ($account === null) {
                return;
            }

            if (! $wasPaid && $isPaid) {
                $account->debit($amountInCents);
            }

            if ($wasPaid && ! $isPaid && $account->tracks($previousPaidAt)) {
                $account->credit($previousAmountInCents);
            }
        });

        $this->refresh();
    }
}
