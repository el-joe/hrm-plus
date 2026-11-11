<?php

namespace App\Http\Requests\Admin\Lifecycle;

use App\Enums\TerminationType;
use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Rules\ExistsInScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Open (employee_id required) or edit an offboarding case.
 */
class OffboardingCaseRequest extends FormRequest
{
    public function case(): ?OffboardingCase
    {
        $case = $this->route('offboarding');

        return $case instanceof OffboardingCase ? $case : null;
    }

    public function authorize(): bool
    {
        return $this->case() ? $this->user()->can('update', $this->case()) : $this->user()->can('create', OffboardingCase::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => $this->case() ? ['exclude'] : ['required', 'integer', new ExistsInScope(Employee::class, activeOnly: false)],
            'type' => ['required', Rule::enum(TerminationType::class)],
            'notice_date' => ['nullable', 'date', 'before_or_equal:today'],
            'last_working_date' => ['required', 'date', 'after:-1 year', 'before_or_equal:+1 year'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'eligible_for_rehire' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [...$this->validated(), 'eligible_for_rehire' => $this->boolean('eligible_for_rehire')];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('onboarding.fields');
    }
}
