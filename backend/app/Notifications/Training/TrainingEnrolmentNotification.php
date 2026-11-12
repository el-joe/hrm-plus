<?php

namespace App\Notifications\Training;

use App\Models\TrainingEnrolment;
use App\Models\User;
use App\Notifications\CentreNotification;

/**
 * Training enrolment events (templates "training_*") to the employee, the requester and approvers.
 */
class TrainingEnrolmentNotification extends CentreNotification
{
    public const KINDS = ['nominated', 'submitted', 'awaiting_approval', 'approved', 'rejected', 'cancelled', 'completed'];

    public function __construct(public readonly TrainingEnrolment $enrolment, public readonly string $kind, public readonly ?string $reason = null) {}

    public function event(): string
    {
        return 'training_'.$this->kind;
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
        $enrolment = $this->enrolment->loadMissing([
            'employee' => fn ($query) => $query->withoutGlobalScopes(),
            'session.course',
        ]);

        return [
            'employee' => $enrolment->employee->localized('name', $locale),
            'course' => $enrolment->session->course->localized('name', $locale),
            'date' => $enrolment->session->start_date->toDateString(),
            'reason' => $this->reason,
        ];
    }

    /**
     * @return array{kind: string, training_enrolment_id: int, reason: ?string, url: string}
     */
    public function payload(object $notifiable): array
    {
        $isAdmin = $notifiable instanceof User && $notifiable->isAdmin() && $notifiable->employee_id !== $this->enrolment->employee_id;

        return [
            'kind' => 'training_'.$this->kind,
            'training_enrolment_id' => $this->enrolment->id,
            'reason' => $this->reason,
            'url' => $isAdmin ? route('admin.training-enrolments.show', $this->enrolment) : route('portal.training'),
        ];
    }
}
