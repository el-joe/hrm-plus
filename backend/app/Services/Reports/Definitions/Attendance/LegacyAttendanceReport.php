<?php

namespace App\Services\Reports\Definitions\Attendance;

use App\Models\User;
use App\Services\Attendance\Reports\AttendanceReports;
use App\Services\Reports\Column;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * A reports-centre entry that REUSES a Phase 5b attendance report
 * (App\Services\Attendance\Reports\AttendanceReports: columns, query, row) instead of
 * duplicating it. The centre adds the shared employee filters (team, position, group, …) through
 * ReportFilters, PDF export and totals.
 */
abstract class LegacyAttendanceReport extends Report
{
    /**
     * Key of the report in AttendanceReports::REPORTS.
     */
    abstract protected function legacyKey(): string;

    /**
     * Employee id column of the legacy query (null: not employee based).
     */
    protected function employeeColumn(): ?string
    {
        return 'attendance_days.employee_id';
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
        $legacy = app(AttendanceReports::class);
        $numeric = $legacy->numeric($this->legacyKey());
        $columns = [];

        foreach ($legacy->columns($this->legacyKey()) as $key => $label) {
            $columns[$key] = match (true) {
                in_array($key, $numeric, true) => Column::number($label),
                $key === 'date' => Column::date($label),
                in_array($key, ['worked', 'late', 'early_leave', 'overtime', 'overtime_approved'], true) => Column::duration($label),
                in_array($key, ['employee_number', 'check_in', 'check_out', 'scheduled_start', 'recorded_at', 'started_at', 'serial', 'rate'], true) => Column::code($label),
                default => Column::text($label),
            };
        }

        return $columns;
    }

    public function sortable(): array
    {
        if ($this->employeeColumn() !== 'attendance_days.employee_id') {
            return [];
        }

        // Only the columns the legacy report has (the monthly summary is grouped per employee, without a date).
        return array_intersect_key(['date' => 'attendance_days.work_date', 'employee_number' => 'employees.employee_number'], $this->columns());
    }

    public function query(array $filters, User $user): Builder
    {
        $query = app(AttendanceReports::class)->query($this->legacyKey(), [
            'from' => $filters['from'], 'to' => $filters['to'],
            'branch_id' => $filters['branch_id'] ?? null, 'department_id' => $filters['department_id'] ?? null,
            'search' => $filters['employee'] ?? null,
        ]);

        if ($this->employeeColumn() === null) {
            return $query;
        }

        return ReportFilters::applyEmployeeFilters($query, $this->employeeColumn(), array_diff_key($filters, array_flip(['branch_id', 'department_id', 'employee'])));
    }

    public function row(mixed $record, array $filters): array
    {
        return app(AttendanceReports::class)->row($this->legacyKey(), $record);
    }
}
