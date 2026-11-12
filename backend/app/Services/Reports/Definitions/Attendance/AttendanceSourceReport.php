<?php

namespace App\Services\Reports\Definitions\Attendance;

use App\Enums\RawEventSource;
use App\Models\AttendanceRawEvent;
use App\Models\Branch;
use App\Models\User;
use App\Services\Attendance\Reports\AttendanceReports;
use App\Services\Reports\Column;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use App\Support\Charts\ChartData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Attendance by capture source (device, mobile GPS, manual, import): raw events, employees and
 * attendance days per source and branch in the period (the pinned work date of the event, else its
 * local day in the company timezone). Voided events are excluded.
 */
class AttendanceSourceReport extends Report
{
    public function key(): string
    {
        return 'attendance_source';
    }

    public function area(): string
    {
        return 'attendance';
    }

    public function maxRangeDays(): int
    {
        return AttendanceReports::MAX_RANGE_DAYS;
    }

    public function columns(): array
    {
        return [
            'source' => Column::text($this->label('source')),
            'branch' => Column::text($this->label('branch')),
            'events' => Column::number($this->label('events')),
            'employees' => Column::number($this->label('employees'), false),
            'days' => Column::number($this->label('days')),
            'share' => Column::percent($this->label('share')),
        ];
    }

    public function hasChart(): bool
    {
        return true;
    }

    public function query(array $filters, User $user): Collection
    {
        // Device and mobile punches carry no work date (only pinned manual entries and day marks do):
        // those count by their local day in the company timezone.
        $timezone = (string) setting('localisation.timezone');
        $fromUtc = CarbonImmutable::parse($filters['from'], $timezone)->startOfDay()->utc();
        $toUtc = CarbonImmutable::parse($filters['to'], $timezone)->addDay()->startOfDay()->utc();
        $offset = (int) CarbonImmutable::parse($filters['from'], $timezone)->utcOffset();
        $localDay = "coalesce(attendance_raw_events.work_date, date(date_add(attendance_raw_events.occurred_at, interval {$offset} minute)))";

        $query = AttendanceRawEvent::query()
            ->join('employees', 'employees.id', '=', 'attendance_raw_events.employee_id')
            ->where(fn ($query) => $query->whereBetween('attendance_raw_events.work_date', [$filters['from'], $filters['to']])
                ->orWhere(fn ($query) => $query->whereNull('attendance_raw_events.work_date')
                    ->where('attendance_raw_events.occurred_at', '>=', $fromUtc)->where('attendance_raw_events.occurred_at', '<', $toUtc)))
            ->whereNull('attendance_raw_events.voided_at')
            ->select('attendance_raw_events.source', 'employees.branch_id')
            ->selectRaw('count(*) as events, count(distinct attendance_raw_events.employee_id) as employees')
            ->selectRaw("count(distinct attendance_raw_events.employee_id, {$localDay}) as days")
            ->groupBy('attendance_raw_events.source', 'employees.branch_id');

        $rows = ReportFilters::applyEmployeeFilters($query, 'attendance_raw_events.employee_id', $filters)->toBase()->get();
        $branches = Branch::query()->withoutGlobalScopes()->whereKey($rows->pluck('branch_id')->filter()->all())->get()->keyBy('id');
        $total = max(1, (int) $rows->sum('events'));

        return $rows->map(fn (object $row): array => [
            'source' => RawEventSource::tryFrom((string) $row->source)?->label() ?? (string) $row->source,
            'source_key' => (string) $row->source,
            'branch' => $branches[$row->branch_id]->name ?? __('reports.ui.not_set'),
            'events' => (int) $row->events,
            'employees' => (int) $row->employees,
            'days' => (int) $row->days,
            'share' => round($row->events * 100 / $total, 1),
        ])->sortBy([['source_key', 'asc'], ['branch', 'asc']])->values();
    }

    public function row(mixed $record, array $filters): array
    {
        return $record;
    }

    public function chart(Collection $rows, array $filters): ?array
    {
        return ChartData::series('doughnut', $this->label('events'), $rows->groupBy('source')->map(fn (Collection $group): int => (int) $group->sum('events'))->all());
    }
}
