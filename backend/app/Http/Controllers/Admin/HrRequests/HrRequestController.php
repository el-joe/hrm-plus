<?php

namespace App\Http\Controllers\Admin\HrRequests;

use App\Actions\HrRequests\DecideProfileChanges;
use App\Actions\HrRequests\IssueHrLetter;
use App\Actions\HrRequests\ManageHrRequest;
use App\Actions\HrRequests\SubmitHrRequest;
use App\Enums\HrRequestPriority;
use App\Enums\HrRequestSource;
use App\Enums\HrRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HrRequests\StoreHrRequestRequest;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\HrProfileChange;
use App\Models\HrRequest;
use App\Models\HrRequestAttachment;
use App\Models\HrRequestCategory;
use App\Models\User;
use App\Services\Employees\SensitiveDataAccessLog;
use App\Services\Employees\Spreadsheet;
use App\Services\HrRequests\HrLetterDocument;
use App\Services\HrRequests\HrRequestFileStore;
use App\Services\HrRequests\HrRequestVisibility;
use App\Services\HrRequests\ProfileChangeFields;
use App\Support\IndexSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The HR request queue and request page (Phase 8b). Visibility: HrRequestVisibility (data scope; without
 * hr_requests.manage only assigned-to-me / unassigned / awaiting my approval). Rules live in
 * App\Actions\HrRequests\*; authorisation in HrRequestPolicy.
 */
class HrRequestController extends Controller
{
    public const PER_PAGE = 25;

    public function __construct(private readonly HrRequestVisibility $visibility) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', HrRequest::class);

        $user = $request->user();
        $filters = $this->filters($request);
        $query = $this->query($filters, $user)->with(['employee', 'category', 'assignee']);
        $sort = IndexSorting::apply($query, $request, [
            'reference' => 'reference',
            'submitted' => 'created_at',
            'due' => 'due_at',
            'status' => 'status',
            'priority' => fn (Builder $query, string $direction) => $query->orderByRaw("FIELD(priority, 'low', 'normal', 'high', 'urgent') ".($direction === 'desc' ? 'desc' : 'asc')),
        ], default: 'submitted', defaultDirection: 'desc');
        $base = $this->visibility->query($user);

