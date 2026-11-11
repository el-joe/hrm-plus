<?php

namespace App\Services\Payroll\Data;

use App\Enums\LoanType;

/**
 * A scheduled loan / advance instalment payroll should deduct in the period.
 * overdue: its due date is before the period (an earlier run did not recover it).
 */
final readonly class LoanInstalmentInput
{
    public function __construct(
        public int $instalmentId,
        public int $loanId,
        public string $loanReference,
        public LoanType $loanType,
        public int $sequence,
        public string $dueDate,
        public string $amount,
        public bool $overdue,
        public bool $finalSettlement,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'instalment_id' => $this->instalmentId,
            'loan_id' => $this->loanId,
            'loan_reference' => $this->loanReference,
            'loan_type' => $this->loanType->value,
            'sequence' => $this->sequence,
            'due_date' => $this->dueDate,
            'amount' => $this->amount,
            'overdue' => $this->overdue,
            'final_settlement' => $this->finalSettlement,
        ];
    }
}
