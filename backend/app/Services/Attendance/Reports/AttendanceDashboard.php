<?php

namespace App\Services\Attendance\Reports;

use App\Enums\AttendanceDayStatus;
use App\Enums\CorrectionStatus;
use App\Enums\DeviceHealth;
use App\Enums\ManualBatchStatus;
use App\Enums\MobileAttemptOutcome;
use App\Enums\OvertimeStatus;
use App\Enums\RawEventStatus;
use App\Enums\ReviewStatus;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceDay;
use App\Models\AttendanceDevice;
use App\Models\AttendanceManualBatch;
use App\Models\AttendanceMobileAttempt;
use App\Models\AttendancePunch;
use App\Models\AttendanceRawEvent;
use App\Models\User;
use App\Services\Attendance\BulkAttendance;
use App\Services\Attendance\Data\ScheduledShift;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Figures of the attendance dashboard, from live queries and inside the
 * admin's data scope (Employee / AttendanceDay / device scopes apply):
 *
 * Every employee SCHEDULED to work on the date (ScheduleResolver, the same
 * list the bulk screen uses) is counted once:
 *   on_leave   a leave day or a leave-type status
 *   late       processed as late
 *   present    processed as present, or checked in without check-out yet
 *   absent     processed as absent, or (past dates / shift already over) no attendance
 *   not_in     today, nothing recorded yet and the shift is not over
 */
class AttendanceDashboard
{
    public const CATEGORIES = ['present', 'late', 'absent', 'on_leave', 'not_in'];

    public function __construct(private readonly BulkAttendance $bulk) {}

    /**
     * @param  array{date: string, branch_id?: ?int, department_id?: ?int}  $filters
     * @return array<string, mixed>
     */
    public function build(User $admin, array $filters): array
    {
        $date = $filters['date'];
        $isToday = $date === BulkAttendance::today()->format('Y-m-d');
        $now = CarbonImmutable::now();
        $totals = array_fill_keys(self::CATEGORIES, 0);
        $byBranch = [];
        $byDepartment = [];
        $notIn = [];

        foreach ($this->bulk->eligible(['date' => $date, 'branch_id' => $filters['branch_id'] ?? null, 'department_id' => $filters['department_id'] ?? null]) as $row) {
            $category = $this->classify($row['days'], $row['shifts'], $isToday, $now);
            $totals[$category]++;

            $employee = $row['employee'];
            $this->add($byBranch, (string) ($employee->branch?->code ?? '—'), $employee->branch?->name ?? '—', $category);
            $this->add($byDepartment, (string) ($employee->department?->code ?? '—'), $employee->department?->name ?? '—', $category);

            if ($category === 'not_in' && count($notIn) < 50) {
                $notIn[] = ['employee' => $employee, 'start' => $row['shifts'][0]->startAt ?? null, 'timezone' => $row['days'][0]->timezone ?? null];
            }
        }

        $expected = array_sum($totals);
        ksort($byBranch);
        ksort($byDepartment);

        return [
            'date' => $date,
            'is_today' => $isToday,
            'expected' => $expected,
            'totals' => $totals,
            'attendance_rate' => $expected > 0 ? (int) round(($totals['present'] + $totals['late']) * 100 / $expected) : null,
            'by_branch' => array_values($byBranch),
            'by_department' => array_values($byDepartment),
            'not_in' => $notIn,
            'manual_days' => AttendanceDay::query()->whereDate('work_date', $date)->where('is_manual', true)
                ->when($filters['branch_id'] ?? null, fn (Builder $query, $id) => $query->where('branch_id', $id))
                ->when($filters['department_id'] ?? null, fn (Builder $query, $id) => $query->where('department_id', $id))
                ->count(),
            'latest_punches' => $this->latestPunches($filters),
            'devices' => $admin->can('attendance_devices.view') ? $this->devices() : null,
            'pending' => $this->pending($admin),
        ];
    }

    /**
     * @param  list<AttendanceDay>  $days
     * @param  list<ScheduledShift>  $shifts
     */
    public function classify(array $days, array $shifts, bool $isToday, CarbonImmutable $now): string
    {
        $statuses = collect($days)->map(fn (AttendanceDay $day): AttendanceDayStatus => $day->status);

        if ($statuses->contains(AttendanceDayStatus::OnLeave)) {
            return 'on_leave';
        }

        if ($statuses->contains(AttendanceDayStatus::Late)) {
            return 'late';
        }

        if ($statuses->contains(AttendanceDayStatus::Present) || collect($days)->contains(fn (AttendanceDay $day): bool => $day->first_in_at !== null)) {
            return 'present';
        }

        $lastEnd = collect($shifts)->max(fn ($shift) => $shift->endAt);

        if ($isToday && ($lastEnd === null || $now->lt($lastEnd))) {
            return 'not_in';
        }

        return 'absent';
    }

    /**
     * @param  array<string, array<string, mixed>>  $groups
     */
    private function add(array &$groups, string $key, string $label, string $category): void
    {
        $groups[$key] ??= ['code' => $key, 'name' => $label, 'expected' => 0, ...array_fill_keys(self::CATEGORIES, 0)];
        $groups[$key]['expected']++;
        $groups[$key][$category]++;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, AttendancePunch>
     */
    private function latestPunches(array $filters): Collection
    {
        return AttendancePunch::query()
            ->with(['employee.branch', 'rawEvent'])
            ->whereHas('employee', fn (Builder $query) => $query
                ->when($filters['branch_id'] ?? null, fn (Builder $query, $id) => $query->where('branch_id', $id))
                ->when($filters['department_id'] ?? null, fn (Builder $query, $id) => $query->where('department_id', $id)))
            ->where('punched_at', '>=', now()->subDay())
            ->latest('punched_at')->latest('id')
            ->limit(15)
            ->get();
    }

    /**
     * @return array{total: int, by_health: array<string, int>, problems: Collection<int, AttendanceDevice>}
     */
    private function devices(): array
    {
        $devices = AttendanceDevice::query()->with('branch')->orderBy('name')->get();
        $byHealth = array_fill_keys(array_column(DeviceHealth::cases(), 'value'), 0);

        foreach ($devices as $device) {
            $byHealth[$device->health()->value]++;
        }

        return [
            'total' => $devices->count(),
            'by_health' => $byHealth,
            'problems' => $devices->filter(fn (AttendanceDevice $device): bool => in_array($device->health(), [DeviceHealth::Error, DeviceHealth::Warning, DeviceHealth::Unknown], true))->values(),
            'simulated' => $devices->filter->isSimulated()->count(),
        ];
    }

    /**
     * @return array<string, int|null>
     */
    private function pending(User $admin): array
    {
        return [
            'reviews' => AttendanceMobileAttempt::query()->where('outcome', MobileAttemptOutcome::Flagged)->where('review_status', ReviewStatus::Pending)->count(),
            'corrections' => AttendanceCorrection::query()->where('status', CorrectionStatus::Pending)->count(),
            'batches' => AttendanceManualBatch::query()->where('status', ManualBatchStatus::PendingApproval)->count(),
            'overtime' => AttendanceDay::query()->where('overtime_status', OvertimeStatus::Pending)->whereDate('work_date', '>=', now()->subDays(62)->toDateString())->count(),
            'unmatched' => AttendanceRawEvent::query()->where('status', RawEventStatus::Unmatched)->count(),
        ];
    }
}
