<?php

namespace App\Services\Employees;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;

/**
 * The employee list query shared by the directory screen and the export, so
 * an export always contains exactly what the filtered list shows (data
 * scopes apply through the model).
 */
class EmployeeDirectory
{
    public const ID_FILTERS = ['branch_id', 'department_id', 'team_id', 'position_id', 'job_title_id', 'employment_type_id', 'manager_id'];

    /**
     * Validation rules of the list filters.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $rules = [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive,terminated'],
            'group_id' => ['nullable', 'integer'],
            'hired_from' => ['nullable', 'date'],
            'hired_to' => ['nullable', 'date'],
        ];

        foreach (self::ID_FILTERS as $filter) {
            $rules[$filter] = ['nullable', 'integer'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Employee>
     */
    public function query(array $filters): Builder
    {
        return Employee::query()
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->search($search))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('employees.status', $status))
            ->when($filters['group_id'] ?? null, fn (Builder $query, $group) => $query->whereHas('groups', fn (Builder $groups) => $groups->whereKey($group)))
            ->when($filters['hired_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('employees.hire_date', '>=', $date))
            ->when($filters['hired_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('employees.hire_date', '<=', $date))
            ->where(function (Builder $query) use ($filters): void {
                foreach (self::ID_FILTERS as $filter) {
                    if (filled($filters[$filter] ?? null)) {
                        $query->where('employees.'.$filter, $filters[$filter]);
                    }
                }
            });
    }
}
