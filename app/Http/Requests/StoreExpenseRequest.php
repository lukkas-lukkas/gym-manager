<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Expense;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The status is not accepted here: every new expense starts as pending (see {@see Expense::createMonthly()}).
 */
class StoreExpenseRequest extends FormRequest
{
    /**
     * Largest number of months an expense can be repeated in one request.
     */
    public const MAX_MONTHS = 24;

    /**
     * Any authenticated user can create expenses.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The amount arrives already in cents, so no float math happens on the server.
     * `months` is the total number of expenses created (1 means no repetition).
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'amount_in_cents' => ['required', 'integer', 'min:1', 'max:9999999'],
            'due_on' => ['required', 'date_format:Y-m-d'],
            'months' => ['required', 'integer', 'min:1', 'max:'.self::MAX_MONTHS],
        ];
    }

    /**
     * Limits shown in terms the user sees on the page.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount_in_cents.min' => 'O valor deve ser de pelo menos R$ 0,01.',
            'amount_in_cents.max' => 'O valor não pode passar de R$ 99.999,99.',
            'months.min' => 'Repita por pelo menos 1 mês.',
            'months.max' => 'Repita por no máximo '.self::MAX_MONTHS.' meses.',
        ];
    }
}
