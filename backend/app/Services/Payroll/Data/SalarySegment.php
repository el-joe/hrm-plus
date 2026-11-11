<?php

namespace App\Services\Payroll\Data;

use App\Enums\PayBasis;

/**
 * The basic salary in force for part of a period (one per employee_salaries
 * row that covers some of it, clipped to the period).
 */
final readonly class SalarySegment
{
    public function __construct(
        public int $salaryId,
        public string $from,
        public string $to,
        public string $basicSalary,
        public PayBasis $payBasis,
        public string $currency,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'salary_id' => $this->salaryId,
            'from' => $this->from,
            'to' => $this->to,
            'basic_salary' => $this->basicSalary,
            'pay_basis' => $this->payBasis->value,
            'currency' => $this->currency,
        ];
    }
}
