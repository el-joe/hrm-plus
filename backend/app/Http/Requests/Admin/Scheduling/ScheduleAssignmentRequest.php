<?php

namespace App\Http\Requests\Admin\Scheduling;

use App\Enums\AttendanceScope;
use App\Models\ScheduleAssignment;
use App\Models\WorkSchedule;
use App\Rules\ExistsInScope;
use Illuminate\Foundation\Http\FormRequest;

class ScheduleAssignmentRequest extends FormRequest
{
    use ScopeTargetRules;

    public function authorize(): bool
    {
        $assignment = $this->route('schedule_assignment');

        return $assignment instanceof ScheduleAssignment
            ? $this->user()->can('update', $assignment)
            : $this->user()->can('create', ScheduleAssignment::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $assignment = $this->route('schedule_assignment');
        $keep = $assignment instanceof ScheduleAssignment ? $assignment : null;

        return [
            ...$this->scopeRules(AttendanceScope::forSchedules(), $keep?->scope_type?->value === $this->input('scope_type') ? $keep?->scope_id : null),
            'work_schedule_id' => ['required', 'integer', new ExistsInScope(WorkSchedule::class, keep: $keep?->work_schedule_id)],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function assignmentData(): array
    {
        return [
            'work_schedule_id' => (int) $this->input('work_schedule_id'),
            'scope_type' => $this->scopeType(),
            'scope_id' => $this->scopeId(),
            'effective_from' => $this->input('effective_from'),
            'effective_to' => $this->input('effective_to') ?: null,
            'note' => $this->input('note'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [...trans('schedules.fields'), 'scope_ids.employee' => __('schedules.fields.scope_id'), 'scope_ids.employee_group' => __('schedules.fields.scope_id'),
            'scope_ids.department' => __('schedules.fields.scope_id'), 'scope_ids.branch' => __('schedules.fields.scope_id'), 'scope_ids.employment_type' => __('schedules.fields.scope_id')];
    }
}
