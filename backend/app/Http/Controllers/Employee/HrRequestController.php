<?php

namespace App\Http\Controllers\Employee;

use App\Actions\HrRequests\ManageHrRequest;
use App\Actions\HrRequests\SubmitHrRequest;
use App\Enums\HrRequestCategoryType;
use App\Enums\HrRequestSource;
use App\Enums\HrRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\HrRequests\OwnHrRequestRequest;
use App\Models\Employee;
use App\Models\HrRequest;
use App\Services\Employees\PortalProfile;
use App\Services\HrRequests\HrRequestFileStore;
use App\Services\HrRequests\PortalHrRequests;
use App\Services\HrRequests\ProfileChangeFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Portal "My requests" (Phase 8b): the signed-in user's own HR requests — list, new request (incl.
 * letters and profile change requests), detail with the employee-visible thread, reply, files, cancel.
 * Internal notes and HR-only files are never loaded here.
 */
class HrRequestController extends Controller
{
    public function __construct(private readonly PortalProfile $profile, private readonly PortalHrRequests $requests) {}

    public function index(Request $request): View
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(['open', 'closed'])]]);
        $query = $this->requests->forEmployee($this->employee($request));

        if (($filters['status'] ?? null) === 'open') {
            $query->open();
        } elseif (($filters['status'] ?? null) === 'closed') {
            $query->whereIn('status', [HrRequestStatus::Resolved->value, HrRequestStatus::Rejected->value, HrRequestStatus::Cancelled->value]);
        }

        return view('employee.requests.index', [
            'requests' => $query->paginate(15)->withQueryString(),
            'filters' => $filters,
            'enabled' => (bool) setting('hr_requests.employee_requests_enabled'),
        ]);
    }

    public function create(Request $request, ProfileChangeFields $fields): View
    {
        abort_unless(setting('hr_requests.employee_requests_enabled'), 404);
        $employee = $this->employee($request);
        $categories = $this->requests->categories();

        return view('employee.requests.create', [
            'categories' => $categories,
            'selected' => $request->integer('category') ?: null,
            'profileFields' => $categories->contains(fn ($category): bool => $category->isProfileChange()) ? $fields->formFields($employee) : collect(),
        ]);
    }

    public function profileChange(Request $request): RedirectResponse
    {
        $category = $this->requests->categories()->first(fn ($category): bool => $category->type === HrRequestCategoryType::ProfileChange);
        abort_if($category === null, 404);

        return redirect()->route('portal.requests.create', ['category' => $category->id]);
    }

    public function store(OwnHrRequestRequest $request, SubmitHrRequest $submit): RedirectResponse
    {
        $created = $submit->handle($this->employee($request), $request->category(), $request->requestData(), $request->user(), HrRequestSource::Portal, $request->attachmentFiles());

        return redirect()->route('portal.requests.show', $created->id)->with('status', __('hr_requests.portal.submitted', ['reference' => $created->reference]));
    }

    public function show(Request $request, int $hrRequest, ProfileChangeFields $fields): View
    {
        return view('employee.requests.show', ['hrRequest' => $this->find($request, $hrRequest), 'fields' => $fields]);
    }

    public function reply(Request $request, int $hrRequest, ManageHrRequest $manage): RedirectResponse
    {
        $record = $this->find($request, $hrRequest);
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => HrRequestFileStore::fileRules(),
        ]);
        $files = array_values(array_filter((array) $request->file('attachments', []), fn ($file): bool => $file instanceof UploadedFile));
        $manage->employeeReply($record, $request->user(), $data['body'], $files);

        return back()->with('status', __('hr_requests.portal.replied'));
    }

    public function attachment(Request $request, int $hrRequest, int $attachment, HrRequestFileStore $files): StreamedResponse
    {
        $file = $this->find($request, $hrRequest)->employeeAttachments->firstWhere('id', $attachment);
        abort_if($file === null, 404);

        return $files->response($file->file_path, $file->file_name, $file->file_mime);
    }

    public function cancel(Request $request, int $hrRequest, ManageHrRequest $manage): RedirectResponse
    {
        $record = $this->find($request, $hrRequest);
        $manage->cancel($record, $request->user(), $request->validate(['reason' => ['nullable', 'string', 'max:1000']])['reason'] ?? null);

        return back()->with('status', __('hr_requests.portal.cancelled'));
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
}
