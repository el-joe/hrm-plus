<?php

namespace App\Services\Payroll\Data;

use App\Enums\PayBasis;
use App\Enums\PayComponentKind;
use App\Enums\StatutoryType;

/**
 * Everything payroll (Phase 7b) reads from the component layer for one
 * employee and one period — see docs/PAYROLL_ENGINE.md. Immutable; toArray()
 * is the snapshot to store with a calculation.
 *
 * basicSalary / payBasis: the row in force at the period end (null when the
 * employee has no salary); salarySegments: every row covering part of the
 * period (mid-period changes). Placement fields: where the employee sits at
 * the period end (used for scope precedence).
 */
final readonly class EmployeePayrollInputs
{
    /**
     * @param  list<SalarySegment>  $salarySegments
     * @param  list<PayEntryInput>  $entries
     * @param  list<LoanInstalmentInput>  $loanInstalments
     * @param  array<string, StatutoryRule>  $statutory  keyed by App\Enums\StatutoryType value
     * @param  array<string, ComponentRule>  $catalogue  active quantity / statutory / basic components by code
     */
    public function __construct(
        public int $employeeId,
        public string $periodStart,
        public string $periodEnd,
        public string $currency,
        public string $countryCode,
        public ?string $basicSalary,
        public ?PayBasis $payBasis,
        public array $salarySegments,
        public ?StructureSnapshot $structure,
        public array $entries,
        public array $loanInstalments,
        public array $statutory,
        public array $catalogue,
        public ?int $branchId,
        public ?int $departmentId,
        public ?int $jobGradeId,
        public ?string $hireDate,
        public ?string $terminationDate,
    ) {}

    /**
     * @return list<PayEntryInput>
     */
    public function allowances(): array
    {
        return array_values(array_filter($this->entries, fn (PayEntryInput $entry): bool => $entry->rule->kind === PayComponentKind::Earning));
    }

    /**
     * @return list<PayEntryInput>
     */
    public function deductions(): array
    {
        return array_values(array_filter($this->entries, fn (PayEntryInput $entry): bool => $entry->rule->kind === PayComponentKind::Deduction));
    }

    public function statutoryRule(StatutoryType $type): ?StatutoryRule
    {
        return $this->statutory[$type->value] ?? null;
    }

    /**
     * Total of the loan instalments due (bcmath).
     */
    public function loanInstalmentTotal(): string
    {
        return array_reduce($this->loanInstalments, fn (string $sum, LoanInstalmentInput $row): string => bcadd($sum, $row->amount, 2), '0.00');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
            'currency' => $this->currency,
            'country_code' => $this->countryCode,
            'basic_salary' => $this->basicSalary,
            'pay_basis' => $this->payBasis?->value,
            'salary_segments' => array_map(fn (SalarySegment $segment): array => $segment->toArray(), $this->salarySegments),
            'structure' => $this->structure?->toArray(),
            'entries' => array_map(fn (PayEntryInput $entry): array => $entry->toArray(), $this->entries),
            'loan_instalments' => array_map(fn (LoanInstalmentInput $row): array => $row->toArray(), $this->loanInstalments),
            'statutory' => array_map(fn (StatutoryRule $rule): array => $rule->toArray(), $this->statutory),
            'catalogue' => array_map(fn (ComponentRule $rule): array => $rule->toArray(), $this->catalogue),
            'branch_id' => $this->branchId,
            'department_id' => $this->departmentId,
            'job_grade_id' => $this->jobGradeId,
            'hire_date' => $this->hireDate,
            'termination_date' => $this->terminationDate,
        ];
    }
}
