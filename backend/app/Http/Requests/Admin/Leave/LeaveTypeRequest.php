<?php

namespace App\Http\Requests\Admin\Leave;

use App\Enums\Gender;
use App\Enums\LeaveUnit;
use App\Http\Requests\Admin\Structure\StructureRequest;
use App\Models\ApprovalWorkflow;
use App\Models\AttendanceStatus;
use App\Models\LeaveType;
use Illuminate\Validation\Rule;

class LeaveTypeRequest extends StructureRequest
{
    public const BOOLEANS = [
        'is_paid', 'affects_payroll', 'tracks_balance', 'requires_attachment', 'allows_past_dates', 'counts_rest_days', 'counts_holidays', 'is_active',
    ];

    protected function modelClass(): string
    {
        return LeaveType::class;
    }

    protected function routeKey(): string
    {
        return 'leave_type';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->identityRules(),
            'description' => ['nullable', 'string', 'max:1000'],
            'is_paid' => ['sometimes', 'boolean'],
            'affects_payroll' => ['sometimes', 'boolean'],
            'tracks_balance' => ['sometimes', 'boolean'],
            'unit' => ['required', Rule::enum(LeaveUnit::class)],
            'requires_attachment' => ['sometimes', 'boolean'],
            'attachment_after_days' => ['nullable', 'decimal:0,2', 'min:0', 'max:365'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'min_service_months' => ['required', 'integer', 'between:0,600'],
            'max_times_per_service' => ['nullable', 'integer', 'between:1,100'],
            'max_days_per_request' => ['nullable', 'decimal:0,2', 'gt:0', 'max:366'],
            'notice_days' => ['required', 'integer', 'between:0,365'],
            'allows_past_dates' => ['sometimes', 'boolean'],
            'counts_rest_days' => ['sometimes', 'boolean'],
            'counts_holidays' => ['sometimes', 'boolean'],
            'approval_workflow' => ['required', 'string', Rule::exists(ApprovalWorkflow::class, 'key')->where('is_active', true)],
            'attendance_status_id' => ['nullable', 'integer', Rule::exists(AttendanceStatus::class, 'id')->where('category', 'on_leave')],
            'color' => ['required', Rule::in(LeaveType::COLORS)],
            'sort_order' => ['required', 'integer', 'between:0,9999'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordData(): array
    {
        $data = $this->withBooleans(self::BOOLEANS);

        if (! ($data['requires_attachment'] ?? $this->record()?->requires_attachment)) {
            $data['attachment_after_days'] = null;
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('leave.fields');
    }
}
