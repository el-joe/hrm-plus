<?php

namespace App\Notifications\Lifecycle;

use App\Models\OffboardingCase;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To HR admins: an offboarding case was opened automatically (contract expiry).
 */
class OffboardingCaseOpened extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly OffboardingCase $case) {}

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
     * @return array{kind: string, offboarding_case_id: int, employee_id: int, title: string, message: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : null;
        $employee = $this->case->employee()->withoutGlobalScopes()->firstOrFail();

        return [
            'kind' => 'offboarding_opened',
            'offboarding_case_id' => $this->case->getKey(),
            'employee_id' => $employee->getKey(),
            'title' => __('onboarding.notifications.offboarding_title', [], $locale),
            'message' => __('onboarding.notifications.offboarding_message', [
                'employee' => $employee->employee_number.' · '.$employee->localized('name', $locale),
                'date' => $this->case->last_working_date->toDateString(),
            ], $locale),
            'url' => route('admin.offboarding.show', $this->case),
        ];
    }
}
