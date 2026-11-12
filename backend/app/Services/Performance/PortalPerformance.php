<?php

namespace App\Services\Performance;

use App\Enums\GoalStatus;
use App\Enums\PerformanceCycleStatus;
use App\Enums\PerformanceReviewStatus;
use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceGoal;
use App\Models\PerformanceReview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The employee's own goals and reviews for the portal and the mobile API:
 * always the signed-in employee's records, admin data scopes ignored. Reviews
 * still in the manager / calibration steps show the employee's own part only;
 * the manager's ratings and comments appear once HR approved the review.
 */
class PortalPerformance
{
    /**
     * @return Builder<PerformanceGoal>
     */
    public function goals(Employee $employee): Builder
    {
        return PerformanceGoal::query()->withoutGlobalScopes()->where('employee_id', $employee->id)
            ->with('cycle')->orderByRaw("case status when 'active' then 0 when 'completed' then 1 else 2 end")->orderBy('due_date')->orderBy('id');
    }

    public function findGoal(Employee $employee, int $id): ?PerformanceGoal
    {
        return $this->goals($employee)->with('updates')->find($id);
    }

    /**
     * @return Builder<PerformanceReview>
     */
    public function reviews(Employee $employee): Builder
    {
        return PerformanceReview::query()->withoutGlobalScopes()->where('employee_id', $employee->id)
            ->where('status', '!=', PerformanceReviewStatus::Cancelled)
            ->with(['cycle', 'finalRating', 'reviewer' => fn ($query) => $query->withoutGlobalScopes()])->latest('id');
    }

    public function findReview(Employee $employee, int $id): ?PerformanceReview
    {
        return $this->reviews($employee)->with(['competencies.competency', 'cycle.ratingScale.levels'])->find($id);
    }

    /**
     * Active cycles the employee may add goals to.
     *
     * @return Collection<int, PerformanceCycle>
     */
    public function openCycles(Employee $employee): Collection
    {
        $editable = PerformanceReview::query()->withoutGlobalScopes()->where('employee_id', $employee->id)
            ->whereIn('status', [PerformanceReviewStatus::SelfAssessment->value, PerformanceReviewStatus::ManagerAssessment->value])
            ->pluck('performance_cycle_id');

        return PerformanceCycle::query()->where('status', PerformanceCycleStatus::Active)->whereIn('id', $editable)->orderByDesc('period_start')->get();
    }

    /**
     * Whether the employee may still edit a goal (their own, cycle open, review not past assessment).
     */
    public function canEditGoal(PerformanceGoal $goal): bool
    {
        if ($goal->status === GoalStatus::Cancelled) {
            return false;
        }

        if ($goal->performance_cycle_id === null) {
            return true;
        }

        return PerformanceReview::query()->withoutGlobalScopes()->where('employee_id', $goal->employee_id)
            ->where('performance_cycle_id', $goal->performance_cycle_id)
            ->whereNotIn('status', [PerformanceReviewStatus::SelfAssessment->value, PerformanceReviewStatus::ManagerAssessment->value])
            ->doesntExist() && ! $goal->cycle?->isClosed();
    }

    /**
     * Counts for the summary card / API.
     *
     * @return array{active_goals: int, completed_goals: int, average_progress: string, open_reviews: int, to_acknowledge: int, self_assessment_due: int, latest_rating: ?array{value: string, label: ?string, score: ?string, cycle: ?string}}
     */
    public function summary(Employee $employee): array
    {
        $goals = $this->goals($employee)->where('status', '!=', GoalStatus::Cancelled->value)->get();
        $active = $goals->where('status', GoalStatus::Active);
        $reviews = $this->reviews($employee)->get();
        $latest = $reviews->first(fn (PerformanceReview $review): bool => $review->status->isFinal());

        $progress = '0.00';

        if ($active->isNotEmpty()) {
            $sum = $active->reduce(fn (string $carry, PerformanceGoal $goal): string => bcadd($carry, (string) $goal->progress, 2), '0');
            $progress = bcdiv($sum, (string) $active->count(), 2);
        }

        return [
            'active_goals' => $active->count(),
            'completed_goals' => $goals->where('status', GoalStatus::Completed)->count(),
            'average_progress' => $progress,
            'open_reviews' => $reviews->filter(fn (PerformanceReview $review): bool => $review->status->isOpen())->count(),
            'to_acknowledge' => $reviews->where('status', PerformanceReviewStatus::Acknowledgement)->count(),
            'self_assessment_due' => $reviews->where('status', PerformanceReviewStatus::SelfAssessment)->count(),
            'latest_rating' => $latest?->finalRating ? [
                'value' => (string) $latest->finalRating->value,
                'label' => $latest->finalRating->label,
                'score' => $latest->overall_score === null ? null : (string) $latest->overall_score,
                'cycle' => $latest->cycle?->name,
            ] : null,
        ];
    }
}
