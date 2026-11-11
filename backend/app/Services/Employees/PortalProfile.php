<?php

namespace App\Services\Employees;

use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeCustomField;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Support\SensitiveValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * What an employee sees about themselves (portal "My Profile" and the mobile
 * API): personal and contact data, employment data, direct manager,
 * emergency contacts, documents shared with them and their employment
 * history — never salary (the payroll phase adds "My Payroll"), never
 * documents HR has not shared, identity numbers masked.
 *
 * Always resolved from the signed-in user's own employee_id, ignoring admin
 * data scopes (an admin opening their own profile is not restricted).
 */
class PortalProfile
{
    public function __construct(private readonly CustomFieldValues $customFields) {}

    public function employeeFor(User $user): ?Employee
    {
        if ($user->employee_id === null) {
            return null;
        }

        return Employee::query()->withoutGlobalScopes()
            ->with(['branch', 'department', 'team', 'position', 'jobTitle', 'jobGrade', 'employmentType', 'workLocation',
                'manager' => fn ($query) => $query->withoutGlobalScopes(), 'emergencyContacts'])
            ->find($user->employee_id);
    }

    /**
     * @return Builder<EmployeeDocument>
     */
    public function documents(Employee $employee): Builder
    {
        return $employee->documents()->withoutGlobalScopes()->whereNull('deleted_at')->visibleToEmployee()->getQuery();
    }

    /**
     * @return Collection<int, EmployeeAssignment>
     */
    public function history(Employee $employee): Collection
    {
        return $employee->assignments()
            ->whereNotNull('applied_at')->whereNull('cancelled_at')
            ->with(['department' => fn ($query) => $query->withoutGlobalScopes(), 'branch' => fn ($query) => $query->withoutGlobalScopes(),
                'jobTitle', 'position' => fn ($query) => $query->withoutGlobalScopes()])
            ->orderByDesc('effective_date')->orderByDesc('id')
            ->get();
    }

    /**
     * @return array{national_id: ?string, passport_number: ?string}
     */
    public function maskedIdentity(Employee $employee): array
    {
        return [
            'national_id' => SensitiveValue::mask($employee->national_id),
            'passport_number' => SensitiveValue::mask($employee->passport_number),
        ];
    }

    /**
     * @return list<array{field: EmployeeCustomField, value: ?string}>
     */
    public function customFields(Employee $employee): array
    {
        return array_values(array_filter($this->customFields->display($employee, employeeView: true), fn (array $row): bool => $row['value'] !== null));
    }
}
