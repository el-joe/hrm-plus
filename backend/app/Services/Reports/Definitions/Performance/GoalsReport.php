<?php

namespace App\Services\Reports\Definitions\Performance;

use App\Enums\GoalStatus;
use App\Models\PerformanceGoal;
use App\Models\User;
use App\Services\Reports\Column;
use App\Services\Reports\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Goals and KPIs of cycles overlapping the period: weight, progress (0–100), status, due date and
 * whether an active goal is overdue.
 */
class GoalsReport extends PerformanceReport
{
    public function key(): string
    {
        return 'goals';
    }

    public function extraFilters(): array
    {
        return [...parent::extraFilters(), 'goal_status' => [
            'label' => __('reports.filters.goal_status'),
            'options' => collect(GoalStatus::cases())->mapWithKeys(fn (GoalStatus $status): array => [$status->value => $status->label()])->all(),
        ]];
    }

    public function columns(): array
    {
        return [
            ...$this->employeeColumns(),
            'cycle' => Column::text($this->label('cycle')),
            'goal' => Column::text($this->label('goal')),
            'type' => Column::text($this->label('type')),
            'weight' => Column::decimal($this->label('weight'), 2, false),
            'progress' => Column::percent($this->label('progress'), 0),
            'status' => Column::text($this->label('status')),
            'due_date' => Column::date($this->label('due_date')),
            'overdue' => Column::text($this->label('overdue')),
        ];
    }

    public function sortable(): array
    {
        return ['progress' => 'performance_goals.progress', 'due_date' => 'performance_goals.due_date', 'weight' => 'performance_goals.weight'];
    }

    public function query(array $filters, User $user): Builder
    {
        $query = PerformanceGoal::query()
            ->with(['employee.branch', 'employee.department', 'cycle'])
            ->when($filters['goal_status'] ?? null, fn (Builder $query, string $status) => $query->where('performance_goals.status', $status))
            ->orderBy('performance_goals.due_date')->orderBy('performance_goals.id');
        $query = $this->cycleFilters($query, $filters, 'performance_goals.performance_cycle_id');
        $query = ReportFilters::applyEmployeeFilters($query, 'performance_goals.employee_id', $filters);

        return $this->limitedByReportingLine($user) ? $this->visibility()->goals($query, $user) : $query;
    }

    public function row(mixed $record, array $filters): array
    {
        /** @var PerformanceGoal $record */
        $overdue = $record->status === GoalStatus::Active && $record->due_date !== null && $record->due_date->isPast() && ! $record->due_date->isToday();

        return [
            ...$this->employeeCells($record->employee),
            'cycle' => $record->cycle?->name,
            'goal' => $record->title,
            'type' => $record->type?->label(),
            'weight' => $record->weight === null ? null : (string) $record->weight,
            'progress' => $record->progress === null ? null : (string) $record->progress,
            'status' => $record->status?->label(),
            'due_date' => $record->due_date,
            'overdue' => $overdue ? __('reports.ui.yes') : '',
        ];
    }
}
