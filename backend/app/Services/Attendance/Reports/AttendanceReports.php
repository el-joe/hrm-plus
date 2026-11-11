<?php

namespace App\Services\Attendance\Reports;

use App\Enums\AttendanceDayStatus;
use App\Enums\RawEventSource;
use App\Models\AttendanceDay;
use App\Models\AttendanceDeviceSyncLog;
use App\Models\AttendanceRawEvent;
use App\Models\Employee;
use App\Models\User;
use App\Services\Attendance\AttendanceClock;
use App\Services\Attendance\BulkAttendance;
use App\Support\Duration;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The attendance reports: one definition per report (columns, query, row
 * mapping), shared by the screen (paginated) and the Excel / CSV export
 * (every row). Queries run on Eloquent models with data scopes, so a scoped
 * admin only ever sees and exports their own branches / departments.
 *
 * Every figure is read from attendance_days (written only by the engine),
 * raw events or device sync logs — nothing is computed here except sums.
 */
class AttendanceReports
{
    public const REPORTS = ['daily', 'monthly_summary', 'lateness', 'absence', 'overtime', 'manual_entries', 'device_sync'];

    /**
     * Longest range a report may cover, in days.
     */
    public const MAX_RANGE_DAYS = 366;

    public function available(User $user): array
    {
        return array_values(array_filter(self::REPORTS, fn (string $report): bool => $report !== 'device_sync' || $user->can('attendance_devices.view')));
    }

    /**
     * @return array<string, string> column key => label
     */
    public function columns(string $report): array
    {
        $field = fn (string $key): string => __('attendance.fields.'.$key);
        $label = fn (string $key): string => __('attendance_reports.columns.'.$key);
        $employee = ['employee_number' => $field('employee_number'), 'employee' => $field('employee'), 'branch' => $field('branch'), 'department' => $field('department')];

        return match ($report) {
            'daily' => [...$employee, 'date' => $field('date'), 'shift' => $field('shift'), 'check_in' => $field('check_in'), 'check_out' => $field('check_out'),
                'worked' => $field('worked'), 'late' => $field('late'), 'early_leave' => $field('early_leave'), 'overtime' => $field('overtime'),
                'status' => $field('status'), 'source' => $field('source'), 'manual' => $label('manual')],
            'monthly_summary' => [...$employee, 'scheduled_days' => $label('scheduled_days'), 'present_days' => $label('present_days'), 'late_days' => $label('late_days'),
                'absent_days' => $label('absent_days'), 'leave_days' => $label('leave_days'), 'incomplete_days' => $label('incomplete_days'),
                'worked' => $field('worked'), 'late' => $field('late'), 'early_leave' => $field('early_leave'), 'overtime' => $field('overtime'),
                'overtime_approved' => $field('overtime_approved'), 'manual_days' => $label('manual_days')],
            'lateness' => [...$employee, 'date' => $field('date'), 'shift' => $field('shift'), 'scheduled_start' => $label('scheduled_start'),
                'check_in' => $field('check_in'), 'late' => $field('late'), 'status' => $field('status')],
            'absence' => [...$employee, 'date' => $field('date'), 'shift' => $field('shift'), 'status' => $field('status'), 'manual' => $label('manual'), 'flags' => $label('flags')],
            'overtime' => [...$employee, 'date' => $field('date'), 'day_type' => $label('day_type'), 'overtime' => $field('overtime'),
                'overtime_status' => $label('overtime_status'), 'overtime_approved' => $field('overtime_approved'), 'rate' => $label('rate'), 'policy' => $field('policy')],
            'manual_entries' => [...$employee, 'date' => $field('date'), 'event' => $field('event'), 'source' => $field('source'),
                'recorded_by' => $label('recorded_by'), 'recorded_at' => $label('recorded_at'), 'event_status' => $field('event_status'), 'note' => $label('note')],
            'device_sync' => ['device' => $label('device'), 'serial' => $label('serial'), 'type' => $label('sync_type'), 'status' => $field('status'),
                'started_at' => $label('started_at'), 'fetched' => $label('fetched'), 'created' => $label('created'), 'duplicates' => $label('duplicates'),
                'unmatched' => $label('unmatched'), 'errors' => $label('errors'), 'simulated' => $label('simulated'), 'message' => $label('message')],
        };
    }