        return view('admin.hr-requests.index', [
            'requests' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
            'statuses' => HrRequestStatus::cases(),
            'priorities' => HrRequestPriority::cases(),
            'categories' => HrRequestCategory::query()->orderBy('sort_order')->orderBy('id')->get(),
            'branches' => Branch::query()->orderByName()->get(),
            'departments' => Department::query()->orderByName()->get(),
            'assignees' => $this->handlers(),
            'counts' => [
                'open' => (clone $base)->open()->count(),
                'mine' => (clone $base)->open()->where('assigned_to', $user->id)->count(),
                'unassigned' => (clone $base)->whereIn('status', [HrRequestStatus::Open->value, HrRequestStatus::InProgress->value])->whereNull('assigned_to')->count(),
                'overdue' => (clone $base)->overdue()->count(),
                'pending_approval' => (clone $base)->where('status', HrRequestStatus::PendingApproval->value)->count(),
            ],
        ]);
    }

    public function export(Request $request, Spreadsheet $spreadsheet): BinaryFileResponse
    {
        Gate::authorize('export', HrRequest::class);

        $filters = $this->filters($request);
        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $path = storage_path('app/private/hr-requests-'.uniqid().'.'.$format);
        $headers = array_map(fn (string $key): string => __('hr_requests.fields.'.$key), [
            'reference', 'employee', 'employee_number', 'department', 'category', 'subject', 'priority', 'status', 'assigned_to', 'submitted_at', 'due_at', 'resolved_at', 'escalation_level',
        ]);
        $rows = $this->query($filters, $request->user())->with(['employee', 'department', 'category', 'assignee'])->orderBy('id')->lazy()
            ->map(fn (HrRequest $row): array => [
                $row->reference, $row->employee->name, $row->employee->employee_number, $row->department?->name ?? '', $row->category->name, $row->subject,
                $row->priority->label(), $row->status->label(), $row->assignee?->name ?? '', $row->created_at->format('Y-m-d H:i'),
                $row->due_at?->format('Y-m-d H:i') ?? '', $row->resolved_at?->format('Y-m-d H:i') ?? '', $row->escalation_level,
            ]);

        $spreadsheet->write($path, $format, $headers, $rows);

        return response()->download($path, 'hr-requests-'.now()->format('Ymd').'.'.$format)->deleteFileAfterSend();
    }

    public function create(Request $request, ProfileChangeFields $fields): View
    {
        Gate::authorize('create', HrRequest::class);

        $employeeId = $request->integer('employee') ?: null;
        $employee = $employeeId ? Employee::query()->employed()->find($employeeId) : null;

        return view('admin.hr-requests.create', [
            'employees' => Employee::query()->employed()
                ->when($request->user()->employee_id, fn (Builder $query, int $id) => $query->whereKeyNot($id))
                ->orderBy('employee_number')->get(['id', 'employee_number', 'name_ar', 'name_en']),
            'categories' => HrRequestCategory::query()->active()->with('fields')->orderBy('sort_order')->orderBy('id')->get(),
            'selectedEmployee' => $employee?->id,
            'profileFields' => $employee !== null ? $fields->formFields($employee) : null,
            'priorities' => HrRequestPriority::cases(),
        ]);
    }

    public function store(StoreHrRequestRequest $request, SubmitHrRequest $submit): RedirectResponse
    {
        $created = $submit->handle($request->employee(), $request->category(), $request->requestData(), $request->user(), HrRequestSource::Admin, $request->attachmentFiles());

        return redirect()->route('admin.hr-requests.show', $created)->with('status', __('hr_requests.messages.created', ['reference' => $created->reference]));
    }

    public function show(Request $request, HrRequest $hrRequest, ProfileChangeFields $fields, HrLetterDocument $letters, SensitiveDataAccessLog $accessLog): View
    {
        Gate::authorize('view', $hrRequest);

        $user = $request->user();
        $hrRequest->load([
            'employee.department', 'employee.jobTitle', 'category.letterTemplate', 'category.fields', 'branch', 'department', 'assignee', 'submitter', 'closer',
            'fieldValues', 'messages.author', 'attachments.uploader', 'events.actor', 'profileChanges.decider',
            'approvalRequests' => fn ($query) => $query->with(['actions.actor', 'currentStep'])->latest('id'),
        ]);
        $employee = $hrRequest->employee;
        $reveal = [];

        foreach ([ProfileChangeFields::GROUP_SENSITIVE, ProfileChangeFields::GROUP_BANK] as $group) {
            $reveal[$group] = $fields->canReveal($user, $employee, $group);
        }

        $revealed = $hrRequest->profileChanges->filter(fn (HrProfileChange $change): bool => $fields->isMasked($change->field) && $reveal[$fields->group($change->field)]);

        if ($revealed->isNotEmpty()) {
            $sensitive = $revealed->filter(fn (HrProfileChange $change): bool => $fields->group($change->field) === ProfileChangeFields::GROUP_SENSITIVE);
            $financial = $revealed->diff($sensitive);

            if ($sensitive->isNotEmpty()) {
                $accessLog->record(SensitiveDataAccessLog::IDENTITY, $employee, $sensitive->pluck('field')->all(), ['hr_request' => $hrRequest->reference], $user);
            }

            if ($financial->isNotEmpty()) {
                $accessLog->record(SensitiveDataAccessLog::FINANCIAL, $employee, $financial->pluck('field')->all(), ['hr_request' => $hrRequest->reference], $user);
            }
        }

        $template = $hrRequest->category->letterTemplate;

        return view('admin.hr-requests.show', [
            'hrRequest' => $hrRequest,
            'fields' => $fields,
            'reveal' => $reveal,
            'canUpdate' => $user->can('update', $hrRequest),
            'assignees' => $user->can('assign', $hrRequest) ? $this->handlers()->filter(fn (User $admin): bool => $admin->employee_id !== $hrRequest->employee_id && $hrRequest->isVisibleTo($admin)) : collect(),
            'letterUsesSalary' => $template !== null && $letters->usesFinancialData($template),
            'priorities' => HrRequestPriority::cases(),
        ]);
    }

    public function assign(Request $request, HrRequest $hrRequest, ManageHrRequest $manage): RedirectResponse
    {
        $data = $request->validate(['assigned_to' => ['nullable', 'integer']]);
        $user = $request->user();
        $assigneeId = isset($data['assigned_to']) ? (int) $data['assigned_to'] : null;

        // Taking a request needs hr_requests.update; handing it to someone else (or unassigning) needs hr_requests.manage.
        Gate::authorize($assigneeId === $user->id ? 'update' : 'assign', $hrRequest);

        $manage->assign($hrRequest, $assigneeId === null ? null : User::query()->admins()->find($assigneeId) ?? abort(422), $user);

        return back()->with('status', __('hr_requests.messages.assigned'));
    }

    public function start(Request $request, HrRequest $hrRequest, ManageHrRequest $manage): RedirectResponse
    {
        Gate::authorize('update', $hrRequest);
        $manage->start($hrRequest, $request->user());

        return back()->with('status', __('hr_requests.messages.started'));
    }

    public function priority(Request $request, HrRequest $hrRequest, ManageHrRequest $manage): RedirectResponse
    {
        Gate::authorize('update', $hrRequest);
        $data = $request->validate(['priority' => ['required', Rule::enum(HrRequestPriority::class)]]);
        $manage->changePriority($hrRequest, HrRequestPriority::from($data['priority']), $request->user());

        return back()->with('status', __('hr_requests.messages.priority_changed'));
    }

    public function reply(Request $request, HrRequest $hrRequest, ManageHrRequest $manage): RedirectResponse
    {
        Gate::authorize('update', $hrRequest);
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:10000'],
            'visibility' => ['required', Rule::in(['reply', 'internal'])],
            'await_employee' => ['sometimes', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => HrRequestFileStore::fileRules(),
        ]);
        $internal = $data['visibility'] === 'internal';
        $manage->reply($hrRequest, $request->user(), $data['body'], $this->files($request), $internal, ! $internal && $request->boolean('await_employee'));

        return back()->with('status', __($internal ? 'hr_requests.messages.note_added' : 'hr_requests.messages.replied'));
    }

    public function resolve(Request $request, HrRequest $hrRequest, ManageHrRequest $manage): RedirectResponse
    {
        Gate::authorize('update', $hrRequest);
        $data = $request->validate(['resolution_note' => ['nullable', 'string', 'max:5000']]);
        $manage->resolve($hrRequest, $request->user(), $data['resolution_note'] ?? null);

        return back()->with('status', __('hr_requests.messages.resolved', ['reference' => $hrRequest->reference]));
    }

    public function reject(Request $request, HrRequest $hrRequest, ManageHrRequest $manage): RedirectResponse
    {
        Gate::authorize('update', $hrRequest);
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:1000']]);
        $manage->reject($hrRequest, $request->user(), $data['rejection_reason']);

        return back()->with('status', __('hr_requests.messages.rejected', ['reference' => $hrRequest->reference]));
    }

    public function cancel(Request $request, HrRequest $hrRequest, ManageHrRequest $manage): RedirectResponse
    {
        Gate::authorize('cancel', $hrRequest);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $manage->cancel($hrRequest, $request->user(), $data['reason']);

        return back()->with('status', __('hr_requests.messages.cancelled', ['reference' => $hrRequest->reference]));
    }

    public function decide(Request $request, HrRequest $hrRequest, string $decision, ManageHrRequest $manage): RedirectResponse
    {
        Gate::authorize('decide', $hrRequest);
        $data = $request->validate(['reason' => [$decision === 'reject' ? 'required' : 'nullable', 'string', 'max:1000']]);
        $manage->decideApproval($hrRequest, $request->user(), $decision === 'approve', $data['reason'] ?? null);

        return back()->with('status', __($decision === 'approve' ? 'hr_requests.messages.approved' : 'hr_requests.messages.approval_rejected'));
    }

    public function storeAttachments(Request $request, HrRequest $hrRequest, ManageHrRequest $manage): RedirectResponse
    {
        Gate::authorize('update', $hrRequest);
        $request->validate([
            'attachments' => ['required', 'array', 'min:1', 'max:5'],
            'attachments.*' => HrRequestFileStore::fileRules(),
            'internal' => ['sometimes', 'boolean'],
        ]);
        $manage->addAttachments($hrRequest, $request->user(), $this->files($request), $request->boolean('internal'));

        return back()->with('status', __('hr_requests.messages.attached'));
    }

    public function attachment(HrRequest $hrRequest, HrRequestAttachment $attachment, HrRequestFileStore $files): StreamedResponse
    {
        abort_unless($attachment->hr_request_id === $hrRequest->id, 404);
        Gate::authorize('view', $hrRequest);

        return $files->response($attachment->file_path, $attachment->file_name, $attachment->file_mime);
    }

    public function issueLetter(Request $request, HrRequest $hrRequest, IssueHrLetter $issue): RedirectResponse
    {
        Gate::authorize('issueLetter', $hrRequest);
        $data = $request->validate(['language' => ['nullable', Rule::in(['ar', 'en'])], 'resolve' => ['sometimes', 'boolean']]);
        $issue->handle($hrRequest, $request->user(), $data['language'] ?? null, $request->boolean('resolve'));

        return back()->with('status', __('hr_requests.messages.letter_issued'));
    }

    public function applyChanges(Request $request, HrRequest $hrRequest, DecideProfileChanges $decide): RedirectResponse
    {
        Gate::authorize('decideChanges', $hrRequest);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $decide->apply($hrRequest, $request->user(), $data['note'] ?? null);

        return back()->with('status', __('hr_requests.messages.changes_applied'));
    }

    public function rejectChanges(Request $request, HrRequest $hrRequest, DecideProfileChanges $decide): RedirectResponse
    {
        Gate::authorize('decideChanges', $hrRequest);
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:1000']]);
        $decide->reject($hrRequest, $request->user(), $data['rejection_reason']);

        return back()->with('status', __('hr_requests.messages.changes_rejected'));
    }

    /**
     * Active admins who may handle requests (for the assignee pickers).
     *
     * @return Collection<int, User>
     */
    private function handlers(): Collection
    {
        return User::query()->admins()->orderBy('name')->get()
            ->filter(fn (User $admin): bool => $admin->isActive() && $admin->can('hr_requests.update'))->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['open', 'closed', ...array_column(HrRequestStatus::cases(), 'value')])],
            'category_id' => ['nullable', 'integer'],
            'priority' => ['nullable', Rule::enum(HrRequestPriority::class)],
            'assignee' => ['nullable', 'string', 'max:20'],
            'overdue' => ['nullable', 'boolean'],
            'escalated' => ['nullable', 'boolean'],
            'branch_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<HrRequest>
     */
    private function query(array $filters, User $user): Builder
    {
        $query = $this->visibility->query($user);
        $status = $filters['status'] ?? null;

        match ($status) {
            null => null,
            'open' => $query->open(),
            'closed' => $query->whereIn('status', [HrRequestStatus::Resolved->value, HrRequestStatus::Rejected->value, HrRequestStatus::Cancelled->value]),
            default => $query->where('status', $status),
        };

        $assignee = $filters['assignee'] ?? null;

        return $query
            ->when($assignee === 'mine', fn (Builder $query) => $query->where('assigned_to', $user->id))
            ->when($assignee === 'unassigned', fn (Builder $query) => $query->whereNull('assigned_to'))
            ->when(is_numeric($assignee), fn (Builder $query) => $query->where('assigned_to', (int) $assignee))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('reference', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%")
                ->orWhereHas('employee', fn (Builder $employee) => $employee->search($search))))
            ->when($filters['category_id'] ?? null, fn (Builder $query, int $id) => $query->where('hr_request_category_id', $id))
            ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($filters['overdue'] ?? false, fn (Builder $query) => $query->overdue())
            ->when($filters['escalated'] ?? false, fn (Builder $query) => $query->where('escalation_level', '>', 0)->open())
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int $id) => $query->where('branch_id', $id))
            ->when($filters['department_id'] ?? null, fn (Builder $query, int $id) => $query->where('department_id', $id))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date));
    }

    /**
     * @return list<UploadedFile>
     */
    private function files(Request $request): array
    {
        return array_values(array_filter((array) $request->file('attachments', []), fn ($file): bool => $file instanceof UploadedFile));
    }
}
