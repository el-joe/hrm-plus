<?php

namespace App\Services\Reports\Definitions\Performance;

use App\Models\PerformanceCycle;
use App\Models\User;
use App\Services\Performance\PerformanceVisibility;
use App\Services\Reports\Report;
use Illuminate\Database\Eloquent\Builder;

/**
 * Base of the performance reports (Phase 8a notes for 8c): reviews / goals are data-scoped through
 * the employee; an admin holding performance.view WITHOUT performance.manage (a manager) is further
 * limited to their reporting line by PerformanceVisibility, exactly as on the performance screens.
 * Report viewers without performance permissions see what their data scope allows.
 */
abstract class PerformanceReport extends Report
{
    public function area(): string
    {
        return 'performance';
    }

    public function defaults(): array
    {
        return ['from' => now()->startOfYear()->format('Y-m-d'), 'to' => now()->endOfYear()->format('Y-m-d')];
    }

    public function extraFilters(): array
    {
        return ['cycle_id' => [
            'label' => __('reports.filters.cycle'),
            'options' => PerformanceCycle::query()->orderByDesc('period_start')->limit(50)->get()->mapWithKeys(fn (PerformanceCycle $cycle): array => [$cycle->id => $cycle->code.' · '.$cycle->name])->all(),
        ]];
    }

    protected function limitedByReportingLine(User $user): bool
    {
        return $user->can('performance.view') && ! $user->can('performance.manage');
    }

    /**
     * Cycles overlapping the period.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<TModel>
     */
    protected function cycleFilters(Builder $query, array $filters, string $cycleColumn): Builder
    {
        return $query
            ->when($filters['cycle_id'] ?? null, fn (Builder $query, $id) => $query->where($cycleColumn, $id))
            ->whereIn($cycleColumn, PerformanceCycle::query()->select('id')
                ->where('period_start', '<=', $filters['to'])->where('period_end', '>=', $filters['from']));
    }

    protected function visibility(): PerformanceVisibility
    {
        return app(PerformanceVisibility::class);
    }
}
