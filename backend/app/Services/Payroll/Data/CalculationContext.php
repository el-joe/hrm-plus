<?php

namespace App\Services\Payroll\Data;

use App\Services\Payroll\PayrollMath;

/**
 * What App\Services\Payroll\ComponentCalculator needs to turn component rules
 * into amounts for one employee and one period. Built by the payroll engine
 * (Phase 7b) from PayrollInputs plus the attendance / leave quantities it
 * reads (attendance_days, LeavePayrollFeed). All values are bcmath strings.
 *
 * basic:         basic salary for the period after proration (monthly amount × factor,
 *                daily rate × paid days, hourly rate × hours, fixed amount);
 * dayRate / hourRate / minuteRate: from App\Services\Payroll\SalaryRates;
 * prorationFactor: share of the period the employee is paid for (0–1, 6 decimals) — shown on
 *                payslips and snapshots only;
 * prorationNumerator / prorationDenominator: the same share as an exact fraction of days
 *                (paid days ÷ period days). Prorated amounts are amount × numerator ÷ denominator,
 *                divided last and rounded once at the line level (Phase 11). When they are null
 *                (hand-built contexts) the factor is used instead.
 * quantities:    late_minutes, early_leave_minutes, absent_days, unpaid_leave_days;
 * overtime:      list of [hours, multiplier] — one row per overtime kind (regular / rest day /
 *                holiday) with the attendance policy version's multiplier.
 */
final readonly class CalculationContext
{
    /**
     * @param  array<string, string>  $quantities
     * @param  list<array{hours: string, multiplier: string}>  $overtime
     */
    public function __construct(
        public string $basic,
        public string $dayRate,
        public string $hourRate,
        public string $minuteRate,
        public string $prorationFactor = '1',
        public array $quantities = [],
        public array $overtime = [],
        public ?string $prorationNumerator = null,
        public ?string $prorationDenominator = null,
    ) {}

    /**
     * The amount times the paid share of the period: exact (amount × days ÷ period days) when the
     * fraction is known, otherwise amount × factor. Not rounded.
     */
    public function prorate(string $amount): string
    {
        if ($this->prorationNumerator === null || $this->prorationDenominator === null) {
            return PayrollMath::mul($amount, $this->prorationFactor);
        }

        return PayrollMath::fraction($amount, $this->prorationNumerator, $this->prorationDenominator);
    }

    public function quantity(string $key): string
    {
        return $this->quantities[$key] ?? '0';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'basic' => $this->basic,
            'day_rate' => $this->dayRate,
            'hour_rate' => $this->hourRate,
            'minute_rate' => $this->minuteRate,
            'proration_factor' => $this->prorationFactor,
            'proration_fraction' => $this->prorationNumerator === null ? null : $this->prorationNumerator.'/'.$this->prorationDenominator,
            'quantities' => $this->quantities,
            'overtime' => $this->overtime,
        ];
    }
}
