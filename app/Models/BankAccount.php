<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExpenseStatus;
use App\Enums\RevenueStatus;
use Carbon\CarbonInterface;
use Database\Factories\BankAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The gym's bank account. For now the gym has a single one, found with {@see self::current()}.
 *
 * `current_balance_in_cents` is left out of the fillable list on purpose: it only changes through the payments,
 * never through a form.
 */
#[Fillable(['name', 'opening_balance_in_cents', 'opened_at'])]
class BankAccount extends Model
{
    /** @use HasFactory<BankAccountFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opening_balance_in_cents' => 'integer',
            'current_balance_in_cents' => 'integer',
            'opened_at' => 'datetime',
        ];
    }

    /**
     * The gym's bank account, or `null` while none has been registered.
     *
     * Every place that needs the account goes through here, so supporting more than one account later only
     * changes this method and its callers.
     */
    public static function current(): ?self
    {
        return static::query()->orderBy('id')->first();
    }

    /**
     * Register the gym's account, starting now with `$openingBalanceInCents` as both its opening and current balance.
     *
     * Returns `null`, creating nothing, when an account already exists: the gym has a single account for now. The
     * check and the insert run under a lock, so two requests at the same time (a double click, two open tabs) can't
     * both see "no account" and create two.
     */
    public static function open(string $name, int $openingBalanceInCents): ?self
    {
        return Cache::lock('bank-account:open', 10)->block(5, function () use ($name, $openingBalanceInCents): ?self {
            if (static::query()->exists()) {
                return null;
            }

            return static::query()->forceCreate([
                'name' => $name,
                'opening_balance_in_cents' => $openingBalanceInCents,
                'current_balance_in_cents' => $openingBalanceInCents,
                'opened_at' => now(),
            ]);
        });
    }

    /**
     * Change the name and the opening balance, moving the current balance by the same difference.
     *
     * Fixing an opening balance typed as R$ 5.000 to R$ 5.200 adds R$ 200 to the current one, since everything paid
     * since then stays the same. The row is locked while the difference is worked out, and the current balance is
     * changed by the database (`current + difference`), so a payment landing at the same time is never lost.
     */
    public function updateDetails(string $name, int $openingBalanceInCents): void
    {
        DB::transaction(function () use ($name, $openingBalanceInCents): void {
            $lockedAccount = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            $difference = $openingBalanceInCents - $lockedAccount->opening_balance_in_cents;

            static::query()->whereKey($this->getKey())->increment('current_balance_in_cents', $difference, [
                'name' => $name,
                'opening_balance_in_cents' => $openingBalanceInCents,
            ]);
        });

        $this->refresh();
    }

    /**
     * Add `$amountInCents` to the current balance, when money comes in (a revenue is paid).
     *
     * The sum is done by the database (`current + amount`) instead of read, added in PHP and saved back: two payments
     * at the same time would both read the same balance and one of them would be lost. Call it inside the same
     * transaction as the payment, so either both are saved or neither is. This instance is not refreshed.
     */
    public function credit(int $amountInCents): void
    {
        static::query()->whereKey($this->getKey())->increment('current_balance_in_cents', $amountInCents);
    }

    /**
     * Take `$amountInCents` out of the current balance, when money goes out (an expense is paid).
     *
     * Same care as {@see self::credit()}: done by the database, inside the payment's transaction. The balance may go
     * negative. This instance is not refreshed.
     */
    public function debit(int $amountInCents): void
    {
        static::query()->whereKey($this->getKey())->decrement('current_balance_in_cents', $amountInCents);
    }

    /**
     * Whether a payment made at `$paidAt` changed this account's balance, so undoing it must give the money back.
     *
     * Payments before the opening balance was informed are already inside it: undoing one of them must not touch
     * the current balance.
     */
    public function tracks(CarbonInterface $paidAt): bool
    {
        return $paidAt->greaterThanOrEqualTo($this->opened_at);
    }

    /**
     * What the current balance should be, worked out from scratch: the opening balance, plus every revenue and minus
     * every expense paid since `opened_at`.
     *
     * The current balance is kept up to date payment by payment, so the two always match unless something went
     * wrong (a bug, or a row changed straight in the database). Two sums on the `(status, paid_at)` indexes.
     */
    public function expectedBalanceInCents(): int
    {
        $revenuesInCents = (int) Revenue::query()
            ->where('status', RevenueStatus::Paid)
            ->where('paid_at', '>=', $this->opened_at)
            ->sum('price_in_cents');

        $expensesInCents = (int) Expense::query()
            ->where('status', ExpenseStatus::Paid)
            ->where('paid_at', '>=', $this->opened_at)
            ->sum('amount_in_cents');

        return $this->opening_balance_in_cents + $revenuesInCents - $expensesInCents;
    }
}
