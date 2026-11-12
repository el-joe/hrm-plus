<?php

namespace App\Services\Reports\Definitions\Performance;

use App\Models\PerformanceReview;
use App\Models\User;
use App\Services\Reports\Column;
use App\Services\Reports\ReportFilters;
use App\Support\Charts\ChartData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Performance scores of finalised reviews (acknowledgement / completed) of cycles overlapping the
 * period: goals, competencies and overall score on the cycle's scale, the final rating band and
 * whether HR calibrated it. Grouped by the review's branch / department snapshot.
 */
class PerformanceScoresReport extends PerformanceReport
{
    public function key(): string
    {
        return 'performance_scores';
    }

    public function columns(): array
    {
        return [
            ...$this->employeeColumns(),
            'cycle' => Column::text($this->label('cycle')),
            'reviewer' => Column::text($this->label('reviewer')),
            'goals_score' => Column::decimal($this->label('goals_score'), 2, false),
            'competencies_score' => Column::decimal($this->label('competencies_score'), 2, false),
            'overall_score' => Column::decimal($this->label('overall_score'), 2, false),
            'rating' => Column::text($this->label('rating')),
            'calibrated' => Column::text($this->label('calibrated')),
        ];
    }

    public function sortable(): array
    {
        return ['overall_score' => 'performance_reviews.overall_score', 'goals_score' => 'performance_reviews.goals_score', 'competencies_score' => 'performance_reviews.competencies_score'];
    }

    public function hasChart(): bool
    {
        return true;
    }

    public function query(array $filters, User $user): Builder
    {
        $query = PerformanceReview::query()->finalised()
            ->with(['employee', 'cycle', 'reviewer', 'finalRating', 'department'])
            ->leftJoin('branches', 'branches.id', '=', 'performance_reviews.branch_id')
            ->select('performance_reviews.*', 'branches.name_ar as branch_ar', 'branches.name_en as branch_en')
            ->orderByDesc('performance_reviews.overall_score')->orderBy('performance_reviews.id');
        $query = $this->cycleFilters($query, $filters, 'performance_reviews.performance_cycle_id');
        $query = ReportFilters::applyEmployeeFilters($query, 'performance_reviews.employee_id', $filters, [
            'branch' => 'performance_reviews.branch_id', 'department' => 'performance_reviews.department_id',
        ]);

        return $this->limitedByReportingLine($user) ? $this->visibility()->reviews($query, $user) : $query;
    }

    public function row(mixed $record, array $filters): array
    {
        /** @var PerformanceReview $record */
        return [
            'employee_number' => $record->employee?->employee_number,
            'employee' => $record->employee?->name,
            'branch' => app()->getLocale() === 'en' ? $record->getAttribute('branch_en') : $record->getAttribute('branch_ar'),
            'department' => $record->department?->name,
            'cycle' => $record->cycle?->name,
            'reviewer' => $record->reviewer?->name,
            'goals_score' => $record->goals_score === null ? null : (string) $record->goals_score,
            'competencies_score' => $record->competencies_score === null ? null : (string) $record->competencies_score,
            'overall_score' => $record->overall_score === null ? null : (string) $record->overall_score,
            'rating' => $record->finalRating?->label,
            'calibrated' => $record->calibrated ? __('reports.ui.yes') : __('reports.ui.no'),
        ];
    }

    public function chart(Collection $rows, array $filters): ?array
    {
        $bands = $rows->groupBy(fn (array $row): string => $row['rating'] ?? __('reports.ui.not_set'))->map->count();

        return ChartData::series('doughnut', __('reports.columns.employees'), $bands->all());
    }
}
