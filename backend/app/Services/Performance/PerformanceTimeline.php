<?php

namespace App\Services\Performance;

use App\Enums\PerformanceReviewStatus;
use App\Models\Employee;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Services\Employees\Contracts\TimelineSource;
use Illuminate\Support\Collection;

/**
 * Employee history: approved performance reviews (date of approval, final rating,
 * score). Only for admins who may see this employee's performance.
 */
class PerformanceTimeline implements TimelineSource
{
    public function __construct(private readonly PerformanceVisibility $visibility) {}

    public function entries(User $user, Employee $employee): Collection
    {
        if (! $user->can('performance.view') || ! $this->visibility->canSeeEmployee($user, $employee)) {
            return collect();
        }

        return PerformanceReview::query()->where('employee_id', $employee->id)->finalised()->whereNotNull('approved_at')
            ->with(['cycle', 'finalRating'])->get()
            ->map(fn (PerformanceReview $review): array => [
                'date' => $review->approved_at,
                'kind' => 'event',
                'id' => $review->id,
                'module' => 'performance',
                'title' => __('performance.timeline.review', ['cycle' => $review->cycle->name]),
                'description' => $review->overall_score !== null
                    ? __('performance.timeline.score', ['score' => $review->overall_score, 'max' => $review->cycle->ratingScale?->levels()->max('value')])
                    : null,
                'url' => route('admin.performance-reviews.show', $review),
                'tone' => $review->status === PerformanceReviewStatus::Completed ? 'success' : 'primary',
                'badge' => $review->finalRating?->label,
            ]);
    }
}
