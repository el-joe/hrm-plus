<?php

namespace App\Http\Requests\Admin\Disciplinary;

use App\Enums\DisciplinaryActionType;
use App\Models\DisciplinaryCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Propose the outcome of a case (disciplinary.update on the case). Type-specific
 * rules (suspension dates, deduction amount, justification below the escalation
 * suggestion) are enforced by App\Actions\Disciplinary\ManageDisciplinaryAction.
 */
class ProposeActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $case = $this->route('disciplinary_case');

        return $case instanceof DisciplinaryCase && $this->user()->can('update', $case);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(DisciplinaryActionType::class)],
            'details' => ['required', 'string', 'max:5000'],
            'effective_date' => ['required', 'date'],
            'justification' => ['nullable', 'string', 'max:2000'],
            'suspension_start' => ['nullable', 'required_if:type,suspension', 'date'],
            'suspension_end' => ['nullable', 'required_if:type,suspension', 'date', 'after_or_equal:suspension_start'],
            'suspension_paid' => ['nullable', 'boolean'],
            'deduction_amount' => ['nullable', 'required_if:type,salary_deduction', 'decimal:0,2', 'gt:0', 'max:9999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('disciplinary.fields');
    }
}
