<?php

namespace App\Services\Reports\Definitions\Leave;

use App\Enums\LeaveRequestStatus;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Leave\LeaveReports;
use App\Services\Reports\Column;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * A reports-centre entry that REUSES a Phase 6 leave report (App\Services\Leave\LeaveReports:
 * columns, query, row, defaults). The centre adds the shared employee filters, totals and PDF.
 */
abstract class LegacyLeaveReport extends Report
{
    abstract protected function legacyKey(): string;

    /**
     * Employee id column of the legacy query.
     */
    protected function employeeColumn(): string
    {
        return in_array($this->legacyKey(), ['requests', 'upcoming', 'on_leave'], true) ? 'leave_requests.employee_id' : 'employees.id';
    }

    /**
     * Filters forced by this entry (e.g. status = approved).
     *
     * @return array<string, mixed>
     */
    protected function forced(): array
    {
        return [];
    }

    public function area(): string
    {
        return 'leave';
    }

    public function extraFilters(): array
    {
        return ['leave_type_id' => [
            'label' => __('reports.filters.leave_type'),
            'options' => LeaveType::query()->ordered()->get()->mapWithKeys(fn (LeaveType $type): array => [$type->id => $type->name])->all(),
        ]];
    }

    protected function statusFilter(): array
    {
        return ['status' => [
            'label' => __('reports.filters.request_status'),
            'options' => collect(LeaveRequestStatus::cases())->mapWithKeys(fn (LeaveRequestStatus $status): array => [$status->value => $status->label()])->all(),
        ]];
    }

    public function defaults(): array
    {
        return app(LeaveReports::class)->withDefaults($this->legacyKey(), []);
    }

    public function columns(): array
    {
        $legacy = app(LeaveReports::class);
        $numeric = $legacy->numeric($this->legacyKey());
        $columns = [];

        foreach ($legacy->columns($this->legacyKey()) as $key => $label) {
            $columns[$key] = match (true) {
                in_array($key, $numeric, true) && in_array($key, ['requests', 'employees'], true) => Column::number($label),
                in_array($key, $numeric, true) => Column::decimal($label, 2, $this->legacyKey() !== 'balances'),
                in_array($key, ['start_date', 'end_date'], true) => Column::date($label),
                in_array($key, ['requested_at', 'decided_at', 'employee_number', 'id'], true) => Column::code($label),
                default => Column::text($label),
            };
        }

        return $columns;
    }

    public function sortable(): array
    {
        return $this->employeeColumn() === 'leave_requests.employee_id'
            ? ['start_date' => 'leave_requests.start_date', 'end_date' => 'leave_requests.end_date', 'days' => 'leave_requests.days']
            : [];
    }

    public function query(array $filters, User $user): Builder
    {
        $legacyFilters = [
            ...$filters,
            ...$this->forced(),
            'search' => $filters['employee'] ?? null,
        ];
        $query = app(LeaveReports::class)->query($this->legacyKey(), $legacyFilters);

        return ReportFilters::applyEmployeeFilters($query, $this->employeeColumn(), array_diff_key($filters, array_flip(['branch_id', 'department_id', 'employee'])));
    }

    public function row(mixed $record, array $filters): array
    {
        return app(LeaveReports::class)->row($this->legacyKey(), $record, [...$filters, ...$this->forced()]);
    }
}
