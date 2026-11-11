<?php

namespace App\Services\Payroll;

use App\Enums\StatutoryType;
use App\Models\StatutoryRuleBracket;
use App\Models\StatutoryRuleSet;
use App\Models\StatutoryRuleVersion;
use App\Services\Payroll\Data\StatutoryRule;
use Carbon\CarbonInterface;

/**
 * Finds the statutory rule version in force: the active rule set of the
 * country (setting payroll.country_code unless given) and type, and its
 * version effective on the date. No active set or no version → null (payroll
 * then calculates no such deduction and says so). Demo sets are returned
 * with isDemo = true when an admin has activated them.
 */
class StatutoryRules
{
    public function for(StatutoryType $type, CarbonInterface|string $date, ?string $countryCode = null): ?StatutoryRule
    {
        $country = strtoupper((string) ($countryCode ?? setting('payroll.country_code')));

        if ($country === '') {
            return null;
        }

        $sets = StatutoryRuleSet::query()->active()->where('country_code', $country)->where('type', $type)
            ->orderBy('is_demo')->orderBy('id')->get();

        foreach ($sets as $set) {
            $version = $set->versionOn($date);

            if ($version !== null) {
                return $this->toRule($set, $version);
            }
        }

        return null;
    }

    /**
     * Every type that has a rule on the date, keyed by type value.
     *
     * @return array<string, StatutoryRule>
     */
    public function all(CarbonInterface|string $date, ?string $countryCode = null): array
    {
        $rules = [];

        foreach (StatutoryType::cases() as $type) {
            $rule = $this->for($type, $date, $countryCode);

            if ($rule !== null) {
                $rules[$type->value] = $rule;
            }
        }

        return $rules;
    }

    public function toRule(StatutoryRuleSet $set, StatutoryRuleVersion $version): StatutoryRule
    {
        return new StatutoryRule(
            setId: $set->id,
            setCode: $set->code,
            versionId: $version->id,
            version: $version->version,
            countryCode: $set->country_code,
            type: $set->type,
            isDemo: $set->is_demo,
            effectiveFrom: $version->effective_from->toDateString(),
            effectiveTo: $version->effective_to?->toDateString(),
            employeeRate: $version->employee_rate === null ? null : (string) $version->employee_rate,
            employerRate: $version->employer_rate === null ? null : (string) $version->employer_rate,
            salaryFloor: $version->salary_floor === null ? null : (string) $version->salary_floor,
            salaryCeiling: $version->salary_ceiling === null ? null : (string) $version->salary_ceiling,
            annualExemption: $version->annual_exemption === null ? null : (string) $version->annual_exemption,
            brackets: $version->brackets->map(fn (StatutoryRuleBracket $bracket): array => [
                'lower' => (string) $bracket->lower_bound,
                'upper' => $bracket->upper_bound === null ? null : (string) $bracket->upper_bound,
                'rate' => (string) $bracket->rate,
            ])->values()->all(),
            baseReductions: array_values(array_map('strval', $version->base_reductions ?? [])),
            deductEmployeeInsurance: (bool) $version->deduct_employee_insurance,
        );
    }
}
