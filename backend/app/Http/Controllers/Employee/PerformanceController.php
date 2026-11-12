<?php

namespace App\Http\Controllers\Employee;

use App\Actions\Performance\ManagePerformanceGoal;
use App\Actions\Performance\ManagePerformanceReview;
use App\Enums\GoalType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\GoalProgressRequest;
use App\Http\Requests\Performance\OwnGoalRequest;
use App\Http\Requests\Performance\ReviewRatingsRequest;
use App\Models\Employee;
use App\Models\PerformanceGoal;
use App\Models\PerformanceReview;
use App\Services\Employees\PortalProfile;
use App\Services\Performance\PortalPerformance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "My performance" (spec section 5, Additional pages: performance reviews): own goals
 * and KPIs with progress updates, adding own goals (when allowed), own reviews with the
 * self-assessment and the acknowledgement. Always the signed-in user's own record;
 * anything else is a 404.
 */
class PerformanceController extends Controller
{
    public function __construct(private readonly PortalProfile $profile, private readonly PortalPerformance $performance) {}

    public function index(Request $request): View
    {
        $employee = $this->employee($request);

        return view('employee.performance.index', [
            'employee' => $employee,
            'summary' => $this->performance->summary($employee),
            'goals' => $this->performance->goals($employee)->paginate(15, pageName: 'goals_page')->withQueryString(),
            'reviews' => $this->performance->reviews($employee)->get(),
            'openCycles' => $this->performance->openCycles($employee),
            'canAddGoals' => (bool) setting('performance.employees_can_add_goals'),
            'types' => GoalType::cases(),
            'portal' => $this->performance,
        ]);
    }

    public function storeGoal(OwnGoalRequest $request, ManagePerformanceGoal $goals): RedirectResponse
    {
        $employee = $this->employee($request);
        $this->ensureOpenCycle($employee, $request->validated('performance_cycle_id'));

        $goals->create($employee, $request->validated(), $request->user());

        return redirect()->route('portal.performance')->with('status', __('performance.portal.goal_added'));
    }

    public function progress(GoalProgressRequest $request, int $goal, ManagePerformanceGoal $goals): RedirectResponse
    {
        $record = $this->goal($request, $goal);
        abort_unless($this->performance->canEditGoal($record), 403);

        $goals->recordProgress($record, $request->validated('progress'), $request->validated('current_value'), $request->validated('note'), $request->user());

        return back()->with('status', __('performance.goals.progress_saved'));
    }

    public function showReview(Request $request, int $review): View
    {
        $record = $this->review($request, $review);

        return view('employee.performance.review', [
            'review' => $record,
            'goals' => $record->goalsQuery()->get(),
            'levels' => $record->cycle->ratingScale->levels,
            'showManager' => $record->status->isFinal(),
        ]);
    }

    public function selfAssessment(ReviewRatingsRequest $request, int $review, ManagePerformanceReview $manage): RedirectResponse
    {
        $manage->submitSelf($this->review($request, $review), $request->ratings(), $request->user());

        return redirect()->route('portal.performance.reviews.show', $review)->with('status', __('performance.portal.self_submitted'));
    }

    public function acknowledge(Request $request, int $review, ManagePerformanceReview $manage): RedirectResponse
    {
        $comment = $request->validate(['comment' => ['nullable', 'string', 'max:2000']])['comment'] ?? null;
        $manage->acknowledge($this->review($request, $review), $request->user(), $comment);

        return redirect()->route('portal.performance.reviews.show', $review)->with('status', __('performance.portal.acknowledged'));
    }

    private function ensureOpenCycle(Employee $employee, mixed $cycleId): void
    {
        if (filled($cycleId) && ! $this->performance->openCycles($employee)->contains('id', (int) $cycleId)) {
            throw ValidationException::withMessages(['performance_cycle_id' => __('performance.validation.cycle_not_open')]);
        }
    }

    private function goal(Request $request, int $id): PerformanceGoal
    {
        $goal = $this->performance->findGoal($this->employee($request), $id);
        abort_if($goal === null, 404);

        return $goal;
    }

    private function review(Request $request, int $id): PerformanceReview
    {
        $review = $this->performance->findReview($this->employee($request), $id);
        abort_if($review === null, 404);

        return $review;
    }

    private function employee(Request $request): Employee
    {
        $employee = $this->profile->employeeFor($request->user());
        abort_if($employee === null, 404);

        return $employee;
    }
}
