<?php

namespace App\Services\Reports\Definitions\Payroll;

use App\Enums\LoanType;

/**
 * Loan balances: loans active in (or started during) the period with what is still outstanding.
 */
class LoanBalancesReport extends LoanReport
{
    public function key(): string
    {
        return 'loan_balances';
    }

    protected function type(): LoanType
    {
        return LoanType::Loan;
    }
}
