<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends StoreMemberRequest
{
    /**
     * Same rules as registration, but the e-mail may stay the one the member already has.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('members')->ignore($this->route('member'))],
        ];
    }
}
