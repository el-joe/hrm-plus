<?php

namespace App\Services\Reports\Definitions\Employees;

use App\Models\Department;

class EmployeesByDepartmentReport extends EmployeesByPlacementReport
{
    public function key(): string
    {
        return 'employees_by_department';
    }

    protected function placement(): array
    {
        return ['department_id', Department::class];
    }
}
