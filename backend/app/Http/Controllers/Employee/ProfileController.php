<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\Employees\PortalProfile;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "My Profile" (spec section 5): read-only, always the signed-in user's own
 * record. Changes to protected data are requested from HR (HR requests
 * arrive with the requests module).
 */
class ProfileController extends Controller
{
    public const TABS = ['personal', 'employment', 'documents', 'history'];

    public function __invoke(Request $request, PortalProfile $profile, string $tab = 'personal'): View
    {
        $employee = $profile->employeeFor($request->user());
        abort_if($employee === null, 404);

        return view('employee.profile', [
            'employee' => $employee,
            'tab' => $tab,
            'identity' => $profile->maskedIdentity($employee),
            'customFields' => $tab === 'personal' ? $profile->customFields($employee) : [],
            'documents' => $tab === 'documents' ? $profile->documents($employee)->orderBy('category')->orderByDesc('id')->get() : collect(),
            'history' => $tab === 'history' ? $profile->history($employee) : collect(),
            'contract' => $tab === 'employment' ? $employee->currentContract() : null,
        ]);
    }
}
