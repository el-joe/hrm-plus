<?php

namespace App\Http\Controllers\Admin\Recruitment;

use App\Actions\Recruitment\ManageInterview;
use App\Actions\Recruitment\SubmitScorecard;
use App\Enums\InterviewDecision;
use App\Enums\InterviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Recruitment\InterviewRequest;
use App\Http\Requests\Admin\Recruitment\ScorecardRequest;
use App\Models\Interview;
use App\Models\JobApplication;
use App\Models\Scopes\DataScope;
use App\Services\Employees\SensitiveDataAccessLog;
use App\Services\Recruitment\RecruitmentFileStore;
use App\Services\Recruitment\RecruitmentOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Interviews in the admin area. Admins without interviews.manage only ever
 * see the interviews they sit on (list, page, CV); InterviewPolicy decides.
 */
class InterviewController extends Controller
{
    public const PER_PAGE = 25;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Interview::class);

        $user = $request->user();
        $canSeeAll = $user->can('interviews.manage');
        $filters = $request->validate([
            'scope' => ['nullable', Rule::in(['mine', 'all'])],
            'status' => ['nullable', Rule::enum(InterviewStatus::class)],
            'when' => ['nullable', Rule::in(['upcoming', 'past'])],
        ]);
        $scope = $canSeeAll ? ($filters['scope'] ?? 'all') : 'mine';

        $interviews = Interview::query()
            ->with(['application.candidate', 'application.vacancy', 'interviewers'])
            ->when($scope === 'mine', fn ($query) => $query->withoutGlobalScope(DataScope::class)->assignedTo($user))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when(($filters['when'] ?? 'upcoming') === 'upcoming',
                fn ($query) => $query->where('scheduled_at', '>=', today()->startOfDay())->orderBy('scheduled_at'),
                fn ($query) => $query->where('scheduled_at', '<', today()->startOfDay())->orderByDesc('scheduled_at'))
            ->paginate(self::PER_PAGE)->withQueryString();

        return view('admin.recruitment.interviews.index', [
            'interviews' => $interviews,
            'filters' => [...$filters, 'scope' => $scope, 'when' => $filters['when'] ?? 'upcoming'],
            'canSeeAll' => $canSeeAll,
        ]);
    }

    public function create(Request $request, JobApplication $application, RecruitmentOptions $options): View
    {
        Gate::authorize('create', Interview::class);
        Gate::authorize('view', $application);

        return view('admin.recruitment.interviews.form', [
            'interview' => new Interview(['duration_minutes' => 60, 'timezone' => (string) setting('localisation.timezone'), 'evaluation_form_id' => $application->vacancy->evaluation_form_id]),
            'application' => $application->load(['candidate', 'vacancy']),
            'options' => $options->interviewForm(),
            'selectedInterviewers' => [$request->user()->id],
        ]);
    }

    public function store(InterviewRequest $request, JobApplication $application, ManageInterview $interviews): RedirectResponse
    {
        $interview = $interviews->schedule($application, $request->payload(), $request->user());

        return redirect()->route('admin.interviews.show', $interview)->with('status', __('recruitment.messages.interview_scheduled'));
    }

    public function show(Request $request, Interview $interview): View
    {
        Gate::authorize('view', $interview);

        $interview->load(['application.candidate', 'application.vacancy', 'application.stage', 'interviewers', 'evaluationForm.criteria', 'scorecards.interviewer', 'scorecards.scores', 'organiser']);
        $user = $request->user();

        return view('admin.recruitment.interviews.show', [
            'interview' => $interview,
            'myScorecard' => $interview->scorecards->firstWhere('user_id', $user->id),
            'canScore' => $user->can('score', $interview),
            'canSeeScorecards' => $user->can('viewScorecards', $interview),
            'canUpdate' => $user->can('update', $interview),
            'canOpenApplication' => $user->can('view', $interview->application),
            'decisions' => InterviewDecision::cases(),
        ]);
    }

    public function edit(Interview $interview, RecruitmentOptions $options): View
    {
        Gate::authorize('update', $interview);
        abort_unless($interview->status === InterviewStatus::Scheduled, 404);

        return view('admin.recruitment.interviews.form', [
            'interview' => $interview,
            'application' => $interview->application->load(['candidate', 'vacancy']),
            'options' => $options->interviewForm(),
            'selectedInterviewers' => $interview->interviewers()->pluck('users.id')->all(),
        ]);
    }

    public function update(InterviewRequest $request, Interview $interview, ManageInterview $interviews): RedirectResponse
    {
        $interviews->update($interview, $request->payload());

        return redirect()->route('admin.interviews.show', $interview)->with('status', __('recruitment.messages.interview_updated'));
    }

    public function status(Request $request, Interview $interview, ManageInterview $interviews): RedirectResponse
    {
        Gate::authorize('update', $interview);
        $data = $request->validate([
            'status' => ['required', Rule::in([InterviewStatus::Completed->value, InterviewStatus::Cancelled->value, InterviewStatus::NoShow->value])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $interviews->setStatus($interview, InterviewStatus::from($data['status']), $data['note'] ?? null);

        return back()->with('status', __('recruitment.messages.interview_status', ['status' => $interview->status->label()]));
    }

    public function decide(Request $request, Interview $interview, ManageInterview $interviews): RedirectResponse
    {
        Gate::authorize('update', $interview);
        $data = $request->validate([
            'decision' => ['required', Rule::enum(InterviewDecision::class)],
            'decision_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $interviews->decide($interview, InterviewDecision::from($data['decision']), $data['decision_note'] ?? null);

        return back()->with('status', __('recruitment.messages.decision_saved'));
    }

    public function scorecard(ScorecardRequest $request, Interview $interview, SubmitScorecard $submit): RedirectResponse
    {
        $submit->handle($interview, $request->user(), $request->validated());

        return redirect()->route('admin.interviews.show', $interview)->with('status', __('recruitment.messages.scorecard_saved'));
    }

    /**
     * The candidate's CV, for interviewers too (who may not see the candidate profile).
     */
    public function cv(Request $request, Interview $interview, RecruitmentFileStore $files, SensitiveDataAccessLog $log): StreamedResponse
    {
        Gate::authorize('view', $interview);
        $candidate = $interview->application->candidate;
        abort_if($candidate->cv_path === null, 404);

        $log->record(SensitiveDataAccessLog::CANDIDATE_FILE, $candidate, ['cv'], ['interview_id' => $interview->id], $request->user());

        return $files->response($candidate->cv_path, $candidate->cv_name ?? 'cv', $candidate->cv_mime ?? 'application/octet-stream');
    }
}
