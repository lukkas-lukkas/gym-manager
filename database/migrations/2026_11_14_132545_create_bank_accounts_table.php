<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The gym's bank account and its balance. For now there is only one (see `BankAccount::current()`).
     *
     * - `opening_balance_in_cents`: how much the account had at `opened_at`, typed in by the gym.
     * - `current_balance_in_cents`: the opening balance plus every revenue and minus every expense paid since
     *   `opened_at`, kept up to date in the same transaction as each payment.
     * - `opened_at`: the moment of the opening balance. Payments before it are already inside the opening balance,
     *   so they never change the current one.
     *
     * Both balances are signed: an account can be overdrawn.
     */
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->bigInteger('opening_balance_in_cents');
            $table->bigInteger('current_balance_in_cents');
            $table->timestamp('opened_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
