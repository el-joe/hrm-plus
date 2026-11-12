<?php

namespace App\Enums;

/**
 * Kind of performance review cycle. Probation cycles review the employees whose
 * probation ends inside the cycle period.
 */
enum PerformanceCycleType: string
{
    case Annual = 'annual';
    case SemiAnnual = 'semi_annual';
    case Quarterly = 'quarterly';
    case Probation = 'probation';

    public function label(): string
    {
        return __('performance.enums.cycle_types.'.$this->value);
    }
}
