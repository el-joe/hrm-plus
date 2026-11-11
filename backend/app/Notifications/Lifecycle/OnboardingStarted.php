<?php

namespace App\Notifications\Lifecycle;

use App\Models\Onboarding;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To the new employee's own account: your onboarding checklist is ready.
 */
class OnboardingStarted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Onboarding $onboarding) {}

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

        return (new MailMessage)->subject($data['title'])->line($data['message'])->action(__('onboarding.notifications.open'), $data['url']);
    }

    /**
     * @return array{kind: string, onboarding_id: int, title: string, message: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : null;

        return [
            'kind' => 'onboarding_started',
            'onboarding_id' => $this->onboarding->getKey(),
            'title' => __('onboarding.notifications.started_title', [], $locale),
            'message' => __('onboarding.notifications.started_message', [
                'count' => $this->onboarding->tasks()->where('assignee', 'employee')->count(),
            ], $locale),
            'url' => route('portal.onboarding'),
        ];
    }
}
