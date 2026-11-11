<?php

namespace App\Http\Requests\Admin\Attendance;

use App\Models\AttendanceStatus;
use App\Models\Employee;
use App\Models\Shift;
use App\Rules\ExistsInScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Individual attendance entry / correction by an admin (attendance.update),
 * for an employee inside the admin's data scope. Times are HH:MM in the
 * employee's branch timezone.
 */
class CorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('attendance.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', new ExistsInScope(Employee::class, activeOnly: false)],
            'work_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:+1 day'],
            'shift_id' => ['nullable', 'integer', Rule::exists(Shift::class, 'id')],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'attendance_status_id' => ['nullable', 'integer', Rule::exists(AttendanceStatus::class, 'id')->where('is_active', true)],
            'void_raw_event_ids' => ['nullable', 'array'],
            'void_raw_event_ids.*' => ['integer'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('attendance.fields');
    }
}
