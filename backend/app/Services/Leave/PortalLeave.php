<?php

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestAttachment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The employee's own leave for the portal and the mobile API: always the
 * signed-in employee's records, admin data scopes ignored.
 */
class PortalLeave
{
    /**
     * @return Builder<LeaveRequest>
     */
    public function requests(Employee $employee): Builder
    {
        return LeaveRequest::query()->withoutGlobalScopes()->where('employee_id', $employee->id)
            ->with(['leaveType', 'attachments', 'latestApprovalRequest.currentStep'])
            ->latest('start_date')->latest('id');
    }

    public function paginate(Employee $employee, int $perPage = 20): LengthAwarePaginator
    {
        return $this->requests($employee)->paginate($perPage)->withQueryString();
    }

    public function find(Employee $employee, int $id): ?LeaveRequest
    {
        return $this->requests($employee)->with(['requestDays', 'approvalRequests.actions'])->find($id);
    }

    public function attachment(Employee $employee, int $requestId, int $attachmentId): ?LeaveRequestAttachment
    {
        return LeaveRequestAttachment::query()->whereKey($attachmentId)->where('leave_request_id', $requestId)
            ->whereHas('request', fn (Builder $query) => $query->withoutGlobalScopes()->where('employee_id', $employee->id))
            ->first();
    }
}
