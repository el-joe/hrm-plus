<?php

namespace App\Services\Payroll;

use App\Enums\AttendanceDayStatus;
use App\Enums\OvertimeStatus;
use App\Enums\ReviewStatus;
use App\Models\AttendanceAdjustment;
use App\Models\AttendanceDay;
use App\Models\AttendanceMobileAttempt;
use App\Models\AttendancePolicyVersion;
use Carbon\CarbonInterface;

/**
 * The attendance quantities payroll needs for one employee and a date range,
 * read from the processed days (written only by the attendance engine):
 *
 * - figures of a day = the day row + its non-superseded attendance_adjustments
 *   (days of a locked period are frozen; later changes are adjustments), the
 *   status = the last adjustment's status_after ("removed" drops the day);
 * - late / early-leave minutes, worked minutes;
 * - absent days (a date with several shifts counts the absent share of its rows);
 * - attended, holiday and paid-leave days (daily pay basis);
 * - overtime: approved minutes only (overtime_status = approved), grouped by
 *   kind with the multiplier of the day's attendance policy version
 *   (overtime_rate_holiday when the day is a holiday, _rest_day on a rest day,
 *   otherwise _regular);
 * - for warnings: incomplete days, overtime still pending, and mobile check-ins
 *   awaiting review (not recorded, so not attended until approved).
 *
 * Data scopes are ignored (payroll decides its population). All numbers are
 * bcmath strings.
 */
class AttendanceQuantities
{
    /**
     * @return array{
     *     days: int, late_minutes: string, early_leave_minutes: string, worked_minutes: string, absent_days: string,
     *     attended_days: string, holiday_days: string, leave_days: string, incomplete_days: int,
     *     overtime: list<array{kind: string, hours: string, multiplier: string, policy_version_id: ?int}>,
     *     overtime_pending_days: int, mobile_pending_review: int, adjusted_days: int, policy_version_ids: list<int>
     * }
     */
    public function between(int $employeeId, CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        $start = is_string($from) ? $from : $from->format('Y-m-d');
        $end = is_string($to) ? $to : $to->format('Y-m-d');

        $days = AttendanceDay::query()->withoutGlobalScopes()
            ->where('employee_id', $employeeId)
            ->whereBetween('work_date', [$start, $end])
            ->with(['adjustments'])
            ->orderBy('work_date')->orderBy('shift_key')
            ->get();

        $versions = AttendancePolicyVersion::query()
            ->whereIn('id', $days->pluck('attendance_policy_version_id')->filter()->unique()->all())
            ->get()->keyBy('id');

        $result = [
            'days' => 0, 'late_minutes' => '0', 'early_leave_minutes' => '0', 'worked_minutes' => '0', 'absent_days' => '0',
            'attended_days' => '0', 'holiday_days' => '0', 'leave_days' => '0', 'incomplete_days' => 0, 'overtime' => [],
            'overtime_pending_days' => 0, 'mobile_pending_review' => 0, 'adjusted_days' => 0, 'policy_version_ids' => [],
        ];
        $overtime = [];

        foreach ($days->groupBy(fn (AttendanceDay $day): string => $day->work_date->format('Y-m-d')) as $rows) {
            $effective = $rows->map(fn (AttendanceDay $day): ?array => $this->effective($day))->filter()->values();

            if ($effective->isEmpty()) {
                continue;
            }

            $result['days']++;
            $share = bcdiv('1', (string) $effective->count(), PayrollMath::SCALE);

            foreach ($effective as $row) {
                $result['late_minutes'] = bcadd($result['late_minutes'], (string) $row['late_minutes'], 0);
                $result['early_leave_minutes'] = bcadd($result['early_leave_minutes'], (string) $row['early_leave_minutes'], 0);
                $result['worked_minutes'] = bcadd($result['worked_minutes'], (string) $row['worked_minutes'], 0);
                $result['adjusted_days'] += $row['adjusted'] ? 1 : 0;

                $bucket = match ($row['status']) {
                    AttendanceDayStatus::Absent->value => 'absent_days',
                    AttendanceDayStatus::Present->value, AttendanceDayStatus::Late->value => 'attended_days',
                    AttendanceDayStatus::Holiday->value => 'holiday_days',
                    AttendanceDayStatus::OnLeave->value => 'leave_days',
                    default => null,
                };

                if ($bucket !== null) {
                    $result[$bucket] = bcadd($result[$bucket], $share, PayrollMath::SCALE);
                }

                if ($row['status'] === AttendanceDayStatus::Incomplete->value) {
                    $result['incomplete_days']++;
                }

                /** @var AttendanceDay $day */
                $day = $row['day'];

                if ($day->overtime_status === OvertimeStatus::Pending) {
                    $result['overtime_pending_days']++;
                }

                if ($day->attendance_policy_version_id !== null) {
                    $result['policy_version_ids'][] = $day->attendance_policy_version_id;
                }

                if ($day->overtime_status !== OvertimeStatus::Approved || (int) $day->overtime_approved_minutes <= 0) {
                    continue;
                }

                $version = $versions->get($day->attendance_policy_version_id);
                [$kind, $multiplier] = $this->overtimeKind($day, $version);
                $key = $kind.'|'.$multiplier;
                $overtime[$key] ??= ['kind' => $kind, 'minutes' => '0', 'multiplier' => $multiplier, 'policy_version_id' => $version?->id];
                $overtime[$key]['minutes'] = bcadd($overtime[$key]['minutes'], (string) $day->overtime_approved_minutes, 0);
            }
        }

        foreach (['absent_days', 'attended_days', 'holiday_days', 'leave_days'] as $key) {
            $result[$key] = PayrollMath::round($result[$key], 4);
        }

        $result['overtime'] = array_values(array_map(fn (array $row): array => [
            'kind' => $row['kind'],
            'hours' => bcdiv($row['minutes'], '60', 4),
            'multiplier' => $row['multiplier'],
            'policy_version_id' => $row['policy_version_id'],
        ], $overtime));
        $result['policy_version_ids'] = array_values(array_unique($result['policy_version_ids']));

        $result['mobile_pending_review'] = AttendanceMobileAttempt::query()->withoutGlobalScopes()
            ->where('employee_id', $employeeId)
            ->whereBetween('work_date', [$start, $end])
            ->where('review_status', ReviewStatus::Pending)
            ->count();

        return $result;
    }

