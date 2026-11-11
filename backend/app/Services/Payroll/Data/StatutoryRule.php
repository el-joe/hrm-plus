<?php

namespace App\Services\Payroll\Data;

use App\Enums\StatutoryType;

/**
 * The statutory rule version in force for a country, type and date.
 * isDemo: illustrative values ("DEMO — not legal advice") — payroll must say so wherever it uses them.
 * baseReductions: component categories (App\Enums\PayComponentCategory values) or component
 * codes whose deduction lines reduce the base (taxable income for income tax, insurable pay
 * for social insurance); deductEmployeeInsurance (income tax): the employee's social
 * insurance share also reduces taxable income. Configured per version (Phase 7b).
 *
 * @phpstan-type Bracket array{lower: string, upper: ?string, rate: string}
 */
final readonly class StatutoryRule
{
    /**
     * @param  list<array{lower: string, upper: ?string, rate: string}>  $brackets
     * @param  list<string>  $baseReductions
     */
    public function __construct(
        public int $setId,
        public string $setCode,
        public int $versionId,
        public int $version,
        public string $countryCode,
        public StatutoryType $type,
        public bool $isDemo,
        public string $effectiveFrom,
        public ?string $effectiveTo,
        public ?string $employeeRate,
        public ?string $employerRate,
        public ?string $salaryFloor,
        public ?string $salaryCeiling,
        public ?string $annualExemption,
        public array $brackets,
        public array $baseReductions = [],
        public bool $deductEmployeeInsurance = false,
    ) {}

    /**
     * Whether a deduction line of the category / code reduces this rule's base.
     */
    public function reducesBase(string $category, string $code): bool
    {
        return in_array($category, $this->baseReductions, true) || in_array($code, $this->baseReductions, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'set_id' => $this->setId,
            'set_code' => $this->setCode,
            'version_id' => $this->versionId,
            'version' => $this->version,
            'country_code' => $this->countryCode,
            'type' => $this->type->value,
            'is_demo' => $this->isDemo,
            'effective_from' => $this->effectiveFrom,
            'effective_to' => $this->effectiveTo,
            'employee_rate' => $this->employeeRate,
            'employer_rate' => $this->employerRate,
            'salary_floor' => $this->salaryFloor,
            'salary_ceiling' => $this->salaryCeiling,
            'annual_exemption' => $this->annualExemption,
            'brackets' => $this->brackets,
            'base_reductions' => $this->baseReductions,
            'deduct_employee_insurance' => $this->deductEmployeeInsurance,
        ];
    }
}
