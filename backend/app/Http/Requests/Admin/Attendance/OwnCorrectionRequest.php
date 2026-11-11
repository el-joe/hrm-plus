<?php

namespace App\Http\Requests\Admin\Attendance;

use App\Enums\AttendanceStatusCategory;
use App\Models\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An employee's correction request (portal and mobile API): own record only
 * (the controller resolves the employee from the account), past or current
 * dates, times in the branch timezone, optionally a "present" status such as
 * remote work or an official mission.
 */
class OwnCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->employee_id !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'work_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after:-90 days'],
            'shift_id' => ['nullable', 'integer'],
            'check_in' => ['nullable', 'date_format:H:i', 'required_without_all:check_out,attendance_status_id'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'attendance_status_id' => ['nullable', 'integer', Rule::exists(AttendanceStatus::class, 'id')
                ->where('is_active', true)->where('category', AttendanceStatusCategory::Present->value)],
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
