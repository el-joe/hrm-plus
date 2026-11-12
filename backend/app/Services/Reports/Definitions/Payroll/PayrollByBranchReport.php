<?php

namespace App\Services\Reports\Definitions\Payroll;

class PayrollByBranchReport extends PayrollByPlacementReport
{
    public function key(): string
    {
        return 'payroll_by_branch';
    }

    protected function placement(): array
    {
        return ['branch_id', 'branches'];
    }
}
