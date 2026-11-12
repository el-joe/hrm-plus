<?php

namespace App\Services\Reports\Definitions\Performance;

use App\Models\Department;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Services\Reports\Column;
use App\Services\Reports\ReportFilters;
use App\Support\Charts\ChartData;
use Illuminate\Support\Collection;

/**
 * Review completion per cycle and department: reviews, finalised (acknowledgement / completed),
 * still open (self / manager assessment, pending approval), cancelled and the completion rate.
 */
class CompletedReviewsReport extends PerformanceReport
{
    public function key(): string
    {
        return 'completed_reviews';
    }

    public function filters(): array
    {
        return array_values(array_diff(ReportFilters::EMPLOYEE_FILTERS, ['employee']));
    }

    public function columns(): array
    {
        return [
            'cycle' => Column::text($this->label('cycle')),
            'department' => Column::text($this->label('department')),
            'reviews' => Column::number($this->label('reviews')),
            'completed' => Column::number($this->label('completed')),
            'open' => Column::number($this->label('open')),
            'cancelled' => Column::number($this->label('cancelled')),
            'completion_rate' => Column::percent($this->label('completion_rate')),
        ];
    }

    public function hasChart(): bool
    {
        return true;
    }

    public function query(array $filters, User $user): Collection
    {
        $query = PerformanceReview::query()
            ->select('performance_reviews.performance_cycle_id', 'performance_reviews.department_id')
            ->selectRaw('count(*) as reviews')
            ->selectRaw("sum(case when performance_reviews.status in ('acknowledgement', 'completed') then 1 else 0 end) as completed")
            ->selectRaw("sum(case when performance_reviews.status in ('self_assessment', 'manager_assessment', 'pending_approval') then 1 else 0 end) as open")
            ->selectRaw("sum(case when performance_reviews.status = 'cancelled' then 1 else 0 end) as cancelled")
            ->groupBy('performance_reviews.performance_cycle_id', 'performance_reviews.department_id');
        $query = $this->cycleFilters($query, $filters, 'performance_reviews.performance_cycle_id');
        $query = ReportFilters::applyEmployeeFilters($query, 'performance_reviews.employee_id', $filters, [
            'branch' => 'performance_reviews.branch_id', 'department' => 'performance_reviews.department_id',
        ]);

        if ($this->limitedByReportingLine($user)) {
            $query = $this->visibility()->reviews($query, $user);
        }

        $rows = $query->toBase()->get();
        $cycles = PerformanceCycle::query()->whereKey($rows->pluck('performance_cycle_id')->unique()->all())->get()->keyBy('id');
        $departments = Department::query()->withoutGlobalScopes()->whereKey($rows->pluck('department_id')->filter()->unique()->all())->get()->keyBy('id');

        return $rows->map(function (object $row) use ($cycles, $departments): array {
            $countable = (int) $row->reviews - (int) $row->cancelled;

            return [
                'cycle' => $cycles[$row->performance_cycle_id]->name ?? '#'.$row->performance_cycle_id,
                'department' => $departments[$row->department_id]->name ?? __('reports.ui.not_set'),
                'reviews' => (int) $row->reviews,
                'completed' => (int) $row->completed,
                'open' => (int) $row->open,
                'cancelled' => (int) $row->cancelled,
                'completion_rate' => $countable > 0 ? round($row->completed * 100 / $countable, 1) : 0,
            ];
        })->sortBy([['cycle', 'asc'], ['department', 'asc']])->values();
    }

    public function row(mixed $record, array $filters): array
    {
        return $record;
    }

    public function chart(Collection $rows, array $filters): ?array
    {
        $byCycle = $rows->groupBy('cycle');

        return ChartData::make('bar', $byCycle->keys()->all(), [
            ['label' => $this->label('completed'), 'data' => $byCycle->map(fn (Collection $group): int => (int) $group->sum('completed'))->values()->all(), 'color' => 'chart-1'],
            ['label' => $this->label('open'), 'data' => $byCycle->map(fn (Collection $group): int => (int) $group->sum('open'))->values()->all(), 'color' => 'chart-5'],
        ], ['stacked' => true]);
    }
}
