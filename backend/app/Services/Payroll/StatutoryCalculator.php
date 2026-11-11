<?php

namespace App\Services\Payroll;

use App\Services\Payroll\Data\StatutoryRule;

/**
 * Applies a statutory rule version (bcmath). The rates come from the rule
 * tables only — this class holds no rate.
 *
 * socialInsurance(): the insurable base of the month clamped to the
 *   version's floor / ceiling, × employee rate % and employer rate %.
 * incomeTax(): taxable pay of the period × periods per year = annual income,
 *   minus the annual exemption, taxed progressively by the brackets
 *   (rate % of the part inside each bracket), ÷ periods per year.
 */
class StatutoryCalculator
{
    /**
     * @return array{base: string, employee: string, employer: string}
     */
    public function socialInsurance(StatutoryRule $rule, string $insurableAmount, int $precision = 2): array
    {
        $base = bcadd($insurableAmount, '0', PayrollMath::SCALE);

        if ($rule->salaryFloor !== null) {
            $base = PayrollMath::max($base, $rule->salaryFloor);
        }

        if ($rule->salaryCeiling !== null) {
            $base = PayrollMath::min($base, $rule->salaryCeiling);
        }

        if (bccomp($insurableAmount, '0', PayrollMath::SCALE) <= 0) {
            $base = '0';
        }

        return [
            'base' => PayrollMath::round($base, $precision),
            'employee' => PayrollMath::round(PayrollMath::percent($base, $rule->employeeRate ?? '0'), $precision),
            'employer' => PayrollMath::round(PayrollMath::percent($base, $rule->employerRate ?? '0'), $precision),
        ];
    }

    /**
     * Tax of one period.
     */
    public function incomeTax(StatutoryRule $rule, string $taxableAmount, int $periodsPerYear = 12, int $precision = 2): string
    {
        $annual = bcmul($taxableAmount, (string) $periodsPerYear, PayrollMath::SCALE);
        $annual = bcsub($annual, $rule->annualExemption ?? '0', PayrollMath::SCALE);

        return PayrollMath::round(PayrollMath::div($this->annualTax($rule, $annual), (string) $periodsPerYear), $precision);
    }

    /**
     * Progressive tax of an annual taxable income (after exemption).
     */
    public function annualTax(StatutoryRule $rule, string $annualTaxable): string
    {
        $tax = '0';

        if (bccomp($annualTaxable, '0', PayrollMath::SCALE) <= 0) {
            return $tax;
        }

        foreach ($rule->brackets as $bracket) {
            if (bccomp($annualTaxable, $bracket['lower'], PayrollMath::SCALE) <= 0) {
                break;
            }

            $top = $bracket['upper'] === null ? $annualTaxable : PayrollMath::min($annualTaxable, $bracket['upper']);
            $slice = bcsub($top, $bracket['lower'], PayrollMath::SCALE);
            $tax = bcadd($tax, PayrollMath::percent($slice, $bracket['rate']), PayrollMath::SCALE);
        }

        return $tax;
    }
}
