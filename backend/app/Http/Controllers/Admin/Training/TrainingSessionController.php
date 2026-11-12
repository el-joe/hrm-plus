<?php

namespace App\Http\Controllers\Admin\Training;

use App\Actions\Training\ManageTrainingEnrolment;
use App\Actions\Training\ManageTrainingSession;
use App\Actions\Training\RecordTrainingAttendance;
use App\Enums\TrainingAttendanceStatus;
use App\Enums\TrainingDeliveryMode;
use App\Enums\TrainingEnrolmentStatus;
use App\Enums\TrainingSessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Training\TrainingSessionRequest;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\TrainingCourse;
use App\Models\TrainingEnrolment;
use App\Models\TrainingSession;
use App\Support\IndexSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Training sessions: the schedule, create / edit / cancel / complete, and the session
 * page where participants are nominated, attendance is recorded per day and outcomes
 * (completed / failed / no-show with score) are entered. Participants shown are those
 * inside the admin's data scope; seats are counted company-wide.
 */
class TrainingSessionController extends Controller
{
    public const PER_PAGE = 20;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', TrainingSession::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(TrainingSessionStatus::class)],
            'training_course_id' => ['nullable', 'integer'],
            'when' => ['nullable', 'in:upcoming,past'],
        ]);

        $query = TrainingSession::query()->with('course.provider')
            ->withCount(['enrolments as seats_taken' => fn (Builder $query) => $query->withoutGlobalScopes()->whereIn('status', TrainingEnrolmentStatus::seated())])
            ->withCount(['enrolments as pending_count' => fn (Builder $query) => $query->withoutGlobalScopes()->where('status', TrainingEnrolmentStatus::Pending)])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->whereLike('reference', "%{$search}%")->orWhereHas('course', fn (Builder $course) => $course->search($search))))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['training_course_id'] ?? null, fn (Builder $query, int $course) => $query->where('training_course_id', $course))
            ->when(($filters['when'] ?? null) === 'upcoming', fn (Builder $query) => $query->whereDate('start_date', '>=', today()))
            ->when(($filters['when'] ?? null) === 'past', fn (Builder $query) => $query->whereDate('start_date', '<', today()));

        $sort = IndexSorting::apply($query, $request, ['start_date' => 'start_date', 'reference' => 'reference', 'capacity' => 'capacity'], 'start_date', 'desc');

        return view('admin.training.sessions.index', [
            'sessions' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
            'statuses' => TrainingSessionStatus::cases(),
            'courses' => TrainingCourse::query()->orderByName()->get(),
            'counts' => [
                'upcoming' => TrainingSession::query()->upcoming()->count(),
                'pending' => TrainingEnrolment::query()->where('status', TrainingEnrolmentStatus::Pending)->count(),
                'month' => TrainingSession::query()->where('status', '!=', TrainingSessionStatus::Cancelled)
                    ->whereBetween('start_date', [today()->startOfMonth()->toDateString(), today()->endOfMonth()->toDateString()])->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', TrainingSession::class);

        $course = $request->integer('course') ? TrainingCourse::query()->find($request->integer('course')) : null;

        return $this->form(new TrainingSession([
            'training_course_id' => $course?->id,
            'delivery_mode' => $course?->delivery_mode ?? TrainingDeliveryMode::Classroom,
            'start_date' => today()->addWeek(), 'end_date' => today()->addWeek(), 'start_time' => '09:00', 'end_time' => '16:00', 'capacity' => 15,
        ]));
    }

    public function store(TrainingSessionRequest $request, ManageTrainingSession $sessions): RedirectResponse
    {
        $session = $sessions->create($request->validated(), $request->user());

        return redirect()->route('admin.training-sessions.show', $session)->with('status', __('training.sessions.saved', ['reference' => $session->reference]));
    }

    public function show(Request $request, TrainingSession $trainingSession): View
    {
        Gate::authorize('view', $trainingSession);

        $session = $trainingSession->load(['course.provider', 'course.courseSkills.skill', 'branch', 'creator']);
        $days = $session->days();
        $today = today()->toDateString();
        $defaultDay = in_array($today, $days, true) ? $today : ($days[0] ?? $today);
        $day = in_array($request->query('day'), $days, true) ? (string) $request->query('day') : $defaultDay;

        // Scoped: an admin sees the participants inside their data scope.
        $enrolments = TrainingEnrolment::query()->where('training_session_id', $session->id)
            ->with(['employee.department', 'attendances', 'latestApprovalRequest.currentStep', 'certificate'])
            ->orderByRaw("case status when 'pending' then 0 when 'enrolled' then 1 else 2 end")->orderBy('id')->get();

        $user = $request->user();

        return view('admin.training.sessions.show', [
            'session' => $session,
            'enrolments' => $enrolments,
            'seated' => $enrolments->filter(fn (TrainingEnrolment $row): bool => in_array($row->status, TrainingEnrolmentStatus::seated(), true))->values(),
            'seatsTaken' => $session->seatsTaken(),
            'days' => $days,
            'day' => $day,
            'attendanceStatuses' => TrainingAttendanceStatus::cases(),
            'outcomes' => TrainingEnrolmentStatus::outcomes(),
            'canNominate' => $user->can('nominate', $session),
            'canRecord' => $user->can('recordAttendance', $session),
            'canManage' => $user->can('update', $session),
            'employees' => $user->can('nominate', $session)
                ? Employee::query()->active()->whereNotIn('id', $enrolments->whereNotIn('status', [TrainingEnrolmentStatus::Cancelled, TrainingEnrolmentStatus::Rejected])->pluck('employee_id'))
                    ->orderBy('employee_number')->get(['id', 'employee_number', 'name_ar', 'name_en'])
                : collect(),
            'minAttendance' => (int) setting('training.completion_min_attendance_percent'),
            'currency' => (string) setting('localisation.currency_code'),
        ]);
    }

    public function edit(TrainingSession $trainingSession): View
    {
        Gate::authorize('update', $trainingSession);

        return $this->form($trainingSession);
    }

    public function update(TrainingSessionRequest $request, TrainingSession $trainingSession, ManageTrainingSession $sessions): RedirectResponse
    {
        $sessions->update($trainingSession, $request->validated());

        return redirect()->route('admin.training-sessions.show', $trainingSession)->with('status', __('training.sessions.saved', ['reference' => $trainingSession->reference]));
    }

    public function cancel(Request $request, TrainingSession $trainingSession, ManageTrainingSession $sessions): RedirectResponse
    {
        Gate::authorize('update', $trainingSession);

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $sessions->cancel($trainingSession, $request->user(), $data['reason']);

        return back()->with('status', __('training.sessions.cancelled'));
    }

    public function complete(Request $request, TrainingSession $trainingSession, ManageTrainingSession $sessions): RedirectResponse
    {
        Gate::authorize('update', $trainingSession);

        $sessions->complete($trainingSession, $request->user());

        return back()->with('status', __('training.sessions.completed'));
    }

    /**
     * Nominate one or more employees (HR: enrolled at once; managers: sent for approval).
     */
    public function nominate(Request $request, TrainingSession $trainingSession, ManageTrainingEnrolment $enrolments): RedirectResponse
    {
        Gate::authorize('nominate', $trainingSession);

        $data = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1', 'max:100'],
            'employee_ids.*' => ['integer', 'distinct'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        // Scoped: only employees the admin can see.
        $employees = Employee::query()->whereKey($data['employee_ids'])->get();

        if ($employees->count() !== count($data['employee_ids'])) {
            throw ValidationException::withMessages(['employee_ids' => __('training.validation.employee_out_of_scope')]);
        }

        $done = 0;
        $errors = [];

        foreach ($employees as $employee) {
            try {
                $enrolments->nominate($trainingSession, $employee, $request->user(), $data['reason'] ?? null);
                $done++;
            } catch (ValidationException $exception) {
                $errors[] = $employee->label().': '.collect($exception->errors())->flatten()->first();
            }
        }

        $redirect = back()->with('status', trans_choice('training.sessions.nominated', $done, ['count' => $done]));

        return $errors === [] ? $redirect : $redirect->withErrors(['employee_ids' => $errors]);
    }

    public function attendance(Request $request, TrainingSession $trainingSession, RecordTrainingAttendance $record): RedirectResponse
    {
        Gate::authorize('recordAttendance', $trainingSession);

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'attendance' => ['required', 'array'],
            'attendance.*.status' => ['nullable', Rule::enum(TrainingAttendanceStatus::class)],
            'attendance.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        $count = $record->handle($trainingSession, $data['date'], $data['attendance'], $request->user());

        return redirect()->route('admin.training-sessions.show', [$trainingSession, 'day' => $data['date']])
            ->with('status', trans_choice('training.sessions.attendance_saved', $count, ['count' => $count]));
    }

    /**
     * Record the outcome of several participants at once; rows left empty are skipped.
     */
    public function outcomes(Request $request, TrainingSession $trainingSession, ManageTrainingEnrolment $enrolments): RedirectResponse
    {
        Gate::authorize('recordAttendance', $trainingSession);

        $data = $request->validate([
            'outcomes' => ['required', 'array'],
            'outcomes.*.status' => ['nullable', Rule::in(array_map(fn (TrainingEnrolmentStatus $status): string => $status->value, TrainingEnrolmentStatus::outcomes()))],
            'outcomes.*.score' => ['nullable', 'decimal:0,2', 'between:0,100'],
            'outcomes.*.note' => ['nullable', 'string', 'max:1000'],
        ]);

        $rows = TrainingEnrolment::query()->where('training_session_id', $trainingSession->id)->whereKey(array_keys($data['outcomes']))->get()->keyBy('id');
        $done = 0;
        $errors = [];

        foreach ($data['outcomes'] as $id => $row) {
            $enrolment = $rows->get((int) $id);

            if ($enrolment === null || blank($row['status'] ?? null) || ! $request->user()->can('update', $enrolment)) {
                continue;
            }

            try {
                $enrolments->recordOutcome($enrolment, TrainingEnrolmentStatus::from($row['status']), $row['score'] ?? null, $row['note'] ?? null, $request->user());
                $done++;
            } catch (ValidationException $exception) {
                $errors[] = $enrolment->employee->label().': '.collect($exception->errors())->flatten()->first();
            }
        }

        $redirect = back()->with('status', trans_choice('training.sessions.outcomes_saved', $done, ['count' => $done]));

        return $errors === [] ? $redirect : $redirect->withErrors(['outcomes' => $errors]);
    }

    private function form(TrainingSession $session): View
    {
        return view('admin.training.sessions.form', [
            'session' => $session,
            'courses' => TrainingCourse::query()->activeOrKeep($session->training_course_id)->orderByName()->get(),
            'branches' => Branch::query()->activeOrKeep($session->branch_id)->orderByName()->get(),
            'modes' => TrainingDeliveryMode::cases(),
            'currency' => (string) setting('localisation.currency_code'),
        ]);
    }
}
