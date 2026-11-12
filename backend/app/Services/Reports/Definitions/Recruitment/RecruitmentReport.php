<?php

namespace App\Services\Reports\Definitions\Recruitment;

use App\Models\Vacancy;
use App\Services\Authorization\DataScopeManager;
use App\Services\Reports\Report;
use Illuminate\Database\Eloquent\Builder;

/**
 * Base of the recruitment reports. Candidates are not employees, so the placement filters apply to
 * the vacancy (branch, department incl. sub-departments); vacancies, applications and interviews
 * are data-scoped through the vacancy.
 */
abstract class RecruitmentReport extends Report
{
    public function area(): string
    {
        return 'recruitment';
    }

    public function filters(): array
    {
        return ['date_range', 'branch', 'department'];
    }

    public function defaults(): array
    {
        return ['from' => now()->subMonths(5)->startOfMonth()->format('Y-m-d'), 'to' => now()->format('Y-m-d')];
    }

    public function extraFilters(): array
    {
        return ['vacancy_id' => [
            'label' => __('reports.filters.vacancy'),
            'options' => Vacancy::query()->latest('id')->limit(200)->get()->mapWithKeys(fn (Vacancy $vacancy): array => [$vacancy->id => $vacancy->reference.' · '.$vacancy->title])->all(),
        ]];
    }

    /**
     * Placement / vacancy filters on a query whose vacancy columns are reachable as {$table}.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<TModel>
     */
    protected function vacancyFilters(Builder $query, array $filters, string $table = 'vacancies'): Builder
    {
        return $query
            ->when($filters['branch_id'] ?? null, fn (Builder $query, $id) => $query->where("{$table}.branch_id", $id))
            ->when($filters['department_id'] ?? null, fn (Builder $query, $id) => $query->whereIn("{$table}.department_id", app(DataScopeManager::class)->withSubDepartments([(int) $id])))
            ->when($filters['vacancy_id'] ?? null, fn (Builder $query, $id) => $query->where("{$table}.id", $id));
    }
}
