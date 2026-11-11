<?php

namespace App\Http\Controllers\Employee;

use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Services\Employees\PortalProfile;
use App\Services\Leave\LeaveBalanceCalculator;
use App\Services\Leave\LeaveEligibility;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request, PortalProfile $profile, LeaveBalanceCalculator $balances, LeaveEligibility $eligibility): View
    {
        $employee = $profile->employeeFor($request->user());

        return view('employee.home', [
            'user' => $request->user(),
            'employee' => $employee,
            // Employee Home (spec section 5): remaining leave balance and pending requests.
            'leaveBalances' => $employee ? $balances->all($employee, null, $eligibility->requestableTypes($employee))->filter->tracksBalance()->values() : collect(),
            'pendingLeave' => $employee ? LeaveRequest::query()->withoutGlobalScopes()->where('employee_id', $employee->id)
                ->inStatus([LeaveRequestStatus::Pending, LeaveRequestStatus::CancellationPending])->count() : 0,
        ]);
    }
}
