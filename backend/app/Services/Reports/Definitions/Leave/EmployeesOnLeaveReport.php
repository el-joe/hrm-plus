<?php

namespace App\Services\Reports\Definitions\Leave;

use App\Services\Reports\ReportFilters;

/**
 * Reuses the Phase 6 "on_leave" report: employees on approved / taken leave on a date (default today).
 */
class EmployeesOnLeaveReport extends LegacyLeaveReport
{
    public function key(): string
    {
        return 'employees_on_leave';
    }

    protected function legacyKey(): string
    {
        return 'on_leave';
    }

    public function filters(): array
    {
        return array_values(array_diff(ReportFilters::EMPLOYEE_FILTERS, ['date_range']));
    }

    public function extraFilters(): array
    {
        return [...parent::extraFilters(), 'date' => ['label' => __('reports.filters.date'), 'type' => 'date', 'options' => [], 'rules' => ['nullable', 'date_format:Y-m-d']]];
    }
}
