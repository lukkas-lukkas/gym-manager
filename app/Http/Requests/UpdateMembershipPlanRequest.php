<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Only the name of a plan can be edited.
 *
 * Price and billing period are what members agreed to pay when they joined the plan.
 * Changing them in place would silently change what existing members owe and rewrite
 * the history used by financial reports. To offer a different price or period, create
 * a new plan (and deactivate the old one when it should stop being sold).
 *
 * Any other field sent in the request is ignored, since only validated data is saved.
 */
class UpdateMembershipPlanRequest extends FormRequest
{
    /**
     * Any authenticated user can rename membership plans.
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
            'name' => ['required', 'string', 'max:255', Rule::unique('membership_plans')->ignore($this->route('membership_plan'))],
        ];
    }
}
