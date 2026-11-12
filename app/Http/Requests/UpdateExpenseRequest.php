<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ExpenseStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edits a single expense: description, amount, due date and status.
 *
 * Any other field sent in the request is ignored, since only validated data is saved.
 */
class UpdateExpenseRequest extends FormRequest
{
    /**
     * Any authenticated user can edit expenses.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The amount arrives already in cents, so no float math happens on the server.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'amount_in_cents' => ['required', 'integer', 'min:1', 'max:9999999'],
            'due_on' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', Rule::enum(ExpenseStatus::class)],
        ];
    }

    /**
     * Amount limits shown in reais, since the user never sees the value in cents.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount_in_cents.min' => 'O valor deve ser de pelo menos R$ 0,01.',
            'amount_in_cents.max' => 'O valor não pode passar de R$ 99.999,99.',
        ];
    }
}
