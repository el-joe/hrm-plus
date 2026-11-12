<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Performance\ManagePerformanceGoal;
use App\Actions\Performance\ManagePerformanceReview;
use App\Enums\GoalStatus;
use App\Enums\PerformanceReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\GoalProgressRequest;
use App\Http\Requests\Performance\ReviewRatingsRequest;
use App\Http\Resources\V1\PerformanceGoalResource;
use App\Http\Resources\V1\PerformanceReviewResource;
use App\Models\Employee;
use App\Models\PerformanceGoal;
use App\Models\PerformanceReview;
use App\Services\Employees\PortalProfile;
use App\Services\Performance\PortalPerformance;
use App\Support\ApiListQuery;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET  /api/v1/me/performance                              summary + active goals + latest reviews
 * GET  /api/v1/me/performance/goals?filter[status]=&filter[cycle]=&sort=due_date   own goals (paginated)
 * POST /api/v1/me/performance/goals/{id}/progress          {progress} or (KPI) {current_value}, {note}
 * GET  /api/v1/me/performance/reviews?filter[status]=      own reviews (paginated)
 * GET  /api/v1/me/performance/reviews/{id}                 detail: scale, goals, competencies (manager part once approved)
 * POST /api/v1/me/performance/reviews/{id}/self-assessment {goals: {id: {rating, comment}}, competencies: {id: {rating, comment}}, comments}
 * POST /api/v1/me/performance/reviews/{id}/acknowledge     {comment?}
 *
 * Always the token owner's own employee record (others are 404).
 */
class PerformanceController extends Controller
{
    public function __construct(private readonly PortalProfile $profile, private readonly PortalPerformance $performance) {}

    public function summary(Request $request): JsonResponse
    {
        $employee = $this->employee($request);

        return ApiResponse::success([
            'summary' => $this->performance->summary($employee),
            'active_goals' => PerformanceGoalResource::collection(
                $this->performance->goals($employee)->where('status', GoalStatus::Active->value)->limit(20)->get()
            )->resolve(),
            'reviews' => PerformanceReviewResource::collection($this->performance->reviews($employee)->limit(5)->get())->resolve(),
        ], meta: ['employees_can_add_goals' => (bool) setting('performance.employees_can_add_goals')]);
    }

    public function goals(Request $request): JsonResponse
    {
        $query = $this->performance->goals($this->employee($request))->reorder();

        return ApiResponse::paginated(ApiListQuery::for($query, $request)
            ->allowFilters(['status', 'type', 'cycle' => fn (Builder $query, string $value) => $query->where('performance_cycle_id', (int) $value)])
            ->allowSorts(['due_date', 'progress', 'created_at'])
            ->defaultSort('-id')
            ->paginate(), PerformanceGoalResource::class);
    }

    public function progress(GoalProgressRequest $request, int $goal, ManagePerformanceGoal $goals): JsonResponse
    {
        $record = $this->goal($request, $goal);
        abort_unless($this->performance->canEditGoal($record), 403);

        $goals->recordProgress($record, $request->validated('progress'), $request->validated('current_value'), $request->validated('note'), $request->user());

        return ApiResponse::success(new PerformanceGoalResource($this->goal($request, $goal)), __('performance.goals.progress_saved'));
    }

    public function reviews(Request $request): JsonResponse
    {
        $query = $this->performance->reviews($this->employee($request))->reorder();

        return ApiResponse::paginated(ApiListQuery::for($query, $request)
            ->allowFilters(['status' => fn (Builder $query, string $value) => $query->where('status', PerformanceReviewStatus::tryFrom($value)?->value ?? '-')])
            ->allowSorts(['created_at'])
            ->defaultSort('-id')
            ->paginate(), PerformanceReviewResource::class);
    }

    public function review(Request $request, int $review): JsonResponse
    {
        return ApiResponse::success((new PerformanceReviewResource($this->findReview($request, $review)))->withDetail());
    }

    public function selfAssessment(ReviewRatingsRequest $request, int $review, ManagePerformanceReview $manage): JsonResponse
    {
        $manage->submitSelf($this->findReview($request, $review), $request->ratings(), $request->user());

        return ApiResponse::success((new PerformanceReviewResource($this->findReview($request, $review)))->withDetail(), __('performance.portal.self_submitted'));
    }

    public function acknowledge(Request $request, int $review, ManagePerformanceReview $manage): JsonResponse
    {
        $comment = $request->validate(['comment' => ['nullable', 'string', 'max:2000']])['comment'] ?? null;
        $manage->acknowledge($this->findReview($request, $review), $request->user(), $comment);

        return ApiResponse::success((new PerformanceReviewResource($this->findReview($request, $review)))->withDetail(), __('performance.portal.acknowledged'));
    }

    private function goal(Request $request, int $id): PerformanceGoal
    {
        $goal = $this->performance->findGoal($this->employee($request), $id);
        abort_if($goal === null, 404);

        return $goal;
    }

    private function findReview(Request $request, int $id): PerformanceReview
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
