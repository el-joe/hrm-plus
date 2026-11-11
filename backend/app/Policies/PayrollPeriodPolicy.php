<?php

namespace App\Policies;

use App\Models\PayrollPeriod;
use App\Models\User;

/**
 * Payroll periods: listed with payroll.view, created / edited / closed / reopened with payroll.manage.
 */
class PayrollPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payroll.view');
    }

    public function manage(User $user, ?PayrollPeriod $period = null): bool
    {
        return $user->can('payroll.manage');
    }
}
