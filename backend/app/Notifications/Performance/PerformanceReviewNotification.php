<?php

namespace App\Notifications\Performance;

use App\Models\PerformanceReview;
use App\Models\User;
use App\Notifications\CentreNotification;

/**
 * Performance review events (templates "performance_*") to the employee, the reviewer and HR.
 */
class PerformanceReviewNotification extends CentreNotification
{
    public const KINDS = ['launched', 'launched_reviewer', 'self_submitted', 'awaiting_approval', 'approved', 'returned', 'acknowledgement_reminder'];

    public function __construct(public readonly PerformanceReview $review, public readonly string $kind, public readonly ?string $reason = null) {}

    public function event(): string
    {
        return 'performance_'.$this->kind;
    }

    protected function actionLabelKey(): string
    {
        return 'performance.notifications.open';
    }

    /**
     * @return array<string, scalar|null>
     */
    public function placeholders(object $notifiable, ?string $locale): array
    {
        $review = $this->review->loadMissing([
            'employee' => fn ($query) => $query->withoutGlobalScopes(),
            'cycle',
        ]);

        return [
            'employee' => $review->employee->localized('name', $locale),
            'cycle' => $review->cycle->localized('name', $locale),
            'reason' => $this->reason,
        ];
    }

    /**
     * @return array{kind: string, performance_review_id: int, reason: ?string, url: string}
     */
    public function payload(object $notifiable): array
    {
        $isAdmin = $notifiable instanceof User && $notifiable->isAdmin() && $notifiable->employee_id !== $this->review->employee_id;

        return [
            'kind' => 'performance_'.$this->kind,
            'performance_review_id' => $this->review->id,
            'reason' => $this->reason,
            'url' => $isAdmin ? route('admin.performance-reviews.show', $this->review) : route('portal.performance.reviews.show', $this->review->id),
        ];
    }
}
