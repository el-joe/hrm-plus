<?php

namespace App\Notifications\Training;

use App\Models\TrainingCertificate;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A certificate / licence expires soon (training:send-certificate-reminders), database + mail.
 * Sent once per certificate to the employee and to the training managers in scope.
 */
class CertificateExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly TrainingCertificate $certificate) {}

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

        return (new MailMessage)->subject($data['title'])->line($data['message'])
            ->action(__('training.notifications.open', [], $notifiable->locale), $data['url']);
    }

    /**
     * @return array{kind: string, training_certificate_id: int, title: string, message: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : null;
        $certificate = $this->certificate->loadMissing(['employee' => fn ($query) => $query->withoutGlobalScopes()]);
        $own = $notifiable instanceof User && $notifiable->employee_id === $certificate->employee_id;

        return [
            'kind' => 'training_certificate_expiring',
            'training_certificate_id' => $certificate->id,
            'title' => __('training.notifications.certificate_title', [], $locale),
            'message' => __($own ? 'training.notifications.certificate_own_message' : 'training.notifications.certificate_message', [
                'name' => $certificate->name,
                'employee' => $certificate->employee->localized('name', $locale),
                'date' => $certificate->expires_on?->toDateString(),
            ], $locale),
            'url' => $own ? route('portal.training') : route('admin.training-certificates.index', ['employee_id' => $certificate->employee_id]),
        ];
    }
}
