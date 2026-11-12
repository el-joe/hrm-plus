<?php

namespace App\Services\Reports\Definitions\Employees;

use App\Models\Branch;

class EmployeesByBranchReport extends EmployeesByPlacementReport
{
    public function key(): string
    {
        return 'employees_by_branch';
    }

    protected function placement(): array
    {
        return ['branch_id', Branch::class];
    }
}
