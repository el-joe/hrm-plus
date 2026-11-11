<?php

namespace App\Services\Payroll\Data;

use App\Enums\PayBasis;

/**
 * The full result of App\Services\Payroll\PayrollCalculator for one employee
 * and one run: lines, totals (bcmath strings), the snapshot of everything it
 * read (inputs, quantities, rule versions), warnings and a SHA-256 hash of
 * the whole. Nothing is written by the calculator; ManagePayrollRun stores it.
 *
 * warnings: [{code, params, blocking}] — a blocking warning (negative net pay,
 * configuration error) stops the run from being submitted for approval.
 * loanRecoveries: what finalisation passes to LoanInstalments::markDeducted().
 */
final readonly class PayrollResult
{
    /**
     * @param  list<PayrollLine>  $lines
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $quantities
     * @param  array<string, mixed>  $ruleVersions
     * @param  list<array{code: string, params: array<string, string|int>, blocking: bool}>  $warnings
     * @param  list<array{instalment_id: int, amount: string}>  $loanRecoveries
     * @param  list<int>  $adjustmentIds
     */
    public function __construct(
        public array $lines,
        public string $grossPay,
        public string $totalDeductions,
        public string $employerContributions,
        public string $netPay,
        public string $taxableBase,
        public string $insurableBase,
        public ?string $basicSalary,
        public ?PayBasis $payBasis,
        public string $prorationFactor,
        public array $inputs,
        public array $quantities,
        public array $ruleVersions,
        public array $warnings,
        public array $loanRecoveries,
        public array $adjustmentIds,
        public string $hash,
    ) {}

    public function hasBlockingWarning(): bool
    {
        foreach ($this->warnings as $warning) {
            if ($warning['blocking']) {
                return true;
            }
        }

        return false;
    }
}
