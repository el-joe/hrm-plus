<?php

namespace App\Http\Controllers\Admin\Payroll;

use App\Enums\PayComponentKind;

/**
 * Allowances (permission module allowances); see PayEntryController.
 */
class AllowanceController extends PayEntryController
{
    public function kind(): PayComponentKind
    {
        return PayComponentKind::Earning;
    }
}
