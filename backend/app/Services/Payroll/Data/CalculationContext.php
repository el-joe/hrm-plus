<?php

namespace App\Services\Payroll\Data;

/**
 * What App\Services\Payroll\ComponentCalculator needs to turn component rules
 * into amounts for one employee and one period. Built by the payroll engine
 * (Phase 7b) from PayrollInputs plus the attendance / leave quantities it
 * reads (attendance_days, LeavePayrollFeed). All values are bcmath strings.
 *
 * basic:         basic salary for the period after proration (monthly amount × factor,
 *                daily rate × paid days, hourly rate × hours, fixed amount);
 * dayRate / hourRate / minuteRate: from App\Services\Payroll\SalaryRates;
 * prorationFactor: share of the period the employee is paid for (0–1, 6 decimals);
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
    ) {}

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
            'quantities' => $this->quantities,
            'overtime' => $this->overtime,
        ];
    }
}
