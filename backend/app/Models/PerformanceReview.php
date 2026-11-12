<?php

namespace App\Models;

use App\Enums\PerformanceReviewStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasApprovals;
use App\Models\Concerns\ScopedThroughEmployee;
use Database\Factories\PerformanceReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One employee's review in a cycle. Moves through PerformanceReviewStatus via
 * App\Actions\Performance\ManagePerformanceReview only; HR calibration runs
 * through the "performance_review" approval workflow.
 *
 * Reading it (dashboards / reports): a review counts as completed when
 * status is "acknowledgement" or "completed" (approved; the final rating is
 * fixed) — `completed` only means the employee also acknowledged it. Scores
 * are on the cycle's rating scale (goals_score, competencies_score,
 * overall_score); the final band is final_rating_level_id (calibrated = HR
 * overrode the band from the score).
 *
 * Data scope: through the employee. Managers without performance.manage are
 * further limited to their reporting line (App\Services\Performance\PerformanceVisibility).
 */
#[Fillable(['reference', 'performance_cycle_id', 'employee_id', 'reviewer_id', 'branch_id', 'department_id', 'status'])]
class PerformanceReview extends Model
{
    /** @use HasFactory<PerformanceReviewFactory> */
    use Auditable, HasApprovals, HasFactory, ScopedThroughEmployee;

    public const WORKFLOW = 'performance_review';

    /**
     * @var list<string>
     */
    protected array $auditExclude = ['self_comments', 'manager_strengths', 'manager_improvements', 'manager_comments', 'employee_comment'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PerformanceReviewStatus::class,
            'self_goals_score' => 'decimal:2',
            'self_competencies_score' => 'decimal:2',
            'self_overall_score' => 'decimal:2',
            'goals_score' => 'decimal:2',
            'competencies_score' => 'decimal:2',
            'overall_score' => 'decimal:2',
            'calibrated' => 'boolean',
            'self_submitted_at' => 'datetime',
            'manager_submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PerformanceCycle, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycle::class, 'performance_cycle_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<RatingScaleLevel, $this>
     */
    public function finalRating(): BelongsTo
    {
        return $this->belongsTo(RatingScaleLevel::class, 'final_rating_level_id');
    }

    /**
     * @return HasMany<PerformanceReviewCompetency, $this>
     */
    public function competencies(): HasMany
    {
        return $this->hasMany(PerformanceReviewCompetency::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The employee's goals of the review's cycle, counted ones first (not a relation:
     * it depends on two columns of this review, so it cannot be eager-loaded).
     *
     * @return Builder<PerformanceGoal>
     */
    public function goalsQuery(): Builder
    {
        return PerformanceGoal::query()->withoutGlobalScopes()
            ->where('employee_id', $this->employee_id)
            ->where('performance_cycle_id', $this->performance_cycle_id)
            ->orderByRaw("case when status = 'cancelled' then 1 else 0 end")->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function managerSubmitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Approved reviews (final rating fixed), acknowledged or not.
     *
     * @param  Builder<PerformanceReview>  $query
     */
    public function scopeFinalised(Builder $query): void
    {
        $query->whereIn($query->qualifyColumn('status'), [PerformanceReviewStatus::Acknowledgement->value, PerformanceReviewStatus::Completed->value]);
    }

    public function hasStatus(PerformanceReviewStatus ...$statuses): bool
    {
        return in_array($this->status, $statuses, true);
    }
}
