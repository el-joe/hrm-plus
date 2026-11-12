<?php

namespace App\Notifications\Training;

use App\Models\TrainingCertificate;
use App\Models\User;
use App\Notifications\CentreNotification;
use App\Services\Notifications\NotificationRouter;
use App\Services\Notifications\NotificationTemplates;

/**
 * A training certificate expires within the reminder window: to the employee (template
 * "training_certificate_expiring_own") and to training managers ("training_certificate_expiring").
 */
class CertificateExpiringNotification extends CentreNotification
{
    public function __construct(public readonly TrainingCertificate $certificate) {}

    public function event(): string
    {
        return 'training_certificate_expiring';
    }

    /**
     * The employee's own copy uses its own template.
     */
    public function eventFor(object $notifiable): string
    {
        return $this->isOwn($notifiable) ? 'training_certificate_expiring_own' : 'training_certificate_expiring';
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return app(NotificationRouter::class)->channelsFor($notifiable, $this->eventFor($notifiable));
    }

    protected function actionLabelKey(): string
    {
        return 'training.notifications.open';
    }

    /**
     * @return array<string, scalar|null>
     */
    public function placeholders(object $notifiable, ?string $locale): array
    {
        $certificate = $this->certificate->loadMissing(['employee' => fn ($query) => $query->withoutGlobalScopes()]);

        return [
            'name' => $certificate->name,
            'employee' => $certificate->employee->localized('name', $locale),
            'date' => $certificate->expires_on?->toDateString(),
        ];
    }

    /**
     * @return array{kind: string, training_certificate_id: int, url: string}
     */
    public function payload(object $notifiable): array
    {
        return [
            'kind' => 'training_certificate_expiring',
            'training_certificate_id' => $this->certificate->id,
            'url' => $this->isOwn($notifiable)
                ? route('portal.training')
                : route('admin.training-certificates.index', ['employee_id' => $this->certificate->employee_id]),
        ];
    }

    /**
     * @return array{subject: string, body: string}
     */
    protected function rendered(object $notifiable, string $channel): array
    {
        $locale = $this->localeOf($notifiable);

        return app(NotificationTemplates::class)->render($this->eventFor($notifiable), $channel, $locale, [
            'recipient' => $notifiable instanceof User ? $notifiable->name : '',
            'app_name' => (string) config('app.name'),
            ...$this->placeholders($notifiable, $locale),
        ]);
    }

    private function isOwn(object $notifiable): bool
    {
        return $notifiable instanceof User && $notifiable->employee_id === $this->certificate->employee_id;
    }
}
