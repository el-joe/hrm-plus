<?php

namespace App\Services\Payroll;

use App\Enums\PayBasis;
use App\Services\Settings\SettingsService;

/**
 * Day, hour and minute rates of a basic salary (bcmath, 6 decimals):
 *
 * - monthly / fixed: day = salary ÷ payroll.working_days_per_month, hour = day ÷ payroll.working_hours_per_day;
 * - daily:           day = salary, hour = day ÷ working hours per day;
 * - hourly:          hour = salary, day = hour × working hours per day.
 *
 * minute = hour ÷ 60. A fixed pay basis is never reduced by attendance, but
 * its rates are still available (e.g. for overtime).
 */
class SalaryRates
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @return array{day: string, hour: string, minute: string}
     */
    public function for(string $basicSalary, PayBasis $basis): array
    {
        $days = (string) $this->settings->get('payroll.working_days_per_month');
        $hours = (string) $this->settings->get('payroll.working_hours_per_day');

        [$day, $hour] = match ($basis) {
            PayBasis::Monthly, PayBasis::Fixed => [$d = PayrollMath::div($basicSalary, $days), PayrollMath::div($d, $hours)],
            PayBasis::Daily => [$basicSalary, PayrollMath::div($basicSalary, $hours)],
            PayBasis::Hourly => [PayrollMath::mul($basicSalary, $hours), $basicSalary],
        };

        return ['day' => bcadd($day, '0', PayrollMath::SCALE), 'hour' => bcadd($hour, '0', PayrollMath::SCALE), 'minute' => PayrollMath::div($hour, '60')];
    }
}
