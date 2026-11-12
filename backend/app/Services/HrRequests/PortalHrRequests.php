<?php

namespace App\Services\HrRequests;

use App\Models\Employee;
use App\Models\HrRequest;
use App\Models\HrRequestCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * An employee's own HR requests (portal and API). Always constrained to the employee; another
 * employee's request id simply does not resolve (404).
 */
class PortalHrRequests
{
    /**
     * @return Builder<HrRequest>
     */
    public function forEmployee(Employee $employee): Builder
    {
        return HrRequest::query()->withoutGlobalScopes()->where('employee_id', $employee->id)
            ->with(['category'])->latest('id');
    }

    public function find(Employee $employee, int $id): ?HrRequest
    {
        return HrRequest::query()->withoutGlobalScopes()->where('employee_id', $employee->id)->with([
            'category.fields', 'fieldValues',
            'employeeMessages.author', 'employeeAttachments',
            'events' => fn ($query) => $query->where('visible_to_employee', true)->with('actor'),
            'profileChanges',
        ])->find($id);
    }

    public function openCount(Employee $employee): int
    {
        return HrRequest::query()->withoutGlobalScopes()->where('employee_id', $employee->id)->open()->count();
    }

    /**
     * Categories an employee may submit (active, ordered).
     *
     * @return Collection<int, HrRequestCategory>
     */
    public function categories(): Collection
    {
        return HrRequestCategory::query()->active()->with('fields')->orderBy('sort_order')->orderBy('id')->get();
    }
}
