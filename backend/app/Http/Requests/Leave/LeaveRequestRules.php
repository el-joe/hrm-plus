<?php

namespace App\Http\Requests\Leave;

use App\Enums\LeaveDayPart;
use App\Http\Requests\Admin\Employees\DocumentRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Shape rules shared by the admin, portal and API leave request forms. The
 * business rules (unit, notice, eligibility, balance, overlap, attachments
 * needed) are checked by App\Actions\Leave\ManageLeaveRequest.
 */
trait LeaveRequestRules
{
    /**
     * @return array<string, mixed>
     */
    protected function leaveRules(): array
    {
        return [
            'leave_type_id' => ['required', 'integer'],
            'start_date' => ['required', 'date_format:Y-m-d', 'after:2000-01-01', 'before:2100-01-01'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before:2100-01-01'],
            'day_part' => ['nullable', Rule::enum(LeaveDayPart::class)],
            'hours' => ['nullable', 'required_if:day_part,hours', 'decimal:0,2', 'gt:0', 'max:24'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', ...DocumentRequest::fileRules()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function leaveData(): array
    {
        return collect($this->validated())->except(['attachments', 'employee_id', 'record_as_approved'])->all();
    }

    /**
     * @return list<UploadedFile>
     */
    public function attachmentFiles(): array
    {
        return array_values(array_filter((array) $this->file('attachments', [])));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('leave.fields');
    }
}
