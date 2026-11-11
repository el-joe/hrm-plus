<?php

namespace App\Services\Leave;

use App\Enums\LeaveAccrualMethod;
use App\Models\Employee;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use Carbon\CarbonImmutable;

/**
 * How many days a policy entitles an employee to in a leave year, up to a
 * date (the accrual "target"). Pure calculation; LeaveAccrualService books
 * the difference between the target and what was already accrued.
 *
 * - annual_upfront: the full entitlement from the first day of the year;
 * - pro_rata_hire: full entitlement, times (months left from the hire month) / 12 in the hire year;
 * - monthly: entitlement / 12 for every month started since the later of year start and hire;
 * - service_tiers: the tier for the completed years of service on the date, pro rata in the hire year.
 *
 * Nothing is due to employees the type is not meant for (gender), before the hire date, after the termination date or before
 * the leave type's minimum service. Results are rounded half up to 2 decimals.
 */
class LeaveEntitlement
{
    public function __construct(private readonly LeaveYear $years) {}

    public function target(LeavePolicy $policy, LeaveType $type, Employee $employee, int $year, CarbonImmutable $asOf): string
    {
        $yearStart = $this->years->start($year);
        $yearEnd = $this->years->end($year);
        $hire = $employee->hire_date ? CarbonImmutable::parse($employee->hire_date->format('Y-m-d')) : null;
        $date = $asOf->startOfDay()->min($yearEnd);

        if ($employee->termination_date !== null) {
            $date = $date->min(CarbonImmutable::parse($employee->termination_date->format('Y-m-d')));
        }

        if ($type->gender !== null && $employee->gender !== $type->gender) {
            return '0.00';
        }

        if ($hire === null || $date->lt($yearStart) || $hire->gt($date)) {
            return '0.00';
        }

        if ($type->min_service_months > 0 && (int) $hire->diffInMonths($date) < $type->min_service_months) {
            return '0.00';
        }

        $days = $policy->accrual_method === LeaveAccrualMethod::ServiceTiers
            ? $this->tierDays($policy, (int) $hire->diffInYears($date))
            : bcadd((string) $policy->entitlement_days, '0', 2);

        $months = match ($policy->accrual_method) {
            LeaveAccrualMethod::AnnualUpfront => 12,
            LeaveAccrualMethod::ProRataHire, LeaveAccrualMethod::ServiceTiers => $hire->gte($yearStart) ? 12 - $this->monthIndex($yearStart, $hire) : 12,
            LeaveAccrualMethod::Monthly => $this->monthIndex($yearStart, $date) - ($hire->gte($yearStart) ? $this->monthIndex($yearStart, $hire) : 0) + 1,
        };

        return $this->round(bcdiv(bcmul($days, (string) max(0, min(12, $months)), 6), '12', 6));
    }

    /**
     * The full-year entitlement (target at the end of the year), for display.
     */
    public function yearly(LeavePolicy $policy, LeaveType $type, Employee $employee, int $year): string
    {
        return $this->target($policy, $type, $employee, $year, $this->years->end($year));
    }

    public function tierDays(LeavePolicy $policy, int $serviceYears): string
    {
        $days = bcadd((string) $policy->entitlement_days, '0', 2);

        foreach ($policy->tiers() as $tier) {
            if ($serviceYears >= $tier['min_years']) {
                $days = $tier['days'];
            }
        }

        return $days;
    }

    /**
     * 0-based month of the leave year the date falls in.
     */
    private function monthIndex(CarbonImmutable $yearStart, CarbonImmutable $date): int
    {
        return ($date->year - $yearStart->year) * 12 + $date->month - $yearStart->month;
    }

    private function round(string $value): string
    {
        return bcdiv(bcdiv(bcadd(bcmul($value, '100', 6), '0.5', 6), '1', 0), '100', 2);
    }
}
