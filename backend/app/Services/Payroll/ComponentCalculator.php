<?php

namespace App\Services\Payroll;

use App\Enums\PayCalculationType;
use App\Exceptions\PayrollConfigurationException;
use App\Services\Payroll\Data\CalculationContext;
use App\Services\Payroll\Data\ComponentRule;
use App\Services\Payroll\Data\PayEntryInput;

/**
 * Turns component rules into amounts (bcmath, rounded per component). Pure:
 * no database access. Statutory components are left to StatutoryCalculator
 * (they need the period's taxable / insurable totals) and return null here.
 *
 *     $amounts = $calculator->evaluate($rules, $context);   // code => "1234.50"
 *
 * Fixed amounts are multiplied by the context's proration factor when the
 * component is prorated; percentage components follow their base (the basic
 * in the context is already prorated) and are never prorated twice.
 *
 * Percentage components may be based on the basic salary or on any other
 * component of the same evaluation; they are resolved in dependency order
 * and a cycle (A → B → A) is refused. Amounts are always positive; the
 * component kind says whether payroll adds or subtracts them.
 */
class ComponentCalculator
{
    /**
     * Evaluate a set of rules together (structure lines, entries) and return code => amount.
     * Rules sharing a code are summed (two entries of the same component).
     *
     * @param  list<ComponentRule>  $rules
     * @param  array<string, string>  $known  amounts already known by code (e.g. structure results for entries based on them)
     * @param  bool  $round  false: unrounded amounts (SCALE decimals) for the caller to sum over salary segments and
     *                       round once with finalise()
     * @return array<string, string>
     *
     * @throws PayrollConfigurationException on a percentage cycle or a missing base
     */
    public function evaluate(array $rules, CalculationContext $context, array $known = [], bool $round = true): array
    {
        $pending = $rules;
        $results = $known;
        $computed = [];

        while ($pending !== []) {
            $progress = false;

            foreach ($pending as $index => $rule) {
                $base = $rule->calculationType === PayCalculationType::Percentage ? $rule->baseCode : null;
                $waitsFor = $base !== null && ! array_key_exists($base, $results) && $this->isPendingCode($base, $pending, $index);

                if ($waitsFor) {
                    continue;
                }

                $amount = $this->amount($rule, $context, $results, $round);

                if ($amount !== null) {
                    $computed[$rule->code] = isset($computed[$rule->code]) ? bcadd($computed[$rule->code], $amount, $round ? $rule->precision : PayrollMath::SCALE) : $amount;
                    $results[$rule->code] = $computed[$rule->code];
                }

                unset($pending[$index]);
                $progress = true;
            }

            if (! $progress) {
                throw PayrollConfigurationException::percentageCycle(array_map(fn (ComponentRule $rule): string => $rule->code, array_values($pending)));
            }
        }

        return $computed;
    }

    /**
     * Amounts of allowance / deduction entries (code => sum). A prorated recurring entry that
     * covers only part of the period gets the context's factor × its own share of the period.
     *
     * @param  list<PayEntryInput>  $entries
     * @param  array<string, string>  $known  structure amounts by code (percentage bases)
     * @return array<string, string>
     */
    public function evaluateEntries(array $entries, CalculationContext $context, string $periodStart, string $periodEnd, array $known = []): array
    {
        $totals = [];
        $proration = app(Proration::class);

        foreach ($entries as $entry) {
            $entryContext = $context;

            if ($entry->rule->prorate && ($entry->activeFrom !== $periodStart || $entry->activeTo !== $periodEnd)) {
                $share = $proration->factor($periodStart, $periodEnd, $entry->activeFrom, $entry->activeTo);
                [$activeDays, $periodDays] = $proration->fraction($periodStart, $periodEnd, $entry->activeFrom, $entry->activeTo);
                $entryContext = new CalculationContext(
                    $context->basic, $context->dayRate, $context->hourRate, $context->minuteRate,
                    PayrollMath::mul($context->prorationFactor, $share), $context->quantities, $context->overtime,
                    $context->prorationNumerator === null ? null : bcmul($context->prorationNumerator, (string) $activeDays),
                    $context->prorationDenominator === null ? null : bcmul($context->prorationDenominator, (string) $periodDays),
                );
            }

            $amount = $this->amount($entry->rule, $entryContext, $known) ?? '0';
            $code = $entry->rule->code;
            $totals[$code] = isset($totals[$code]) ? bcadd($totals[$code], $amount, $entry->rule->precision) : $amount;
        }

        return $totals;
    }

