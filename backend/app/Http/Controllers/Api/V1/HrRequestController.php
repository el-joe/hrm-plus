<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\HrRequests\ManageHrRequest;
use App\Actions\HrRequests\SubmitHrRequest;
use App\Enums\HrRequestSource;
use App\Enums\HrRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\HrRequests\OwnHrRequestRequest;
use App\Http\Resources\V1\HrRequestCategoryResource;
use App\Http\Resources\V1\HrRequestResource;
use App\Models\Employee;
use App\Models\HrRequest;
use App\Services\Employees\PortalProfile;
use App\Services\HrRequests\HrRequestFileStore;
use App\Services\HrRequests\PortalHrRequests;
use App\Services\HrRequests\ProfileChangeFields;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET  /api/v1/me/requests                                 own requests, newest first (?status=open|closed|<status>, ?per_page)
 * GET  /api/v1/me/requests/categories                      active categories with their extra fields
 * GET  /api/v1/me/requests/profile-fields                  fields a profile change may touch, with current (masked) values
 * POST /api/v1/me/requests                                 multipart: hr_request_category_id, subject?, description, priority?,
 *                                                          letter_language?, fields[key], changes[field], attachments[]
 * GET  /api/v1/me/requests/{id}                            detail: employee-visible thread, files, status history, profile changes
 * POST /api/v1/me/requests/{id}/replies                    body + attachments[]
 * POST /api/v1/me/requests/{id}/attachments                attachments[]
 * GET  /api/v1/me/requests/{id}/attachments/{attachment}   download (issued letters too)
 * POST /api/v1/me/requests/{id}/cancel                     while still pending approval / open
 *
 * Always the token owner's own requests (another employee's id is a 404). Internal notes are never returned.
 */
class HrRequestController extends Controller
{
    public function __construct(private readonly PortalProfile $profile, private readonly PortalHrRequests $requests) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'closed', ...array_column(HrRequestStatus::cases(), 'value')])],
            'per_page' => ['nullable', 'integer', 'between:1,50'],
        ]);
        $query = $this->requests->forEmployee($this->employee($request));
        $status = $filters['status'] ?? null;

        match ($status) {
            null => null,
            'open' => $query->open(),
            'closed' => $query->whereIn('status', [HrRequestStatus::Resolved->value, HrRequestStatus::Rejected->value, HrRequestStatus::Cancelled->value]),
            default => $query->where('status', $status),
        };

        return ApiResponse::paginated($query->paginate((int) ($filters['per_page'] ?? 15)), HrRequestResource::class);
    }

    public function categories(): JsonResponse
    {
        return ApiResponse::success(HrRequestCategoryResource::collection($this->requests->categories())->resolve(), meta: [
            'enabled' => (bool) setting('hr_requests.employee_requests_enabled'),
            'attachments' => ['max_kb' => (int) setting('hr_requests.attachment_max_kb'), 'extensions' => (array) setting('hr_requests.allowed_extensions'), 'max_files' => 5],
        ]);
    }

    public function profileFields(Request $request, ProfileChangeFields $fields): JsonResponse
    {
        $employee = $this->employee($request);

        return ApiResponse::success(collect($fields->keys())->map(fn (string $field): array => [
            'field' => $field,
            'group' => $fields->group($field),
            'label' => $fields->label($field),
            'current' => $fields->display($field, $fields->current($employee, $field)),
            'masked' => $fields->isMasked($field),
        ])->values()->all());
    }

    public function store(OwnHrRequestRequest $request, SubmitHrRequest $submit): JsonResponse
    {
        $employee = $this->employee($request);
        $created = $submit->handle($employee, $request->category(), $request->requestData(), $request->user(), HrRequestSource::Api, $request->attachmentFiles());

        return ApiResponse::success(new HrRequestResource($this->find($request, $created->id)), __('hr_requests.portal.submitted', ['reference' => $created->reference]), 201);
    }

    public function show(Request $request, int $hrRequest): JsonResponse
    {
        return ApiResponse::success(new HrRequestResource($this->find($request, $hrRequest)));
    }

    public function reply(Request $request, int $hrRequest, ManageHrRequest $manage): JsonResponse
    {
        $record = $this->find($request, $hrRequest);
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => HrRequestFileStore::fileRules(),
        ]);
        $manage->employeeReply($record, $request->user(), $data['body'], $this->files($request));

        return ApiResponse::success(new HrRequestResource($this->find($request, $hrRequest)), __('hr_requests.portal.replied'), 201);
    }

    public function attach(Request $request, int $hrRequest, ManageHrRequest $manage): JsonResponse
    {
        $record = $this->find($request, $hrRequest);
        $request->validate(['attachments' => ['required', 'array', 'min:1', 'max:5'], 'attachments.*' => HrRequestFileStore::fileRules()]);
        $manage->addAttachments($record, $request->user(), $this->files($request));

        return ApiResponse::success(new HrRequestResource($this->find($request, $hrRequest)), __('hr_requests.portal.attached'), 201);
    }

    public function attachment(Request $request, int $hrRequest, int $attachment, HrRequestFileStore $files): StreamedResponse
    {
        $file = $this->find($request, $hrRequest)->employeeAttachments->firstWhere('id', $attachment);
        abort_if($file === null, 404);

        return $files->response($file->file_path, $file->file_name, $file->file_mime);
    }

    public function cancel(Request $request, int $hrRequest, ManageHrRequest $manage): JsonResponse
    {
        $record = $this->find($request, $hrRequest);
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:1000']])['reason'] ?? null;
        $manage->cancel($record, $request->user(), $reason);

        return ApiResponse::success(new HrRequestResource($this->find($request, $hrRequest)), __('hr_requests.portal.cancelled'));
    }

    private function find(Request $request, int $id): HrRequest
    {
        $record = $this->requests->find($this->employee($request), $id);
        abort_if($record === null, 404);

        return $record;
    }

    private function employee(Request $request): Employee
    {
        $employee = $this->profile->employeeFor($request->user());
        abort_if($employee === null, 404);

        return $employee;
    }

    /**
     * @return list<UploadedFile>
     */
    private function files(Request $request): array
    {
        return array_values(array_filter((array) $request->file('attachments', []), fn ($file): bool => $file instanceof UploadedFile));
    }
}
