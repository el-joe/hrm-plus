<?php

namespace App\Services\Reports\Definitions\Payroll;

use App\Enums\LoanType;

/**
 * Salary advances: advances active in (or started during) the period and their recovery.
 */
class AdvancesReport extends LoanReport
{
    public function key(): string
    {
        return 'advances';
    }

    protected function type(): LoanType
    {
        return LoanType::Advance;
    }
}
