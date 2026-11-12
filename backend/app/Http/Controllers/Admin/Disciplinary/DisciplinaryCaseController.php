<?php

namespace App\Http\Controllers\Admin\Disciplinary;

use App\Actions\Disciplinary\ManageDisciplinaryCase;
use App\Enums\DisciplinaryActionStatus;
use App\Enums\DisciplinaryCaseStatus;
use App\Enums\DisciplinaryNoteType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Disciplinary\EvidenceRules;
use App\Http\Requests\Admin\Disciplinary\ReportIncidentRequest;
use App\Models\Department;
use App\Models\DisciplinaryAction;
use App\Models\DisciplinaryCase;
use App\Models\DisciplinaryEvidence;
use App\Models\Employee;
use App\Models\User;
use App\Models\ViolationType;
use App\Services\Disciplinary\DisciplinaryFileStore;
use App\Services\Disciplinary\DisciplinaryRecipients;
use App\Services\Disciplinary\WarningEscalation;
use App\Services\Employees\SensitiveDataAccessLog;
use App\Services\Employees\Spreadsheet;
use App\Support\IndexSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Disciplinary cases (restricted). Holders of disciplinary.view see the case
 * files in their data scope — every list, page, evidence download and export
 * is written to the sensitive-data access log. Holders of disciplinary.create
 * only report incidents and follow the status of the cases they reported.
 * Rules live in App\Actions\Disciplinary\*.
 */
class DisciplinaryCaseController extends Controller
{
    use EvidenceRules;

    public const PER_PAGE = 25;

    public function __construct(private readonly SensitiveDataAccessLog $accessLog) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', DisciplinaryCase::class);

        $user = $request->user();
        $fullAccess = $user->can('disciplinary.view');
        $filters = $this->filters($request);
        $query = $this->query($filters, $user)->with(['employee', 'violationType', 'latestAction']);
        $sort = IndexSorting::apply($query, $request, [
            'reference' => 'reference',
            'incident_date' => 'incident_date',
            'status' => 'status',
            'reported' => 'created_at',
        ], default: 'reported', defaultDirection: 'desc');
        $cases = $query->paginate(self::PER_PAGE)->withQueryString();

        if ($fullAccess) {
            $this->accessLog->record(SensitiveDataAccessLog::DISCIPLINARY, null, ['case_list'], ['count' => $cases->total(), 'page' => $cases->currentPage()], $user);
        }

