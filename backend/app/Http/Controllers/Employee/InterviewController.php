<?php

namespace App\Http\Controllers\Employee;

use App\Actions\Recruitment\SubmitScorecard;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Recruitment\ScorecardRequest;
use App\Models\Interview;
use App\Services\Employees\SensitiveDataAccessLog;
use App\Services\Recruitment\RecruitmentFileStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Interviews an employee sits on as an interviewer (portal): the list, the
 * interview with the candidate's CV, and their own scorecard. They never see
 * other interviewers' scorecards or any salary.
 */
class InterviewController extends Controller
{
    public function index(Request $request): View
    {
        return view('employee.interviews.index', [
            'interviews' => Interview::query()->assignedTo($request->user())
                ->with(['application.candidate', 'application.vacancy', 'scorecards' => fn ($query) => $query->where('user_id', $request->user()->id)])
                ->orderByDesc('scheduled_at')->paginate(20),
        ]);
    }

    public function show(Request $request, Interview $assignedInterview): View
    {
        Gate::authorize('view', $assignedInterview);

        $assignedInterview->load(['application.candidate', 'application.vacancy', 'interviewers', 'evaluationForm.criteria']);

        return view('employee.interviews.show', [
            'interview' => $assignedInterview,
            'myScorecard' => $assignedInterview->scorecards()->with('scores')->where('user_id', $request->user()->id)->first(),
        ]);
    }

    public function scorecard(ScorecardRequest $request, Interview $assignedInterview, SubmitScorecard $submit): RedirectResponse
    {
        $submit->handle($assignedInterview, $request->user(), $request->validated());

        return redirect()->route('portal.interviews.show', $assignedInterview)->with('status', __('recruitment.messages.scorecard_saved'));
    }

    public function cv(Request $request, Interview $assignedInterview, RecruitmentFileStore $files, SensitiveDataAccessLog $log): StreamedResponse
    {
        Gate::authorize('view', $assignedInterview);
        $candidate = $assignedInterview->application->candidate;
        abort_if($candidate->cv_path === null, 404);

        $log->record(SensitiveDataAccessLog::CANDIDATE_FILE, $candidate, ['cv'], ['interview_id' => $assignedInterview->id, 'channel' => 'portal'], $request->user());

        return $files->response($candidate->cv_path, $candidate->cv_name ?? 'cv', $candidate->cv_mime ?? 'application/octet-stream');
    }
}
