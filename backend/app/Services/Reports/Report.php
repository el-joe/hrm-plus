<?php

namespace App\Services\Reports;

use App\Models\Employee;
use App\Models\User;
use App\Services\Attendance\BulkAttendance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * One report of the reports centre (spec module 16). A report is a small class:
 *
 * - key / area / title / description (lang/{ar,en}/reports.php → items.{key});
 * - requires(): permissions needed besides reports.view (e.g. payroll → employee_financials.view);
 * - filters(): the shared filters it honours (App\Services\Reports\ReportFilters::SHARED) and
 *   extraFilters(): report-specific selects;
 * - columns(): Column definitions (type, totals, guard);
 * - query(): an Eloquent builder (paginated in SQL) or a collection of records — queries start from
 *   data-scoped models (or go through ReportFilters::applyEmployeeFilters, which always applies the
 *   admin data scope), so the screen and every export show the same rows;
 * - row(): record → raw values keyed like columns() (money as DECIMAL strings, dates as dates);
 * - sortable(): column key → SQL expression for builder reports (collections sort in PHP);
 * - chart(): optional chart built from all rows (grouped reports).
 *
 * Registered in App\Services\Reports\ReportRegistry.
 */
abstract class Report
{
    abstract public function key(): string;

    abstract public function area(): string;

    /**
     * @return array<string, Column>
     */
    abstract public function columns(): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<*>|Collection<int, mixed>
     */
    abstract public function query(array $filters, User $user): Builder|Collection;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    abstract public function row(mixed $record, array $filters): array;

    public function title(): string
    {
        return __('reports.items.'.$this->key().'.title');
    }

    public function description(): string
    {
        return __('reports.items.'.$this->key().'.description');
    }

    /**
     * Permissions required besides reports.view (all of them).
     *
     * @return list<string>
     */
    public function requires(): array
    {
        return [];
    }

    public function isAvailableTo(User $user): bool
    {
        if (! $user->isAdmin() || ! $user->can('reports.view')) {
            return false;
        }

        foreach ($this->requires() as $permission) {
            if (! $user->can($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Shared filters this report honours.
     *
     * @return list<string>
     */
    public function filters(): array
    {
        return ReportFilters::EMPLOYEE_FILTERS;
    }

    /**
     * Report-specific select filters: key => [label, options (value => label), rules].
     *
     * @return array<string, array{label: string, options: array<string, string>, rules?: list<mixed>}>
     */
    public function extraFilters(): array
    {
        return [];
    }

    /**
     * Default filter values (applied when the request leaves them empty).
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $today = BulkAttendance::today();

        return ['from' => $today->startOfMonth()->format('Y-m-d'), 'to' => $today->format('Y-m-d')];
    }

    /**
     * Longest date range, in days.
     */
    public function maxRangeDays(): int
    {
        return 731;
    }

    /**
     * Sort keys (column keys) => SQL expression, for builder reports.
     *
     * @return array<string, string>
     */
    public function sortable(): array
    {
        return [];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows  every row of the report
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>|null
     */
    public function chart(Collection $rows, array $filters): ?array
    {
        return null;
    }

    public function hasChart(): bool
    {
        return false;
    }

    /**
     * Columns describing an employee (number, name, branch, department).
     *
     * @return array<string, Column>
     */
    protected function employeeColumns(bool $withBranch = true): array
    {
        $columns = [
            'employee_number' => Column::code(__('reports.columns.employee_number')),
            'employee' => Column::text(__('reports.columns.employee')),
        ];

        if ($withBranch) {
            $columns['branch'] = Column::text(__('reports.columns.branch'));
            $columns['department'] = Column::text(__('reports.columns.department'));
        }

        return $columns;
    }

    /**
     * @return array{employee_number: ?string, employee: ?string, branch: ?string, department: ?string}
     */
    protected function employeeCells(?Employee $employee): array
    {
        return [
            'employee_number' => $employee?->employee_number,
            'employee' => $employee?->name,
            'branch' => $employee?->branch?->name,
            'department' => $employee?->department?->name,
        ];
    }

    protected function label(string $key): string
    {
        return __('reports.columns.'.$key);
    }
}
