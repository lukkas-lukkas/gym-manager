<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\BankAccount;

/**
 * Same fields and limits as when the account is registered: the name and the opening balance.
 * Changing the opening balance moves the current one by the same difference (see {@see BankAccount::updateDetails()}).
 */
class UpdateBankAccountRequest extends StoreBankAccountRequest {}
