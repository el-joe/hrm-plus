<?php

namespace App\Http\Resources\V1;

use App\Models\PerformanceGoal;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCompetency;
use App\Models\RatingScaleLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The employee's own performance review (mobile app). Scores are DECIMAL strings on the
 * cycle's rating scale. The manager's ratings, comments and scores are only included once
 * HR approved the review ("acknowledgement" / "completed"). The detail endpoint adds
 * "scale", "goals" and "competencies" (pass ['detail' => true] via additional()).
 *
 * @mixin PerformanceReview
 */
class PerformanceReviewResource extends JsonResource
{
    public bool $detail = false;

    public function withDetail(): static
    {
        $this->detail = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $final = $this->status->isFinal();

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'cycle' => [
                'id' => $this->cycle->id,
                'name' => $this->cycle->name,
                'type' => $this->cycle->type->value,
                'type_label' => $this->cycle->type->label(),
                'period_start' => $this->cycle->period_start->toDateString(),
                'period_end' => $this->cycle->period_end->toDateString(),
                'self_assessment_due' => $this->cycle->self_assessment_due?->toDateString(),
            ],
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'reviewer' => $this->reviewer?->name,
            'self_overall_score' => $this->self_overall_score === null ? null : (string) $this->self_overall_score,
            'goals_score' => $final && $this->goals_score !== null ? (string) $this->goals_score : null,
            'competencies_score' => $final && $this->competencies_score !== null ? (string) $this->competencies_score : null,
            'overall_score' => $final && $this->overall_score !== null ? (string) $this->overall_score : null,
            'final_rating' => $final && $this->finalRating ? [
                'value' => (string) $this->finalRating->value, 'label' => $this->finalRating->label, 'color' => $this->finalRating->color,
            ] : null,
            'can_self_assess' => $this->status->value === 'self_assessment',
            'can_acknowledge' => $this->status->value === 'acknowledgement',
            'self_submitted_at' => $this->self_submitted_at?->toIso8601ZuluString(),
            'approved_at' => $this->approved_at?->toIso8601ZuluString(),
            'acknowledged_at' => $this->acknowledged_at?->toIso8601ZuluString(),
            $this->mergeWhen($this->detail, fn (): array => [
                'self_comments' => $this->self_comments,
                'manager_strengths' => $final ? $this->manager_strengths : null,
                'manager_improvements' => $final ? $this->manager_improvements : null,
                'manager_comments' => $final ? $this->manager_comments : null,
                'employee_comment' => $this->employee_comment,
                'scale' => $this->cycle->ratingScale->levels->map(fn (RatingScaleLevel $level): array => [
                    'value' => (string) $level->value, 'label' => $level->label, 'description' => $level->description,
                ])->values()->all(),
                'goals' => $this->goalsQuery()->get()->map(fn (PerformanceGoal $goal): array => [
                    'id' => $goal->id, 'title' => $goal->title, 'type' => $goal->type->value, 'weight' => (string) $goal->weight,
                    'progress' => (string) $goal->progress, 'status' => $goal->status->value, 'counted' => $goal->status->counts(),
                    'self_rating' => $goal->self_rating === null ? null : (string) $goal->self_rating, 'self_comment' => $goal->self_comment,
                    'manager_rating' => $final && $goal->manager_rating !== null ? (string) $goal->manager_rating : null,
                    'manager_comment' => $final ? $goal->manager_comment : null,
                ])->all(),
                'competencies' => $this->competencies->map(fn (PerformanceReviewCompetency $row): array => [
                    'id' => $row->id, 'name' => $row->competency->name, 'category' => $row->competency->category->label(), 'weight' => (string) $row->weight,
                    'self_rating' => $row->self_rating === null ? null : (string) $row->self_rating, 'self_comment' => $row->self_comment,
                    'manager_rating' => $final && $row->manager_rating !== null ? (string) $row->manager_rating : null,
                    'manager_comment' => $final ? $row->manager_comment : null,
                ])->all(),
            ]),
        ];
    }
}
