<?php

namespace App\Enums;

/**
 * Rows of the loan ledger. The outstanding balance is the disbursed amount
 * minus every repayment (payroll deductions, early repayments) plus reversals.
 */
enum LoanTransactionType: string
{
    case Disbursement = 'disbursement';
    case PayrollDeduction = 'payroll_deduction';
    case EarlyRepayment = 'early_repayment';
    case DeductionReversal = 'deduction_reversal';

    public function label(): string
    {
        return __('payroll.enums.transaction_types.'.$this->value);
    }
}