    /**
     * Amount of one rule (rounded to its precision), or null for statutory components.
     *
     * @param  array<string, string>  $results  amounts of other components by code (percentage bases)
     *
     * @throws PayrollConfigurationException when a percentage base is unknown
     */
    public function amount(ComponentRule $rule, CalculationContext $context, array $results = [], bool $round = true): ?string
    {
        $raw = match ($rule->calculationType) {
            PayCalculationType::Basic => $context->basic,
            PayCalculationType::Fixed, PayCalculationType::Variable => $this->prorated((string) ($rule->amount ?? '0'), $rule, $context),
            PayCalculationType::Percentage => PayrollMath::percent($this->base($rule, $context, $results), (string) ($rule->rate ?? '0')),
            PayCalculationType::LateMinutes, PayCalculationType::EarlyLeaveMinutes => $this->perUnit($rule, $context, $context->minuteRate),
            PayCalculationType::AbsentDays, PayCalculationType::UnpaidLeaveDays => $this->perUnit($rule, $context, $context->dayRate),
            PayCalculationType::OvertimeHours => $this->overtime($rule, $context),
            PayCalculationType::Statutory => null,
        };

        if ($raw === null) {
            return null;
        }

        return $round ? $this->finalise($rule, $raw) : $raw;
    }

    /**
     * Round an unrounded amount to the rule's precision and mode (never below zero): the one rounding of a line.
     */
    public function finalise(ComponentRule $rule, string $raw): string
    {
        $rounded = PayrollMath::round($raw, $rule->precision, $rule->rounding);

        return bccomp($rounded, '0', $rule->precision) < 0 ? bcadd('0', '0', $rule->precision) : $rounded;
    }

    private function prorated(string $amount, ComponentRule $rule, CalculationContext $context): string
    {
        return $rule->prorate ? $context->prorate($amount) : $amount;
    }

    /**
     * @param  array<string, string>  $results
     */
    private function base(ComponentRule $rule, CalculationContext $context, array $results): string
    {
        if ($rule->baseCode === null) {
            return $context->basic;
        }

        if (! array_key_exists($rule->baseCode, $results)) {
            throw PayrollConfigurationException::missingBase($rule->code, $rule->baseCode);
        }

        return $results[$rule->baseCode];
    }

    /**
     * quantity × (fixed amount per unit, or salary rate × multiplier).
     */
    private function perUnit(ComponentRule $rule, CalculationContext $context, string $salaryRate): string
    {
        $quantity = $context->quantity((string) $rule->calculationType->quantityKey());
        $unit = $rule->amount ?? PayrollMath::mul($salaryRate, $rule->rate ?? '1');

        return PayrollMath::mul($quantity, $unit);
    }

    /**
     * Σ hours × hour rate × multiplier: the attendance policy's multiplier of each overtime kind,
     * or the component's rate (default setting payroll.overtime_rate_multiplier). A fixed amount
     * per hour replaces the hour rate × multiplier.
     */
    private function overtime(ComponentRule $rule, CalculationContext $context): string
    {
        $total = '0';

        foreach ($context->overtime as $row) {
            $multiplier = $rule->usePolicyMultiplier ? $row['multiplier'] : ($rule->rate ?? (string) setting('payroll.overtime_rate_multiplier'));
            $unit = $rule->amount ?? PayrollMath::mul($context->hourRate, $multiplier);
            $total = bcadd($total, PayrollMath::mul($row['hours'], $unit), PayrollMath::SCALE);
        }

        return $total;
    }

    /**
     * @param  array<int, ComponentRule>  $pending
     */
    private function isPendingCode(string $code, array $pending, int $self): bool
    {
        foreach ($pending as $index => $rule) {
            if ($index !== $self && $rule->code === $code) {
                return true;
            }
        }

        return false;
    }
}