        return view('admin.disciplinary.cases.index', [
            'cases' => $cases,
            'filters' => $filters,
            'sort' => $sort,
            'fullAccess' => $fullAccess,
            'statuses' => DisciplinaryCaseStatus::cases(),
            'violationTypes' => ViolationType::query()->orderBy('code')->get(),
            'departments' => $fullAccess ? Department::query()->orderByName()->get() : collect(),
            'counts' => $fullAccess ? [
                'open' => DisciplinaryCase::query()->whereIn('status', [DisciplinaryCaseStatus::Reported, DisciplinaryCaseStatus::Investigating])->count(),
                'pending' => DisciplinaryCase::query()->where('status', DisciplinaryCaseStatus::PendingApproval)->count(),
                'awaiting_response' => DisciplinaryAction::query()->where('status', DisciplinaryActionStatus::Approved)->whereNull('responded_at')
                    ->whereHas('disciplinaryCase', fn (Builder $case) => $case->where('status', DisciplinaryCaseStatus::ActionIssued))->count(),
                'active_warnings' => DisciplinaryAction::query()->activeWarnings()->count(),
            ] : null,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', DisciplinaryCase::class);

        return view('admin.disciplinary.cases.create', [
            'employees' => Employee::query()->employed()
                ->when($request->user()->employee_id, fn (Builder $query, int $id) => $query->whereKeyNot($id))
                ->orderBy('employee_number')->get(['id', 'employee_number', 'name_ar', 'name_en']),
            'violationTypes' => ViolationType::query()->active()->orderBy('code')->get(),
            'selectedEmployee' => $request->integer('employee') ?: null,
        ]);
    }

    public function store(ReportIncidentRequest $request, ManageDisciplinaryCase $cases): RedirectResponse
    {
        $case = $cases->report($request->safe()->except('evidence'), $request->user(), $request->evidenceFiles());

        return redirect()->route('admin.disciplinary-cases.show', $case)->with('status', __('disciplinary.cases.reported', ['reference' => $case->reference]));
    }

    public function show(Request $request, DisciplinaryCase $disciplinaryCase, WarningEscalation $escalation, DisciplinaryRecipients $recipients): View
    {
        $user = $request->user();

        if (! $user->can('view', $disciplinaryCase)) {
            Gate::authorize('viewSummary', $disciplinaryCase);

            return view('admin.disciplinary.cases.summary', ['case' => $disciplinaryCase->load(['employee', 'violationType', 'latestAction'])]);
        }

        $disciplinaryCase->load([
            'employee.department', 'employee.jobTitle', 'violationType', 'branch', 'department', 'reporter', 'investigator', 'closer',
            'notes.author', 'evidence.uploader',
            'actions' => fn ($query) => $query->with(['proposer', 'decider', 'payEntry', 'latestApprovalRequest.currentStep',
                'approvalRequests' => fn ($approvals) => $approvals->with(['actions.actor', 'currentStep'])->latest('id')]),
        ]);

        $this->accessLog->record(SensitiveDataAccessLog::DISCIPLINARY, $disciplinaryCase, ['description', 'notes', 'evidence', 'actions', 'employee_response'],
            ['case' => $disciplinaryCase->reference], $user);

        $employee = $disciplinaryCase->employee;

        return view('admin.disciplinary.cases.show', [
            'case' => $disciplinaryCase,
            'suggestion' => $disciplinaryCase->status->acceptsProposal() ? $escalation->suggest($employee, $disciplinaryCase->violationType) : null,
            'activeWarnings' => $escalation->activeWarnings($employee),
            'history' => DisciplinaryCase::query()->where('employee_id', $employee->id)->whereKeyNot($disciplinaryCase->id)
                ->with(['violationType', 'latestAction'])->latest('incident_date')->limit(10)->get(),
            'canUpdate' => $user->can('update', $disciplinaryCase),
            'investigators' => $disciplinaryCase->status === DisciplinaryCaseStatus::Reported ? $recipients->viewersOf($disciplinaryCase) : collect(),
            'noteTypes' => DisciplinaryNoteType::cases(),
        ]);
    }

    public function investigate(Request $request, DisciplinaryCase $disciplinaryCase, ManageDisciplinaryCase $cases): RedirectResponse
    {
        Gate::authorize('update', $disciplinaryCase);

        $data = $request->validate(['investigator_id' => ['nullable', 'integer']]);
        $cases->startInvestigation($disciplinaryCase, $request->user(), isset($data['investigator_id']) ? (int) $data['investigator_id'] : null);

        return back()->with('status', __('disciplinary.cases.investigation_started'));
    }

    public function storeNote(Request $request, DisciplinaryCase $disciplinaryCase, ManageDisciplinaryCase $cases): RedirectResponse
    {
        Gate::authorize('update', $disciplinaryCase);

        $data = $request->validate([
            'type' => ['required', Rule::enum(DisciplinaryNoteType::class)],
            'body' => ['required', 'string', 'max:10000'],
        ]);
        $cases->addNote($disciplinaryCase, DisciplinaryNoteType::from($data['type']), $data['body'], $request->user());

        return back()->with('status', __('disciplinary.cases.note_added'));
    }

    public function storeEvidence(Request $request, DisciplinaryCase $disciplinaryCase, ManageDisciplinaryCase $cases): RedirectResponse
    {
        Gate::authorize('update', $disciplinaryCase);

        $data = $request->validate([...$this->evidenceRules(true), 'evidence_description' => ['nullable', 'string', 'max:500']]);
        $cases->addEvidence($disciplinaryCase, array_values((array) $request->file('evidence', [])), $data['evidence_description'] ?? null, $request->user());

        return back()->with('status', __('disciplinary.cases.evidence_added'));
    }

    public function evidence(Request $request, DisciplinaryCase $disciplinaryCase, DisciplinaryEvidence $evidence, DisciplinaryFileStore $files): StreamedResponse
    {
        abort_unless($evidence->disciplinary_case_id === $disciplinaryCase->id, 404);
        Gate::authorize('view', $disciplinaryCase);

        $this->accessLog->record(SensitiveDataAccessLog::DISCIPLINARY, $disciplinaryCase, ['evidence'], ['case' => $disciplinaryCase->reference, 'evidence' => $evidence->id], $request->user());

        return $files->response($evidence->file_path, $evidence->file_name, $evidence->file_mime);
    }

    public function findings(Request $request, DisciplinaryCase $disciplinaryCase, ManageDisciplinaryCase $cases): RedirectResponse
    {
        Gate::authorize('update', $disciplinaryCase);

        $data = $request->validate(['findings' => ['required', 'string', 'max:10000']]);
        $cases->recordFindings($disciplinaryCase, $data['findings'], $request->user());

        return back()->with('status', __('disciplinary.cases.findings_saved'));
    }

    public function dismiss(Request $request, DisciplinaryCase $disciplinaryCase, ManageDisciplinaryCase $cases): RedirectResponse
    {
        Gate::authorize('update', $disciplinaryCase);

        $data = $request->validate(['closure_note' => ['required', 'string', 'max:2000']]);
        $cases->dismiss($disciplinaryCase, $request->user(), $data['closure_note']);

        return back()->with('status', __('disciplinary.cases.dismissed', ['reference' => $disciplinaryCase->reference]));
    }

    public function close(Request $request, DisciplinaryCase $disciplinaryCase, ManageDisciplinaryCase $cases): RedirectResponse
    {
        Gate::authorize('update', $disciplinaryCase);

        $data = $request->validate(['closure_note' => ['nullable', 'string', 'max:2000']]);
        $cases->close($disciplinaryCase, $request->user(), $data['closure_note'] ?? null);

        return back()->with('status', __('disciplinary.cases.closed', ['reference' => $disciplinaryCase->reference]));
    }

    public function export(Request $request, Spreadsheet $spreadsheet): BinaryFileResponse
    {
        Gate::authorize('export', DisciplinaryCase::class);

        $filters = $this->filters($request);
        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $path = storage_path('app/private/disciplinary-'.uniqid().'.'.$format);
        $headers = array_map(fn (string $key): string => __('disciplinary.fields.'.$key), [
            'reference', 'employee', 'employee_number', 'department', 'violation_type', 'severity', 'incident_date', 'status', 'action', 'action_status', 'responded',
        ]);
        $query = $this->query($filters, $request->user())->with(['employee', 'department', 'violationType', 'latestAction'])->orderBy('id');

        $this->accessLog->record(SensitiveDataAccessLog::DISCIPLINARY, null, ['case_export'], ['count' => (clone $query)->count(), 'format' => $format], $request->user());

        $rows = $query->lazy()->map(fn (DisciplinaryCase $case): array => [
            $case->reference, $case->employee->name, $case->employee->employee_number, $case->department?->name ?? '',
            $case->violationType->name, $case->violationType->severity->label(), $case->incident_date->toDateString(), $case->status->label(),
            $case->latestAction?->type->label() ?? '', $case->latestAction?->status->label() ?? '',
            $case->latestAction?->responded_at?->toDateString() ?? '',
        ]);

        $spreadsheet->write($path, $format, $headers, $rows);

        return response()->download($path, 'disciplinary-cases-'.now()->format('Ymd').'.'.$format)->deleteFileAfterSend();
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(DisciplinaryCaseStatus::class)],
            'violation_type_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
    }

    /**
     * Cases in the admin's data scope (global scope); reporters without disciplinary.view see only their own reports.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<DisciplinaryCase>
     */
    private function query(array $filters, User $user): Builder
    {
        return DisciplinaryCase::query()
            ->when(! $user->can('disciplinary.view'), fn (Builder $query) => $query->where('reported_by', $user->id))
            ->when($user->employee_id, fn (Builder $query, int $id) => $query->where('employee_id', '!=', $id))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('reference', 'like', "%{$search}%")
                ->orWhereHas('employee', fn (Builder $employee) => $employee->search($search))))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['violation_type_id'] ?? null, fn (Builder $query, int $id) => $query->where('violation_type_id', $id))
            ->when($filters['department_id'] ?? null, fn (Builder $query, int $id) => $query->where('department_id', $id))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('incident_date', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('incident_date', '<=', $date));
    }
}