    /**
     * Figures of the day after its adjustments, or null when an adjustment removed it.
     *
     * @return array{day: AttendanceDay, status: string, late_minutes: int, early_leave_minutes: int, worked_minutes: int, adjusted: bool}|null
     */
    private function effective(AttendanceDay $day): ?array
    {
        $adjustments = $day->adjustments;
        $status = $adjustments->last()?->status_after ?? $day->status->value;

        if ($status === 'removed') {
            return null;
        }

        $sum = fn (string $delta): int => (int) $adjustments->sum(fn (AttendanceAdjustment $row): int => (int) $row->{$delta});

        return [
            'day' => $day,
            'status' => $status,
            'late_minutes' => max(0, $day->late_minutes + $sum('late_minutes_delta')),
            'early_leave_minutes' => max(0, $day->early_leave_minutes + $sum('early_leave_minutes_delta')),
            'worked_minutes' => max(0, $day->worked_minutes + $sum('worked_minutes_delta')),
            'adjusted' => $adjustments->isNotEmpty(),
        ];
    }

    /**
     * @return array{0: string, 1: string} kind (holiday | rest_day | regular) and multiplier
     */
    private function overtimeKind(AttendanceDay $day, ?AttendancePolicyVersion $version): array
    {
        $fallback = (string) setting('payroll.overtime_rate_multiplier');

        if ($day->holiday_id !== null) {
            return ['holiday', (string) ($version?->overtime_rate_holiday ?? $fallback)];
        }

        if ($day->is_rest_day) {
            return ['rest_day', (string) ($version?->overtime_rate_rest_day ?? $fallback)];
        }

        return ['regular', (string) ($version?->overtime_rate_regular ?? $fallback)];
    }
}
