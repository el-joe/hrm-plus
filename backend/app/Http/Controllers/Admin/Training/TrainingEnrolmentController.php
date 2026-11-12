<?php

namespace App\Http\Controllers\Admin\Training;

use App\Actions\Training\ManageTrainingEnrolment;
use App\Enums\TrainingEnrolmentSource;
use App\Enums\TrainingEnrolmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\TrainingCourse;
use App\Models\TrainingEnrolment;
use App\Models\TrainingSession;
use App\Services\Approvals\ApprovalService;
use App\Services\Employees\Spreadsheet;
use App\Support\IndexSorting;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Enrolments inside the admin's data scope: the queue (requests awaiting approval,
 * "my approvals"), the enrolment page (attendance, outcome, approval history),
 * decisions, cancellation, outcome and the Excel / CSV export.
 */
class TrainingEnrolmentController extends Controller
{
    public const PER_PAGE = 25;

    public function index(Request $request, ApprovalService $approvals): View
    {
        Gate::authorize('viewAny', TrainingEnrolment::class);

        $filters = $this->filters($request);
        $query = $this->query($filters, $request, $approvals)->with(['employee', 'session.course', 'latestApprovalRequest.currentStep']);
        $sort = IndexSorting::apply($query, $request, [
            'created' => 'created_at',
            'start_date' => fn (Builder $query, string $direction) => $query->orderBy(
                TrainingSession::query()->select('start_date')->whereColumn('training_sessions.id', 'training_enrolments.training_session_id'), $direction),
            'cost' => 'cost',
        ], 'created', 'desc');

        return view('admin.training.enrolments.index', [
            'enrolments' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
            'statuses' => TrainingEnrolmentStatus::cases(),
            'sources' => TrainingEnrolmentSource::cases(),
            'courses' => TrainingCourse::query()->orderByName()->get(),
            'departments' => Department::query()->orderByName()->get(),
            'counts' => [
                'pending' => TrainingEnrolment::query()->where('status', TrainingEnrolmentStatus::Pending)->count(),
                'mine' => $approvals->pendingFor($request->user())->whereHasMorph('approvable', [TrainingEnrolment::class])->count(),
                'enrolled' => TrainingEnrolment::query()->where('status', TrainingEnrolmentStatus::Enrolled)->count(),
                'completed' => TrainingEnrolment::query()->where('status', TrainingEnrolmentStatus::Completed)->whereYear('completed_at', now()->year)->count(),
            ],
            'currency' => (string) setting('localisation.currency_code'),
        ]);
    }

    public function show(Request $request, TrainingEnrolment $trainingEnrolment): View
    {
        Gate::authorize('view', $trainingEnrolment);

        $enrolment = $trainingEnrolment->load([
            'employee.department', 'session.course.provider', 'attendances', 'certificate', 'requester', 'decider',
            'approvalRequests' => fn ($query) => $query->with(['actions.actor', 'currentStep'])->latest('id'),
        ]);

        return view('admin.training.enrolments.show', [
            'enrolment' => $enrolment,
            'days' => $enrolment->session->days(),
            'attendanceRate' => $enrolment->attendanceRate(),
            'outcomes' => TrainingEnrolmentStatus::outcomes(),
            'canDecide' => $request->user()->can('decide', $enrolment),
            'canUpdate' => $request->user()->can('update', $enrolment) && $enrolment->session->start_date->lte(today())
                && in_array($enrolment->status, [TrainingEnrolmentStatus::Enrolled, ...TrainingEnrolmentStatus::outcomes()], true),
            'canCancel' => $request->user()->can('cancel', $enrolment),
            'minAttendance' => (int) setting('training.completion_min_attendance_percent'),
            'currency' => (string) setting('localisation.currency_code'),
        ]);
    }

