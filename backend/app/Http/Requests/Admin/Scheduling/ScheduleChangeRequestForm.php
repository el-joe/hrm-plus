<?php

namespace App\Http\Requests\Admin\Scheduling;

use App\Enums\ScheduleChangeType;
use App\Models\Employee;
use App\Models\ScheduleChangeRequest;
use App\Models\Shift;
use App\Rules\ExistsInScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleChangeRequestForm extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ScheduleChangeRequest::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $swap = $this->input('type') === ScheduleChangeType::Swap->value;

        return [
            'type' => ['required', Rule::enum(ScheduleChangeType::class)],
            'employee_id' => ['required', 'integer', new ExistsInScope(Employee::class)],
            'work_date' => ['required', 'date_format:Y-m-d'],
            'shift_id' => ['nullable', 'integer', Rule::exists(Shift::class, 'id')->where('is_active', true)],
            'swap_employee_id' => [$swap ? 'required' : 'nullable', 'integer', 'different:employee_id', new ExistsInScope(Employee::class)],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('schedules.fields');
    }
}
