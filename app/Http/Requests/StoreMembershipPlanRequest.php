<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BillingPeriod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMembershipPlanRequest extends FormRequest
{
    /**
     * Any authenticated user can create membership plans.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The price arrives already in cents, so no float math happens on the server.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('membership_plans')],
            'price_in_cents' => ['required', 'integer', 'min:1', 'max:9999999'],
            'billing_period' => ['required', Rule::enum(BillingPeriod::class)],
        ];
    }

    /**
     * Price limits shown in reais, since the user never sees the value in cents.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price_in_cents.min' => 'O preço deve ser de pelo menos R$ 0,01.',
            'price_in_cents.max' => 'O preço não pode passar de R$ 99.999,99.',
        ];
    }
}