    /**
     * Columns whose values are numbers (right-aligned on screen, totals).
     *
     * @return list<string>
     */
    public function numeric(string $report): array
    {
        return match ($report) {
            'monthly_summary' => ['scheduled_days', 'present_days', 'late_days', 'absent_days', 'leave_days', 'incomplete_days', 'manual_days'],
            'device_sync' => ['fetched', 'created', 'duplicates', 'unmatched', 'errors'],
            default => [],
        };
    }

    /**
     * @param  array{from: string, to: string, branch_id?: ?int, department_id?: ?int, search?: ?string}  $filters
     * @return Builder<Model>
     */
    public function query(string $report, array $filters): Builder
    {
        return match ($report) {
            'daily' => $this->days($filters)->orderBy('attendance_days.work_date')->orderBy('employees.employee_number'),
            'lateness' => $this->days($filters)->where('attendance_days.late_minutes', '>', 0)->orderBy('attendance_days.work_date')->orderBy('employees.employee_number'),
            'absence' => $this->days($filters)->where('attendance_days.status', AttendanceDayStatus::Absent)->orderBy('attendance_days.work_date')->orderBy('employees.employee_number'),
            'overtime' => $this->days($filters)->where('attendance_days.overtime_minutes', '>', 0)->with('policyVersion.policy')->orderBy('attendance_days.work_date')->orderBy('employees.employee_number'),
            'monthly_summary' => $this->summary($filters),
            'manual_entries' => $this->manual($filters),
            'device_sync' => $this->sync($filters),
        };
    }

