<?php

namespace App\Services\Notifications\Automation;

use App\Enums\TrainingEnrolmentStatus;
use App\Models\TrainingEnrolment;
use App\Notifications\Automation\AutomationNotification;
use App\Services\Authorization\DataScopeManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Training starts soon" (training_session_reminder) to employees enrolled in a session starting in
 * notifications.training_reminder_days days (notifications:training-reminders, daily; once per enrolment).
 */
class TrainingReminders
{
    public function __construct(
        private readonly NotificationDispatches $dispatches,
        private readonly AutomationRecipients $recipients,
        private readonly DataScopeManager $scopes,
    ) {}

    public function run(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $date = $today->addDays((int) setting('notifications.training_reminder_days'))->toDateString();
        $count = 0;

        $this->scopes->withoutScoping(function () use ($date, &$count): void {
            TrainingEnrolment::query()->withoutGlobalScopes()->where('status', TrainingEnrolmentStatus::Enrolled)
                ->whereHas('session', fn (Builder $session) => $session->whereDate('start_date', $date))
                ->with(['session.course', 'employee' => fn ($query) => $query->withoutGlobalScopes()])
                ->orderBy('id')
                ->each(function (TrainingEnrolment $enrolment) use (&$count): void {
                    $account = $this->recipients->accountOf($enrolment->employee_id);

                    if ($account === null) {
                        return;
                    }

                    $session = $enrolment->session;
                    $where = $session->location ?: ($session->meeting_url ? 'online' : '—');
                    $when = $session->start_date->toDateString().($session->start_time ? ' '.substr((string) $session->start_time, 0, 5) : '');

                    $count += (int) $this->dispatches->once('training_reminder:'.$enrolment->id.':'.$session->start_date->toDateString(), 'training_session_reminder',
                        function () use ($account, $enrolment, $session, $where, $when): int {
                            $account->notify(new AutomationNotification('training_session_reminder', [
                                'employee' => ['ar' => $enrolment->employee->localized('name', 'ar'), 'en' => $enrolment->employee->localized('name', 'en')],
                                'course' => ['ar' => $session->course->localized('name', 'ar'), 'en' => $session->course->localized('name', 'en')],
                                'date' => $when,
                                'location' => $where,
                            ], ['training_enrolment_id' => $enrolment->id, 'url' => route('portal.training')]));

                            return 1;
                        });
                });
        });

        return $count;
    }
}
