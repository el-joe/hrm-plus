<?php

namespace App\Services\Notifications\Automation;

use App\Models\Company;
use App\Models\Employee;
use App\Notifications\Automation\AutomationNotification;
use App\Services\Authorization\DataScopeManager;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Daily people events (notifications:people-events):
 *
 * - birthday_greeting to the employee on the day (setting notifications.birthday_greetings_enabled);
 * - birthday_upcoming to the manager and HR (employees.manage in scope) notifications.birthday_notice_days
 *   ahead (0 = on the day);
 * - work_anniversary to the employee and work_anniversary_team to the manager / HR on each full year of
 *   service since the hire date (notifications.work_anniversary_enabled);
 * - probation_ending to the manager / HR notifications.probation_reminder_days before probation_end_date.
 *
 * Each is sent once per employee and year (NotificationDispatches). 29 February birthdays are celebrated
 * on 28 February in non-leap years.
 */
class PeopleEvents
{
    public function __construct(
        private readonly NotificationDispatches $dispatches,
        private readonly AutomationRecipients $recipients,
        private readonly DataScopeManager $scopes,
    ) {}

    /**
     * @return array{birthdays: int, upcoming_birthdays: int, anniversaries: int, probation: int}
     */
    public function run(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $counts = ['birthdays' => 0, 'upcoming_birthdays' => 0, 'anniversaries' => 0, 'probation' => 0];
        $company = Company::query()->first();
        $companyName = ['ar' => $company?->name_ar ?: config('app.name'), 'en' => $company?->name_en ?: config('app.name')];
        $noticeDate = $today->addDays((int) setting('notifications.birthday_notice_days'));
        $probationDate = $today->addDays((int) setting('notifications.probation_reminder_days'));

        $this->scopes->withoutScoping(function () use ($today, $noticeDate, $probationDate, $companyName, &$counts): void {
            Employee::query()->withoutGlobalScopes()->employed()->orderBy('id')
                ->each(function (Employee $employee) use ($today, $noticeDate, $probationDate, $companyName, &$counts): void {
                    $names = ['ar' => $employee->localized('name', 'ar'), 'en' => $employee->localized('name', 'en')];
                    $label = ['ar' => $employee->employee_number.' · '.$names['ar'], 'en' => $employee->employee_number.' · '.$names['en']];
                    $url = fn (bool $admin): string => $admin ? route('admin.employees.show', $employee) : route('portal.profile');

                    if ($employee->date_of_birth !== null) {
                        if (setting('notifications.birthday_greetings_enabled') && $this->isAnniversaryOn($employee->date_of_birth, $today)) {
                            $counts['birthdays'] += (int) $this->dispatches->once('birthday:'.$today->year.':'.$employee->id, 'birthday_greeting', function () use ($employee, $names, $companyName, $url): int {
                                $account = $this->recipients->accountOf($employee->id);
                                $account?->notify(new AutomationNotification('birthday_greeting', ['employee' => $names, 'company' => $companyName], ['employee_id' => $employee->id, 'url' => $url(false)]));

                                return $account ? 1 : 0;
                            });
                        }

                        if ($this->isAnniversaryOn($employee->date_of_birth, $noticeDate)) {
                            $counts['upcoming_birthdays'] += (int) $this->dispatches->once('birthday_notice:'.$noticeDate->year.':'.$employee->id, 'birthday_upcoming', function () use ($employee, $label, $noticeDate, $url): int {
                                $users = $this->recipients->overseersOf($employee, 'employees.manage');

                                foreach ($users as $user) {
                                    $user->notify(new AutomationNotification('birthday_upcoming', ['employee' => $label, 'date' => $noticeDate->toDateString()], ['employee_id' => $employee->id, 'url' => $url($user->isAdmin())]));
                                }

                                return $users->count();
                            });
                        }
                    }

                    if (setting('notifications.work_anniversary_enabled') && $employee->hire_date !== null && $employee->hire_date->lt($today)
                        && $this->isAnniversaryOn($employee->hire_date, $today)) {
                        $years = $today->year - $employee->hire_date->year;

                        if ($years >= 1) {
                            $counts['anniversaries'] += (int) $this->dispatches->once('anniversary:'.$today->year.':'.$employee->id, 'work_anniversary', function () use ($employee, $names, $label, $years, $today, $companyName, $url): int {
                                $sent = 0;
                                $account = $this->recipients->accountOf($employee->id);

                                if ($account !== null) {
                                    $account->notify(new AutomationNotification('work_anniversary', ['employee' => $names, 'company' => $companyName, 'years' => $years, 'date' => $today->toDateString()], ['employee_id' => $employee->id, 'url' => $url(false)]));
                                    $sent++;
                                }

                                foreach ($this->recipients->overseersOf($employee, 'employees.manage') as $user) {
                                    $user->notify(new AutomationNotification('work_anniversary_team', ['employee' => $label, 'years' => $years, 'date' => $today->toDateString()], ['employee_id' => $employee->id, 'url' => $url($user->isAdmin())]));
                                    $sent++;
                                }

                                return $sent;
                            });
                        }
                    }

                    if ($employee->probation_end_date !== null && $employee->probation_end_date->isSameDay($probationDate)) {
                        $counts['probation'] += (int) $this->dispatches->once('probation:'.$employee->id.':'.$employee->probation_end_date->toDateString(), 'probation_ending', function () use ($employee, $label, $today, $url): int {
                            $users = $this->recipients->overseersOf($employee, 'employees.update');

                            foreach ($users as $user) {
                                $user->notify(new AutomationNotification('probation_ending', [
                                    'employee' => $label, 'date' => $employee->probation_end_date->toDateString(), 'days' => (int) $today->diffInDays($employee->probation_end_date),
                                ], ['employee_id' => $employee->id, 'url' => $url($user->isAdmin())]));
                            }

                            return $users->count();
                        });
                    }
                });
        });

        return $counts;
    }

    /**
     * Whether the yearly anniversary of a date falls on the given day (29 Feb → 28 Feb in common years).
     */
    public function isAnniversaryOn(DateTimeInterface $date, CarbonImmutable $day): bool
    {
        $date = CarbonImmutable::instance($date);

        if ($date->month === 2 && $date->day === 29 && ! $day->isLeapYear()) {
            return $day->month === 2 && $day->day === 28;
        }

        return $date->month === $day->month && $date->day === $day->day;
    }
}
