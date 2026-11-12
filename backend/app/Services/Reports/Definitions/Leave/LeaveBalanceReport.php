<?php

namespace App\Services\Reports\Definitions\Leave;

use App\Services\Attendance\BulkAttendance;
use App\Services\Leave\LeaveYear;
use App\Services\Reports\ReportFilters;

/**
 * Reuses the Phase 6 "balances" report: available balance per leave type for a leave year.
 */
class LeaveBalanceReport extends LegacyLeaveReport
{
    public function key(): string
    {
        return 'leave_balance';
    }

    protected function legacyKey(): string
    {
        return 'balances';
    }

    public function filters(): array
    {
        return array_values(array_diff(ReportFilters::EMPLOYEE_FILTERS, ['date_range', 'employee_status']));
    }

    public function extraFilters(): array
    {
        $current = app(LeaveYear::class)->of(BulkAttendance::today());
        $years = range($current - 2, $current + 1);

        return ['year' => ['label' => __('reports.filters.leave_year'), 'options' => array_combine(array_map('strval', $years), array_map('strval', $years))]];
    }
}
