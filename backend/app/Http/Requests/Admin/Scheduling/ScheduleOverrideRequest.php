<?php

namespace App\Http\Requests\Admin\Scheduling;

use App\Models\Employee;
use App\Models\Shift;
use App\Rules\ExistsInScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Set (or clear) the shifts of one employee on one date from the calendar.
 */
class ScheduleOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('schedules.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', new ExistsInScope(Employee::class, activeOnly: false)],
            'date' => ['required', 'date_format:Y-m-d'],
            'shift_ids' => ['nullable', 'array', 'max:4'],
            'shift_ids.*' => ['integer', Rule::exists(Shift::class, 'id')->where('is_active', true)],
            'note' => ['nullable', 'string', 'max:500'],
            'view' => ['nullable', 'in:month,week'],
        ];
    }

    public function employee(): Employee
    {
        return Employee::query()->findOrFail($this->integer('employee_id'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [...trans('schedules.fields'), 'date' => __('schedules.fields.work_date')];
    }
}
