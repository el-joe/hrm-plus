<?php

namespace App\Services\Reports\Definitions\Payroll;

class PayrollByDepartmentReport extends PayrollByPlacementReport
{
    public function key(): string
    {
        return 'payroll_by_department';
    }

    protected function placement(): array
    {
        return ['department_id', 'departments'];
    }
}
