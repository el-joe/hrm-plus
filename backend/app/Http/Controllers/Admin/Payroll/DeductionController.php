<?php

namespace App\Http\Controllers\Admin\Payroll;

use App\Enums\PayComponentKind;

/**
 * Deductions (permission module deductions); see PayEntryController.
 */
class DeductionController extends PayEntryController
{
    public function kind(): PayComponentKind
    {
        return PayComponentKind::Deduction;
    }
}
