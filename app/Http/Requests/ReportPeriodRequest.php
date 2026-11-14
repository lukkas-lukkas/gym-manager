<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Reports\ReportPeriod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The period of the reports page: `?from=YYYY-MM&to=YYYY-MM`, both months included.
 *
 * Both are optional, but they come together: with neither, the default period is used (see {@see ReportPeriod::default()}).
 */
class ReportPeriodRequest extends FormRequest
{
    /**
     * Any authenticated user can see the reports.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'required_with:to', 'date_format:Y-m'],
            'to' => ['nullable', 'required_with:from', 'date_format:Y-m', 'after_or_equal:from'],
        ];
    }

    /**
     * Only checks the size of the period once both months are valid, so the user sees one error at a time.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->filled('from')) {
                    return;
                }

                if ($this->period()->monthCount() > ReportPeriod::MAX_MONTHS) {
                    $validator->errors()->add('to', 'O período pode ter no máximo '.ReportPeriod::MAX_MONTHS.' meses.');
                }
            },
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
            'to.after_or_equal' => 'O mês final deve ser igual ou posterior ao mês inicial.',
        ];
    }

    /**
     * The period asked for, or the default one when no months were sent.
     */
    public function period(): ReportPeriod
    {
        if (! $this->filled('from')) {
            return ReportPeriod::default();
        }

        return ReportPeriod::fromMonths($this->string('from')->value(), $this->string('to')->value());
    }
}
