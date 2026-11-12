<?php

namespace App\Services\Reports\Definitions\Attendance;

use App\Enums\CorrectionStatus;
use App\Models\AttendanceCorrection;
use App\Models\User;
use App\Services\Attendance\AttendanceClock;
use App\Services\Reports\Column;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Attendance correction requests for work dates in the period: requested times / status, who asked,
 * source, decision and application.
 */
class AttendanceCorrectionsReport extends Report
{
    public function key(): string
    {
        return 'attendance_corrections';
    }

    public function area(): string
    {
        return 'attendance';
    }

    public function extraFilters(): array
    {
        return ['status' => [
            'label' => __('reports.filters.request_status'),
            'options' => collect(CorrectionStatus::cases())->mapWithKeys(fn (CorrectionStatus $status): array => [$status->value => $status->label()])->all(),
        ]];
    }

    public function columns(): array
    {
        return [
            ...$this->employeeColumns(),
            'date' => Column::date($this->label('date')),
            'check_in' => Column::code($this->label('check_in')),
            'check_out' => Column::code($this->label('check_out')),
            'reason' => Column::text($this->label('reason')),
            'requested_by' => Column::text($this->label('requested_by')),
            'source' => Column::text($this->label('source')),
            'status' => Column::text($this->label('status')),
            'decided_at' => Column::dateTime($this->label('decided_at')),
        ];
    }

    public function sortable(): array
    {
        return ['date' => 'attendance_corrections.work_date', 'status' => 'attendance_corrections.status', 'decided_at' => 'attendance_corrections.decided_at'];
    }

    public function query(array $filters, User $user): Builder
    {
        $query = AttendanceCorrection::query()
            ->with(['employee.branch', 'employee.department', 'requester'])
            ->whereBetween('attendance_corrections.work_date', [$filters['from'], $filters['to']])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('attendance_corrections.status', $status))
            ->orderBy('attendance_corrections.work_date')->orderBy('attendance_corrections.id');

        return ReportFilters::applyEmployeeFilters($query, 'attendance_corrections.employee_id', $filters);
    }

    public function row(mixed $record, array $filters): array
    {
        /** @var AttendanceCorrection $record */
        $tz = (string) setting('localisation.timezone');

        return [
            ...$this->employeeCells($record->employee),
            'date' => $record->work_date,
            'check_in' => AttendanceClock::localTime($record->check_in_at, $tz),
            'check_out' => AttendanceClock::localTime($record->check_out_at, $tz),
            'reason' => $record->reason,
            'requested_by' => $record->requester?->name,
            'source' => $record->source?->label(),
            'status' => $record->status?->label(),
            'decided_at' => $record->decided_at,
        ];
    }
}