    public function decide(Request $request, TrainingEnrolment $trainingEnrolment, string $decision, ManageTrainingEnrolment $enrolments): RedirectResponse
    {
        Gate::authorize('decide', $trainingEnrolment);

        $data = $request->validate(['reason' => [$decision === 'reject' ? 'required' : 'nullable', 'string', 'max:1000']]);
        $enrolments->decide($trainingEnrolment, $request->user(), $decision === 'approve', $data['reason'] ?? null);

        return back()->with('status', __($decision === 'approve' ? 'training.enrolments.approved' : 'training.enrolments.rejected'));
    }

    public function cancel(Request $request, TrainingEnrolment $trainingEnrolment, ManageTrainingEnrolment $enrolments): RedirectResponse
    {
        Gate::authorize('cancel', $trainingEnrolment);

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $enrolments->cancel($trainingEnrolment, $request->user(), $data['reason']);

        return back()->with('status', __('training.enrolments.cancelled'));
    }

    public function outcome(Request $request, TrainingEnrolment $trainingEnrolment, ManageTrainingEnrolment $enrolments): RedirectResponse
    {
        Gate::authorize('update', $trainingEnrolment);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_map(fn (TrainingEnrolmentStatus $status): string => $status->value, TrainingEnrolmentStatus::outcomes()))],
            'score' => ['nullable', 'decimal:0,2', 'between:0,100'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $enrolments->recordOutcome($trainingEnrolment, TrainingEnrolmentStatus::from($data['status']), $data['score'] ?? null, $data['note'] ?? null, $request->user());

        return back()->with('status', __('training.enrolments.outcome_saved'));
    }

    public function export(Request $request, Spreadsheet $spreadsheet, ApprovalService $approvals): BinaryFileResponse
    {
        Gate::authorize('export', TrainingEnrolment::class);

        $filters = $this->filters($request);
        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $path = storage_path('app/private/training-enrolments-'.uniqid().'.'.$format);
        $headers = array_map(fn (string $key): string => __('training.fields.'.$key), [
            'session', 'course', 'start_date', 'employee', 'department', 'source', 'status', 'attendance_rate', 'score', 'cost',
        ]);

        $departments = Department::query()->withoutGlobalScopes()->get()->keyBy('id');
        $rows = $this->query($filters, $request, $approvals)->with(['employee', 'session.course', 'attendances'])
            ->orderBy('id')->lazy()->map(fn (TrainingEnrolment $row): array => [
                $row->session->reference, $row->session->course->name, $row->session->start_date->toDateString(), $row->employee->label(),
                $departments->get($row->department_id)?->name ?? '',
                $row->source->label(), $row->status->label(), $row->attendanceRate(), $row->score === null ? '' : (string) $row->score, Money::format((string) $row->cost),
            ]);

        $spreadsheet->write($path, $format, $headers, $rows);

        return response()->download($path, 'training-enrolments-'.now()->format('Ymd').'.'.$format)->deleteFileAfterSend();
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(TrainingEnrolmentStatus::class)],
            'source' => ['nullable', Rule::enum(TrainingEnrolmentSource::class)],
            'training_course_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'mine' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<TrainingEnrolment>
     */
    private function query(array $filters, Request $request, ApprovalService $approvals): Builder
    {
        return TrainingEnrolment::query()
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('employee', fn (Builder $employee) => $employee->search($search)))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['source'] ?? null, fn (Builder $query, string $source) => $query->where('source', $source))
            ->when($filters['department_id'] ?? null, fn (Builder $query, int $department) => $query->where('training_enrolments.department_id', $department))
            ->when($filters['training_course_id'] ?? null, fn (Builder $query, int $course) => $query->whereHas('session', fn (Builder $session) => $session->where('training_course_id', $course)))
            ->when($filters['year'] ?? null, fn (Builder $query, int $year) => $query->whereHas('session', fn (Builder $session) => $session->whereYear('start_date', $year)))
            ->when($filters['mine'] ?? false, fn (Builder $query) => $query->whereIn('id', $approvals->pendingFor($request->user())
                ->where('approvable_type', (new TrainingEnrolment)->getMorphClass())->select('approvable_id')));
    }
}
