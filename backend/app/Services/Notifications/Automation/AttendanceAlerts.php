<?php

namespace App\Services\Notifications\Automation;

use App\Enums\AttendanceDayStatus;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Notifications\Automation\AutomationNotification;
use App\Services\Attendance\AttendanceClock;
use App\Services\Authorization\DataScopeManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Attendance alerts from processed attendance days (notifications:attendance-alerts, hourly):
 *
 * - attendance_missing_checkout: a check-in without a check-out, notifications.missing_checkout_after_minutes
 *   after the scheduled end (days of the last two dates);
 * - attendance_late_arrival: a processed day with late minutes (today and yesterday);
 * - attendance_absent (employee) + attendance_absent_team (direct manager): yesterday processed as absent.
 *
 * Each day is alerted once per kind. Days only exist once the attendance engine processed them.
 */
class AttendanceAlerts
{
    public function __construct(
        private readonly NotificationDispatches $dispatches,
        private readonly AutomationRecipients $recipients,
        private readonly DataScopeManager $scopes,
    ) {}

    /**
     * @return array{missing_checkout: int, late: int, absent: int}
     */
    public function run(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $counts = ['missing_checkout' => 0, 'late' => 0, 'absent' => 0];

        $this->scopes->withoutScoping(function () use ($now, &$counts): void {
            if (setting('notifications.missing_checkout_enabled')) {
                $cutoff = $now->subMinutes((int) setting('notifications.missing_checkout_after_minutes'));

                $this->days()->whereNotNull('first_in_at')->whereNull('last_out_at')
                    ->whereNotNull('scheduled_end_at')->where('scheduled_end_at', '<=', $cutoff)
                    ->whereDate('work_date', '>=', $now->subDays(2)->toDateString())
                    ->each(function (AttendanceDay $day) use (&$counts): void {
                        $counts['missing_checkout'] += (int) $this->alertEmployee($day, 'attendance_missing_checkout', [
                            'date' => $day->work_date->toDateString(),
                            'time' => AttendanceClock::localTime($day->first_in_at, $day->timezone),
                        ]);
                    });
            }

            if (setting('notifications.late_arrival_enabled')) {
                $this->days()->where('late_minutes', '>', 0)->whereNotNull('first_in_at')
                    ->whereDate('work_date', '>=', $now->subDay()->toDateString())
                    ->each(function (AttendanceDay $day) use (&$counts): void {
                        $counts['late'] += (int) $this->alertEmployee($day, 'attendance_late_arrival', [
                            'date' => $day->work_date->toDateString(),
                            'minutes' => $day->late_minutes,
                            'time' => AttendanceClock::localTime($day->scheduled_start_at, $day->timezone),
                        ]);
                    });
            }

            if (setting('notifications.absence_alerts_enabled')) {
                $this->days()->where('status', AttendanceDayStatus::Absent)
                    ->whereDate('work_date', $now->subDay()->toDateString())
                    ->each(function (AttendanceDay $day) use (&$counts): void {
                        $sent = $this->alertEmployee($day, 'attendance_absent', ['date' => $day->work_date->toDateString()]);
                        $employee = $day->employee;
                        $manager = $this->recipients->accountOf($employee?->manager_id);

                        if ($employee !== null && $manager !== null) {
                            $this->dispatches->once('attendance_absent_team:'.$day->id, 'attendance_absent_team', function () use ($manager, $employee, $day): int {
                                $manager->notify(new AutomationNotification('attendance_absent_team', [
                                    'employee' => ['ar' => $employee->employee_number.' · '.$employee->localized('name', 'ar'), 'en' => $employee->employee_number.' · '.$employee->localized('name', 'en')],
                                    'date' => $day->work_date->toDateString(),
                                ], ['attendance_day_id' => $day->id, 'employee_id' => $employee->id,
                                    'url' => $manager->isAdmin() && $manager->can('attendance.view') ? route('admin.attendance.days.show', $day) : route('portal.attendance')]));

                                return 1;
                            });
                        }

                        $counts['absent'] += (int) $sent;
                    });
            }
        });

        return $counts;
    }

    /**
     * @return Builder<AttendanceDay>
     */
    private function days(): Builder
    {
        return AttendanceDay::query()->withoutGlobalScopes()
            ->with(['employee' => fn ($query) => $query->withoutGlobalScopes()])
            ->whereHas('employee', fn (Builder $employee) => $employee->withoutGlobalScopes()->employed())
            ->orderBy('id');
    }

    /**
     * @param  array<string, scalar|null>  $values
     */
    private function alertEmployee(AttendanceDay $day, string $event, array $values): bool
    {
        /** @var Employee|null $employee */
        $employee = $day->employee;
        $account = $this->recipients->accountOf($employee?->id);

        if ($employee === null || $account === null) {
            return false;
        }

        return $this->dispatches->once($event.':'.$day->id, $event, function () use ($account, $employee, $day, $event, $values): int {
            $account->notify(new AutomationNotification($event, [
                'employee' => ['ar' => $employee->localized('name', 'ar'), 'en' => $employee->localized('name', 'en')],
                ...$values,
            ], ['attendance_day_id' => $day->id, 'url' => route('portal.attendance')]));

            return 1;
        });
    }
}
