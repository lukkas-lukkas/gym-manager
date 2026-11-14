<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BankAccount;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Signature('bank-accounts:verify')]
#[Description('Check that the bank account balance matches its opening balance plus the payments since then')]
class VerifyBankAccountBalance extends Command
{
    /**
     * Execute the console command.
     *
     * The stored balance is compared with the one worked out from the payments (see
     * {@see BankAccount::expectedBalanceInCents()}). Nothing is fixed: a difference means something went wrong, and
     * someone has to find out what before the balance is touched. It is logged as an error and the command fails, so
     * the scheduler's failure hooks can warn someone.
     *
     * The account row is locked while both numbers are read, so a payment landing in between can't make a correct
     * balance look wrong. Payments wait for a few milliseconds at most.
     */
    public function handle(): int
    {
        $balances = DB::transaction(function (): ?array {
            $accountId = BankAccount::current()?->getKey();

            if ($accountId === null) {
                return null;
            }

            $account = BankAccount::query()->whereKey($accountId)->lockForUpdate()->firstOrFail();

            return [
                'bank_account_id' => $account->getKey(),
                'stored_in_cents' => $account->current_balance_in_cents,
                'expected_in_cents' => $account->expectedBalanceInCents(),
            ];
        });

        if ($balances === null) {
            $this->info('No bank account registered yet. Nothing to verify.');

            return self::SUCCESS;
        }

        if ($balances['stored_in_cents'] === $balances['expected_in_cents']) {
            $this->info("Balance matches: {$balances['stored_in_cents']} cents.");

            return self::SUCCESS;
        }

        $differenceInCents = $balances['stored_in_cents'] - $balances['expected_in_cents'];

        Log::error('Bank account balance does not match its payments.', [...$balances, 'difference_in_cents' => $differenceInCents]);

        $this->error("Balance mismatch: stored {$balances['stored_in_cents']} cents, expected {$balances['expected_in_cents']} cents (difference {$differenceInCents} cents).");

        return self::FAILURE;
    }
}
