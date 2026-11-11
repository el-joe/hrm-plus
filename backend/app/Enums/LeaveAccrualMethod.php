<?php

namespace App\Enums;

/**
 * How a leave policy credits its yearly entitlement.
 *
 * - annual_upfront: the full entitlement at the start of the leave year;
 * - monthly: 1/12 at the start of each month of employment;
 * - pro_rata_hire: upfront, reduced in the hire year to the months left after the hire date;
 * - service_tiers: upfront, the days of the tier matching the completed years of service
 *   (topped up when a tier is reached during the year), pro rata in the hire year.
 */
enum LeaveAccrualMethod: string
{
    case AnnualUpfront = 'annual_upfront';
    case Monthly = 'monthly';
    case ProRataHire = 'pro_rata_hire';
    case ServiceTiers = 'service_tiers';

    public function label(): string
    {
        return __('leave.enums.accrual_methods.'.$this->value);
    }
}
