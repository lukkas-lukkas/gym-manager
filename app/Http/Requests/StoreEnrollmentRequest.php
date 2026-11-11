<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Member;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Only the plan is chosen by the user. The dates are calculated on the server
 * (see {@see Member::enroll()}), so they cannot be tampered with.
 */
class StoreEnrollmentRequest extends FormRequest
{
    /**
     * Any authenticated user can enroll members.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'membership_plan_id' => [
                'required',
                'integer',
                Rule::exists('membership_plans', 'id')->whereNull('deactivated_at'),
            ],
        ];
    }

    /**
     * Messages for rules whose default text would not make sense to the user.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'membership_plan_id.required' => 'Escolha um plano.',
            'membership_plan_id.exists' => 'Escolha um plano ativo.',
        ];
    }
}
