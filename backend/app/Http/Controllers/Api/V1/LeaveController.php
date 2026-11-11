<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Leave\ManageLeaveRequest;
use App\Enums\LeaveRequestSource;
use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Employees\DocumentRequest;
use App\Http\Requests\Leave\OwnLeaveRequest;
use App\Http\Resources\V1\LeaveRequestResource;
use App\Http\Resources\V1\LeaveTypeResource;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\Employees\EmployeeFileStore;
use App\Services\Employees\PortalProfile;
use App\Services\Leave\Data\LeaveBalance;
use App\Services\Leave\LeaveBalanceCalculator;
use App\Services\Leave\LeaveEligibility;
use App\Services\Leave\LeaveYear;
use App\Services\Leave\PortalLeave;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET  /api/v1/me/leave/types                                   leave types the employee may request (+ form rules)
 * GET  /api/v1/me/leave/balances?year=YYYY                      balance breakdown per type
 * GET  /api/v1/me/leave/requests?status=&page=                  own requests (paginated)
 * POST /api/v1/me/leave/requests/preview                        days a request would charge, balance after
 * POST /api/v1/me/leave/requests                                submit (multipart: attachments[])
 * GET  /api/v1/me/leave/requests/{id}                           detail with days and approval history
 * POST /api/v1/me/leave/requests/{id}/cancel                    cancel / ask to cancel ({reason})
 * POST /api/v1/me/leave/requests/{id}/attachments               add files to a pending request
 * GET  /api/v1/me/leave/requests/{id}/attachments/{attachment}  download a file
 *
 * Always the token owner's own employee record.
 */
class LeaveController extends Controller
{
    public function __construct(private readonly PortalProfile $profile, private readonly PortalLeave $leave) {}

    public function types(Request $request, LeaveEligibility $eligibility): JsonResponse
    {
        return ApiResponse::success(LeaveTypeResource::collection($eligibility->requestableTypes($this->employee($request)))->resolve());
    }

    public function balances(Request $request, LeaveBalanceCalculator $balances, LeaveEligibility $eligibility, LeaveYear $years): JsonResponse
    {
        $data = $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100']]);
        $employee = $this->employee($request);
        $year = (int) ($data['year'] ?? $years->current());

        return ApiResponse::success(
            $balances->all($employee, $year, $eligibility->requestableTypes($employee))->map(fn (LeaveBalance $balance): array => $balance->toArray())->all(),
            meta: ['year' => $year, 'year_label' => $years->label($year), 'year_start' => $years->start($year)->toDateString(), 'year_end' => $years->end($year)->toDateString()],
        );
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', 'string', 'in:'.implode(',', array_column(LeaveRequestStatus::cases(), 'value'))]]);
        $query = $this->leave->requests($this->employee($request))->when($data['status'] ?? null, fn ($query, string $status) => $query->where('status', $status));

        return ApiResponse::paginated($query->paginate(20), LeaveRequestResource::class);
    }

    public function show(Request $request, int $leaveRequest): JsonResponse
    {
        return ApiResponse::success(new LeaveRequestResource($this->find($request, $leaveRequest)));
    }

    public function preview(OwnLeaveRequest $request, ManageLeaveRequest $requests, LeaveBalanceCalculator $balances): JsonResponse
    {
        $employee = $this->employee($request);
        ['type' => $type, 'count' => $count] = $requests->preview($employee, $request->leaveData());
        $after = [];

        if ($type->tracks_balance) {
            foreach ($count->byYear as $year => $days) {
                $after[$year] = bcsub($balances->availableFor($employee, $type, $year), $days, 2);
            }
        }

        return ApiResponse::success([
            ...$count->toArray(),
            'available_after' => $after,
            'needs_attachment' => $type->needsAttachment($count->total),
        ]);
    }

    public function store(OwnLeaveRequest $request, ManageLeaveRequest $requests): JsonResponse
    {
        $leave = $requests->submit($this->employee($request), $request->leaveData(), LeaveRequestSource::Api, $request->user(), $request->attachmentFiles());

        return ApiResponse::success(
            new LeaveRequestResource($leave->load(['leaveType', 'attachments', 'requestDays', 'latestApprovalRequest.currentStep'])),
            __('leave.portal.sent'), 201,
        );
    }

    public function cancel(Request $request, int $leaveRequest, ManageLeaveRequest $requests): JsonResponse
    {
        $record = $this->find($request, $leaveRequest);
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:1000']])['reason'] ?? null;
        $status = $requests->cancel($record, $request->user(), $reason);

        return ApiResponse::success(
            new LeaveRequestResource($record->refresh()->load(['leaveType', 'attachments'])),
            __($status === LeaveRequestStatus::CancellationPending ? 'leave.portal.cancellation_requested' : 'leave.portal.cancelled'),
        );
    }

    public function addAttachments(Request $request, int $leaveRequest, ManageLeaveRequest $requests): JsonResponse
    {
        $record = $this->find($request, $leaveRequest);
        $request->validate([
            'attachments' => ['required', 'array', 'min:1', 'max:5'],
            'attachments.*' => ['file', ...DocumentRequest::fileRules()],
        ]);
        $requests->addAttachments($record, array_values((array) $request->file('attachments')), $request->user());

        return ApiResponse::success(new LeaveRequestResource($record->refresh()->load(['leaveType', 'attachments'])), __('leave.portal.attachments_added'));
    }

    public function attachment(Request $request, int $leaveRequest, int $attachment, EmployeeFileStore $files): StreamedResponse
    {
        $file = $this->leave->attachment($this->employee($request), $leaveRequest, $attachment);
        abort_if($file === null, 404);

        return $files->response($file->file_path, $file->file_name, $file->file_mime);
    }

    private function find(Request $request, int $id): LeaveRequest
    {
        $record = $this->leave->find($this->employee($request), $id);
        abort_if($record === null, 404);

        return $record;
    }

    private function employee(Request $request): Employee
    {
        $employee = $this->profile->employeeFor($request->user());
        abort_if($employee === null, 404);

        return $employee;
    }
}
