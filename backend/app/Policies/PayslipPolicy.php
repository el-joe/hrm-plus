<?php

namespace App\Policies;

use App\Models\Payslip;
use App\Models\User;

/**
 * Payslips in the admin area (employees use the portal / API, which only
 * ever read their own): payslips.view inside the data scope (every view and
 * download is logged), payslips.manage to record payments, payslips.export
 * for payslip lists.
 */
class PayslipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payslips.view');
    }

    public function view(User $user, Payslip $payslip): bool
    {
        return $user->can('payslips.view') && $payslip->isVisibleTo($user);
    }

    public function pay(User $user, ?Payslip $payslip = null): bool
    {
        return $user->can('payslips.manage') && ($payslip === null || (! $payslip->isVoid() && $payslip->isVisibleTo($user)));
    }

    public function export(User $user): bool
    {
        return $user->can('payslips.export');
    }
}
