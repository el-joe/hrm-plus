<?php

use App\Models\Employee;
use App\Models\TrainingEnrolment;
use App\Models\TrainingSession;
use App\Notifications\Automation\AutomationNotification;
use App\Services\Notifications\Automation\AttendanceAlerts;
use App\Services\Notifications\Automation\PeopleEvents;
use App\Services\Notifications\Automation\TrainingReminders;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/NotificationHelpers.php';

/*
| Scheduled automations (Phase 8b): birthdays, work anniversaries, probation end, attendance alerts
| (missing check-out, late arrival, absence), training reminders — each sent once.
*/

beforeEach(function () {
    seedRoles();
    Notification::fake();
    $this->today = CarbonImmutable::parse('2026-11-12');
});

function sentEvent(object $user, string $event): bool
{
    return Notification::sent($user, AutomationNotification::class)->contains(fn (AutomationNotification $n): bool => $n->eventKey === $event);
}

it('greets an employee on their birthday and tells the manager and HR ahead, once', function () {
    setSetting('notifications.birthday_notice_days', 3);
    $manager = employeeAccount();
    $hr = adminWithRoles('HR Manager');
    $birthdayToday = employeeAccount(['date_of_birth' => '1990-11-12', 'manager_id' => $manager->employee_id]);
    $birthdaySoon = Employee::factory()->create(['date_of_birth' => '1992-11-15', 'manager_id' => $manager->employee_id]);

    $counts = app(PeopleEvents::class)->run($this->today);
    app(PeopleEvents::class)->run($this->today);

    expect($counts['birthdays'])->toBe(1)->and($counts['upcoming_birthdays'])->toBe(1)
        ->and(sentEvent($birthdayToday, 'birthday_greeting'))->toBeTrue()
        ->and(sentEvent($manager, 'birthday_upcoming'))->toBeTrue()
        ->and(sentEvent($hr, 'birthday_upcoming'))->toBeTrue();
    Notification::assertSentToTimes($birthdayToday, AutomationNotification::class, 1);
});

it('celebrates 29 February birthdays on 28 February in common years', function () {
    $events = app(PeopleEvents::class);

    expect($events->isAnniversaryOn(CarbonImmutable::parse('2000-02-29'), CarbonImmutable::parse('2027-02-28')))->toBeTrue()
        ->and($events->isAnniversaryOn(CarbonImmutable::parse('2000-02-29'), CarbonImmutable::parse('2028-02-28')))->toBeFalse();
});

it('sends work anniversaries from the hire date, not in the first year', function () {
    $veteran = employeeAccount(['hire_date' => '2021-11-12', 'original_hire_date' => '2021-11-12', 'date_of_birth' => null]);
    $newcomer = employeeAccount(['hire_date' => '2026-11-12', 'original_hire_date' => '2026-11-12', 'date_of_birth' => null]);

    app(PeopleEvents::class)->run($this->today);

    expect(sentEvent($veteran, 'work_anniversary'))->toBeTrue()->and(sentEvent($newcomer, 'work_anniversary'))->toBeFalse();
    Notification::assertSent(AutomationNotification::class, fn (AutomationNotification $n): bool => $n->eventKey === 'work_anniversary' && $n->values['years'] === 5);
});

it('reminds the manager and HR before a probation period ends', function () {
    setSetting('notifications.probation_reminder_days', 14);
    $manager = employeeAccount();
    $hr = adminWithRoles('HR Specialist');
    Employee::factory()->create(['manager_id' => $manager->employee_id, 'probation_end_date' => '2026-11-26', 'date_of_birth' => null]);

    expect(app(PeopleEvents::class)->run($this->today)['probation'])->toBe(1)
        ->and(sentEvent($manager, 'probation_ending'))->toBeTrue()
        ->and(sentEvent($hr, 'probation_ending'))->toBeTrue();
});

it('reminds employees of a missing check-out after the scheduled end, once', function () {
    $user = employeeAccount();
    $employee = $user->employee;
    payrollAttendanceDay($employee, '2026-11-12', 'incomplete', [
        'first_in_at' => '2026-11-12 06:00:00', 'last_out_at' => null, 'scheduled_end_at' => '2026-11-12 14:00:00',
    ]);
    setSetting('notifications.missing_checkout_after_minutes', 120);

    expect(app(AttendanceAlerts::class)->run(CarbonImmutable::parse('2026-11-12 15:00:00'))['missing_checkout'])->toBe(0);
    expect(app(AttendanceAlerts::class)->run(CarbonImmutable::parse('2026-11-12 16:30:00'))['missing_checkout'])->toBe(1);
    expect(app(AttendanceAlerts::class)->run(CarbonImmutable::parse('2026-11-12 17:30:00'))['missing_checkout'])->toBe(0);
    expect(sentEvent($user, 'attendance_missing_checkout'))->toBeTrue();
});

it('tells employees about late arrivals and employees and managers about absences', function () {
    $manager = employeeAccount();
    $user = employeeAccount(['manager_id' => $manager->employee_id]);
    payrollAttendanceDay($user->employee, '2026-11-12', 'late', ['first_in_at' => '2026-11-12 06:25:00', 'scheduled_start_at' => '2026-11-12 06:00:00', 'late_minutes' => 25]);
    payrollAttendanceDay($user->employee, '2026-11-11', 'absent');

    $counts = app(AttendanceAlerts::class)->run(CarbonImmutable::parse('2026-11-12 10:00:00'));

    expect($counts['late'])->toBe(1)->and($counts['absent'])->toBe(1)
        ->and(sentEvent($user, 'attendance_late_arrival'))->toBeTrue()
        ->and(sentEvent($user, 'attendance_absent'))->toBeTrue()
        ->and(sentEvent($manager, 'attendance_absent_team'))->toBeTrue();
});

it('switches attendance alerts off through the settings', function () {
    setSetting('notifications.late_arrival_enabled', false);
    $user = employeeAccount();
    payrollAttendanceDay($user->employee, '2026-11-12', 'late', ['first_in_at' => '2026-11-12 06:25:00', 'late_minutes' => 25]);

    expect(app(AttendanceAlerts::class)->run(CarbonImmutable::parse('2026-11-12 10:00:00'))['late'])->toBe(0);
});

it('reminds enrolled employees of a session starting soon', function () {
    setSetting('notifications.training_reminder_days', 2);
    $user = employeeAccount();
    $session = TrainingSession::factory()->create(['start_date' => '2026-11-14', 'end_date' => '2026-11-14']);
    TrainingEnrolment::factory()->create(['training_session_id' => $session->id, 'employee_id' => $user->employee_id, 'status' => 'enrolled']);

    expect(app(TrainingReminders::class)->run($this->today))->toBe(1)
        ->and(app(TrainingReminders::class)->run($this->today))->toBe(0)
        ->and(sentEvent($user, 'training_session_reminder'))->toBeTrue();
});

it('registers every automation command and schedules it', function () {
    foreach (['notifications:people-events', 'notifications:attendance-alerts', 'notifications:training-reminders', 'notifications:approvals-digest', 'system:purge-retention'] as $command) {
        $this->artisan($command)->assertSuccessful();
    }

    $this->artisan('schedule:list')->assertSuccessful()->expectsOutputToContain('notifications:attendance-alerts');
});
