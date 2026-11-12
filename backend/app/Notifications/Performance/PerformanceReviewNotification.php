<?php

namespace App\Notifications\Performance;

use App\Models\PerformanceReview;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Performance review events, database + mail (mail when setting notifications.email_enabled):
 *
 * - to the employee: launched (self-assessment open), approved (please acknowledge),
 *   acknowledgement_reminder;
 * - to the reviewer's admin account(s): self_submitted (ready for the manager assessment),
 *   returned (calibration sent it back), launched_reviewer (reviews to assess, sent when
 *   self-assessment is off);
 * - to approvers: awaiting_approval.
 *
 * Admin recipients get the admin review page, employees the portal review page.
 */
class PerformanceReviewNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const KINDS = ['launched', 'launched_reviewer', 'self_submitted', 'awaiting_approval', 'approved', 'returned', 'acknowledgement_reminder'];

    public function __construct(public readonly PerformanceReview $review, public readonly string $kind, public readonly ?string $reason = null) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return setting('notifications.email_enabled') ? ['database', 'mail'] : ['database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);
        $mail = (new MailMessage)->subject($data['title'])->line($data['message']);

        if ($this->reason) {
            $mail->line(__('performance.notifications.reason', ['reason' => $this->reason], $notifiable->locale));
        }

        return $mail->action(__('performance.notifications.open', [], $notifiable->locale), $data['url']);
    }

    /**
     * @return array{kind: string, performance_review_id: int, title: string, message: string, reason: ?string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : null;
        $review = $this->review->loadMissing([
            'employee' => fn ($query) => $query->withoutGlobalScopes(),
            'cycle',
        ]);
        $isAdmin = $notifiable instanceof User && $notifiable->isAdmin() && $notifiable->employee_id !== $review->employee_id;

        return [
            'kind' => 'performance_'.$this->kind,
            'performance_review_id' => $review->id,
            'title' => __('performance.notifications.'.$this->kind.'_title', [], $locale),
            'message' => __('performance.notifications.'.$this->kind.'_message', [
                'employee' => $review->employee->localized('name', $locale),
                'cycle' => $review->cycle->localized('name', $locale),
            ], $locale),
            'reason' => $this->reason,
            'url' => $isAdmin ? route('admin.performance-reviews.show', $review) : route('portal.performance.reviews.show', $review->id),
        ];
    }
}
