<?php

namespace App\Http\Requests\Admin\Scheduling;

use App\Enums\AttendanceScope;
use Illuminate\Foundation\Http\FormRequest;

class PolicyAssignmentRequest extends FormRequest
{
    use ScopeTargetRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('attendance_policy'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->scopeRules(AttendanceScope::forPolicies()),
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function assignmentData(): array
    {
        return [
            'scope_type' => $this->scopeType(),
            'scope_id' => $this->scopeId(),
            'effective_from' => $this->input('effective_from'),
            'effective_to' => $this->input('effective_to') ?: null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [...trans('schedules.fields'), ...collect(AttendanceScope::cases())->mapWithKeys(fn ($scope) => ['scope_ids.'.$scope->value => __('schedules.fields.scope_id')])->all()];
    }
}
