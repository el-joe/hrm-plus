<?php

namespace App\Services\Reports\Definitions\Leave;

use App\Services\Reports\ReportFilters;
use App\Support\Charts\ChartData;
use Illuminate\Support\Collection;

/**
 * Reuses the Phase 6 utilisation report ("usage_by_department": counted days of taken leave per
 * department and type in the period).
 */
class LeaveUtilizationReport extends LegacyLeaveReport
{
    public function key(): string
    {
        return 'leave_utilization';
    }

    protected function legacyKey(): string
    {
        return 'usage_by_department';
    }

    public function filters(): array
    {
        return array_values(array_diff(ReportFilters::EMPLOYEE_FILTERS, ['employee']));
    }

    public function hasChart(): bool
    {
        return true;
    }

    public function chart(Collection $rows, array $filters): ?array
    {
        $byType = $rows->groupBy('type')->map(fn (Collection $group): string => $group->reduce(fn (string $sum, array $row): string => bcadd($sum, (string) $row['days'], 2), '0'));

        return ChartData::series('bar', __('reports.columns.days'), $byType->all(), ['decimals' => 1]);
    }
}
