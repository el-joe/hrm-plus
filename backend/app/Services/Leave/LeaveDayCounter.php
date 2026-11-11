<?php

namespace App\Services\Leave;

use App\Enums\LeaveDayPart;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Services\Attendance\Data\ResolvedSchedule;
use App\Services\Attendance\Data\ScheduledShift;
use App\Services\Attendance\ScheduleResolver;
use App\Services\Leave\Data\LeaveCount;
use App\Services\Settings\SettingsService;
use Illuminate\Validation\ValidationException;

/**
 * Counts the days a leave request charges, one row per calendar day, using
 * the employee's own schedule (ScheduleResolver):
 *
 * - a working day counts 1 (a half day 0.5);
 * - a rest day (no shift by the schedule) counts only when the leave type
 *   counts rest days; a public holiday only when it counts holidays;
 * - hours are charged as a fraction of the day's scheduled hours (the
 *   shifts' expected minutes, else setting payroll.working_hours_per_day);
 * - every day of the period must fall inside employment.
 */
class LeaveDayCounter
{
    public function __construct(
        private readonly ScheduleResolver $schedules,
        private readonly LeaveYear $years,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @throws ValidationException when a date is outside employment
     */
    public function count(Employee $employee, LeaveType $type, string $start, string $end, LeaveDayPart $part = LeaveDayPart::Full, ?string $hours = null): LeaveCount
    {
        $days = [];
        $total = '0.00';
        $byYear = [];

        foreach ($this->schedules->forRange($employee, $start, $end) as $date => $resolved) {
            if (! $resolved->isEmployed()) {
                throw ValidationException::withMessages(['start_date' => __('leave.validation.not_employed', ['date' => $date])]);
            }

            $kind = $resolved->holiday !== null ? 'holiday' : ($resolved->isRestDay ? 'rest_day' : 'working');
            $counted = match ($kind) {
                'holiday' => $type->counts_holidays,
                'rest_day' => $type->counts_rest_days,
                default => true,
            };
            $amount = $counted ? $this->amount($resolved, $part, $hours) : '0.00';
            $year = $this->years->of($date);

            $days[] = [
                'date' => $date, 'leave_year' => $year, 'day_part' => $part->value, 'amount' => $amount,
                'hours' => $part === LeaveDayPart::Hours ? $this->decimal($hours ?? '0') : null,
                'is_counted' => $counted, 'day_kind' => $kind,
            ];
            $total = bcadd($total, $amount, 2);
            $byYear[$year] = bcadd($byYear[$year] ?? '0.00', $amount, 2);
        }

        return new LeaveCount($days, $total, $byYear);
    }

    /**
     * Scheduled hours of the date (decimal string).
     */
    public function hoursPerDay(ResolvedSchedule $resolved): string
    {
        $minutes = array_sum(array_map(fn (ScheduledShift $shift): int => $shift->expectedMinutes, $resolved->shifts));

        return $minutes > 0
            ? bcdiv((string) $minutes, '60', 4)
            : $this->decimal((string) $this->settings->get('payroll.working_hours_per_day'), 4);
    }

    private function amount(ResolvedSchedule $resolved, LeaveDayPart $part, ?string $hours): string
    {
        return match ($part) {
            LeaveDayPart::Full => '1.00',
            LeaveDayPart::FirstHalf, LeaveDayPart::SecondHalf => '0.50',
            LeaveDayPart::Hours => $this->round(bcdiv($this->decimal($hours ?? '0', 4), $this->hoursPerDay($resolved), 6)),
        };
    }

    /**
     * Round half up to 2 decimals, capped at one day.
     */
    private function round(string $value): string
    {
        $rounded = bcdiv(bcadd(bcmul($value, '100', 6), '0.5', 6), '1', 0);
        $result = bcdiv($rounded, '100', 2);

        return bccomp($result, '1.00', 2) > 0 ? '1.00' : $result;
    }

    private function decimal(string $value, int $scale = 2): string
    {
        return bcadd(is_numeric($value) ? $value : '0', '0', $scale);
    }
}
