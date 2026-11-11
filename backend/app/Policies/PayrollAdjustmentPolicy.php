<?php

namespace App\Policies;

use App\Models\PayrollAdjustment;
use App\Models\User;

/**
 * Post-finalisation corrections: listed with payroll.view (data scope through
 * the employee), entered / scanned with payroll.create, dismissed with
 * payroll.update. Nobody enters or dismisses a correction of their own pay.
 */
class PayrollAdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payroll.view');
    }

    public function create(User $user): bool
    {
        return $user->can('payroll.create');
    }

    public function dismiss(User $user, PayrollAdjustment $adjustment): bool
    {
        return $user->can('payroll.update') && $adjustment->isPending() && $adjustment->payroll_item_id === null
            && $adjustment->isVisibleTo($user) && $adjustment->employee_id !== $user->employee_id;
    }
}
