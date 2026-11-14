<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\BankAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The current balance is not accepted here: it starts equal to the opening balance (see {@see BankAccount::open()}).
 */
class StoreBankAccountRequest extends FormRequest
{
    /**
     * Largest opening balance accepted, in cents (R$ 99.999.999,99).
     */
    public const MAX_OPENING_BALANCE_IN_CENTS = 9_999_999_999;

    /**
     * Any authenticated user can manage the bank account.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The amount arrives already in cents, so no float math happens on the server. An overdrawn opening balance
     * can't be typed in the money input, so it is not accepted either.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'opening_balance_in_cents' => ['required', 'integer', 'min:0', 'max:'.self::MAX_OPENING_BALANCE_IN_CENTS],
        ];
    }

    /**
     * Limits shown in reais, since the user never sees the value in cents.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'opening_balance_in_cents.min' => 'O saldo inicial não pode ser negativo.',
            'opening_balance_in_cents.max' => 'O saldo inicial não pode passar de R$ 99.999.999,99.',
        ];
    }
}