    /**
     * @return array<string, scalar|null>
     */
    public function row(string $report, mixed $record): array
    {
        if ($report === 'device_sync') {
            /** @var AttendanceDeviceSyncLog $record */
            return [
                'device' => $record->device?->name, 'serial' => $record->device?->serial_number, 'type' => $record->type->label(),
                'status' => $record->status->label(), 'started_at' => $record->started_at->setTimezone($this->timezone())->format('Y-m-d H:i'),
                'fetched' => $record->fetched, 'created' => $record->created, 'duplicates' => $record->duplicates, 'unmatched' => $record->unmatched,
                'errors' => $record->errors, 'simulated' => $record->is_simulated ? __('devices.simulated.badge') : '', 'message' => $record->message,
            ];
        }

        if ($report === 'manual_entries') {
            /** @var AttendanceRawEvent $record */
            $tz = $record->timezone;

            return [
                ...$this->employeeCells($record->employee),
                'date' => $record->work_date?->format('Y-m-d') ?? AttendanceClock::localDate($record->occurred_at, $tz)->format('Y-m-d'),
                'event' => $record->kind->label().': '.($record->kind->value === 'punch'
                    ? $record->direction?->label().' '.AttendanceClock::localTime($record->occurred_at, $tz)
                    : ($record->attendanceStatus?->name ?? __('attendance.days.mark_cleared'))),
                'source' => $record->source->label(),
                'recorded_by' => $record->recorder?->name,
                'recorded_at' => $record->created_at->setTimezone($this->timezone())->format('Y-m-d H:i'),
                'event_status' => $record->status->label(),
                'note' => $record->note,
            ];
        }

        if ($report === 'monthly_summary') {
            return [
                'employee_number' => $record->employee_number, 'employee' => $record->employee_name, 'branch' => $record->branch_code, 'department' => $record->department_code,
                'scheduled_days' => (int) $record->scheduled_days, 'present_days' => (int) $record->present_days, 'late_days' => (int) $record->late_days,
                'absent_days' => (int) $record->absent_days, 'leave_days' => (int) $record->leave_days, 'incomplete_days' => (int) $record->incomplete_days,
                'worked' => Duration::format((int) $record->worked_minutes, '0:00'), 'late' => Duration::format((int) $record->late_minutes, '0:00'),
                'early_leave' => Duration::format((int) $record->early_leave_minutes, '0:00'), 'overtime' => Duration::format((int) $record->overtime_minutes, '0:00'),
                'overtime_approved' => Duration::format((int) $record->overtime_approved_minutes, '0:00'), 'manual_days' => (int) $record->manual_days,
            ];
        }

        /** @var AttendanceDay $record */
        $tz = $record->timezone;
        $base = [...$this->employeeCells($record->employee), 'date' => $record->work_date->format('Y-m-d'), 'shift' => $record->shift?->code];

        return match ($report) {
            'daily' => [...$base,
                'check_in' => AttendanceClock::localTime($record->first_in_at, $tz), 'check_out' => AttendanceClock::localTime($record->last_out_at, $tz),
                'worked' => Duration::format($record->worked_minutes, '0:00'), 'late' => Duration::format($record->late_minutes, '0:00'),
                'early_leave' => Duration::format($record->early_leave_minutes, '0:00'), 'overtime' => Duration::format($record->overtime_minutes, '0:00'),
                'status' => $record->statusLabel(),
                'source' => implode(', ', array_map(fn (string $source): string => RawEventSource::from($source)->label(), $record->sources ?? [])),
                'manual' => $record->is_manual ? __('attendance.common.manual') : ''],
            'lateness' => [...$base, 'scheduled_start' => AttendanceClock::localTime($record->scheduled_start_at, $tz),
                'check_in' => AttendanceClock::localTime($record->first_in_at, $tz), 'late' => Duration::format($record->late_minutes, '0:00'), 'status' => $record->statusLabel()],
            'absence' => [...$base, 'status' => $record->statusLabel(), 'manual' => $record->is_manual ? __('attendance.common.manual') : '',
                'flags' => implode(', ', array_map(fn (string $flag): string => __('attendance.flags.'.$flag), array_diff($record->flags ?? [], ['manual'])))],
            'overtime' => [...$base, 'day_type' => __('attendance_reports.day_types.'.$this->dayType($record)),
                'overtime' => Duration::format($record->overtime_minutes, '0:00'), 'overtime_status' => $record->overtime_status->label(),
                'overtime_approved' => Duration::format($record->overtime_approved_minutes, '0:00'), 'rate' => $this->rate($record),
                'policy' => $record->policyVersion ? $record->policyVersion->policy->code.' v'.$record->policyVersion->version : __('attendance.days.settings_defaults')],
        };
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function defaultRange(): array
    {
        $today = BulkAttendance::today();

        return [$today->startOfMonth(), $today];
    }

    private function dayType(AttendanceDay $day): string
    {
        return match (true) {
            $day->holiday_id !== null => 'holiday',
            $day->is_rest_day => 'rest_day',
            default => 'regular',
        };
    }

    /**
     * The overtime multiplier of the day's policy version (for payroll, Phase 7).
     */
    private function rate(AttendanceDay $day): ?string
    {
        $rates = $day->calculation['policy']['rules'] ?? [];
        $key = 'overtime_rate_'.$this->dayType($day);

        return isset($rates[$key]) ? (string) $rates[$key] : ($day->policyVersion?->{$key} !== null ? (string) $day->policyVersion->{$key} : null);
    }

    /**
     * @return array{employee_number: ?string, employee: ?string, branch: ?string, department: ?string}
     */
    private function employeeCells(?Employee $employee): array
    {
        return [
            'employee_number' => $employee?->employee_number, 'employee' => $employee?->name,
            'branch' => $employee?->branch?->code, 'department' => $employee?->department?->code,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<AttendanceDay>
     */
    private function days(array $filters): Builder
    {
        return AttendanceDay::query()
            ->with(['employee.branch', 'employee.department', 'shift', 'attendanceStatus'])
            ->join('employees', 'employees.id', '=', 'attendance_days.employee_id')
            ->select('attendance_days.*')
            ->whereBetween('attendance_days.work_date', [$filters['from'], $filters['to']])
            ->when($filters['branch_id'] ?? null, fn (Builder $query, $id) => $query->where('attendance_days.branch_id', $id))
            ->when($filters['department_id'] ?? null, fn (Builder $query, $id) => $query->where('attendance_days.department_id', $id))
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->whereHas('employee', fn (Builder $query) => $query->search($term)));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<AttendanceDay>
     */
    private function summary(array $filters): Builder
    {
        $status = fn (string ...$values): string => "sum(case when attendance_days.status in ('".implode("','", $values)."') then 1 else 0 end)";
        $name = app()->getLocale() === 'ar' ? 'employees.name_ar' : 'employees.name_en';

        return $this->days($filters)
            ->leftJoin('branches', 'branches.id', '=', 'attendance_days.branch_id')
            ->leftJoin('departments', 'departments.id', '=', 'attendance_days.department_id')
            ->setEagerLoads([])
            ->select([
                'attendance_days.employee_id', 'employees.employee_number', DB::raw("max({$name}) as employee_name"),
                DB::raw('max(branches.code) as branch_code'), DB::raw('max(departments.code) as department_code'),
                DB::raw($status('present', 'late', 'absent', 'incomplete', 'on_leave').' as scheduled_days'),
                DB::raw($status('present').' as present_days'), DB::raw($status('late').' as late_days'), DB::raw($status('absent').' as absent_days'),
                DB::raw($status('on_leave').' as leave_days'), DB::raw($status('incomplete').' as incomplete_days'),
                DB::raw('sum(attendance_days.worked_minutes) as worked_minutes'), DB::raw('sum(attendance_days.late_minutes) as late_minutes'),
                DB::raw('sum(attendance_days.early_leave_minutes) as early_leave_minutes'), DB::raw('sum(attendance_days.overtime_minutes) as overtime_minutes'),
                DB::raw("sum(case when attendance_days.overtime_status = 'approved' then coalesce(attendance_days.overtime_approved_minutes, 0) else 0 end) as overtime_approved_minutes"),
                DB::raw('sum(case when attendance_days.is_manual then 1 else 0 end) as manual_days'),
            ])
            ->groupBy('attendance_days.employee_id', 'employees.employee_number')
            ->orderBy('employees.employee_number');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<AttendanceRawEvent>
     */
    private function manual(array $filters): Builder
    {
        $tz = $this->timezone();

        return AttendanceRawEvent::query()
            ->with(['employee.branch', 'employee.department', 'recorder', 'attendanceStatus'])
            ->whereIn('source', [RawEventSource::Manual, RawEventSource::Import])
            ->where(fn (Builder $query) => $query
                ->whereBetween('work_date', [$filters['from'], $filters['to']])
                ->orWhere(fn (Builder $query) => $query->whereNull('work_date')->whereBetween('occurred_at', [
                    CarbonImmutable::parse($filters['from'], $tz)->utc(), CarbonImmutable::parse($filters['to'], $tz)->addDay()->utc(),
                ])))
            ->whereHas('employee', fn (Builder $query) => $query
                ->when($filters['branch_id'] ?? null, fn (Builder $query, $id) => $query->where('branch_id', $id))
                ->when($filters['department_id'] ?? null, fn (Builder $query, $id) => $query->where('department_id', $id))
                ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->search($term)))
            ->orderBy('occurred_at')->orderBy('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<AttendanceDeviceSyncLog>
     */
    private function sync(array $filters): Builder
    {
        $tz = $this->timezone();

        return AttendanceDeviceSyncLog::query()->with('device')
            ->whereBetween('started_at', [CarbonImmutable::parse($filters['from'], $tz)->utc(), CarbonImmutable::parse($filters['to'], $tz)->addDay()->utc()])
            ->when($filters['branch_id'] ?? null, fn (Builder $query, $id) => $query->whereHas('device', fn (Builder $query) => $query->where('branch_id', $id)))
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->whereHas('device', fn (Builder $query) => $query
                ->whereLike('name', "%{$term}%")->orWhereLike('serial_number', "%{$term}%")))
            ->latest('started_at')->latest('id');
    }

    private function timezone(): string
    {
        return (string) setting('localisation.timezone');
    }
}
