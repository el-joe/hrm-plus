<?php

namespace App\Http\Controllers\Admin\Employees;

use App\Actions\Employees\ManageEmployeeAccount;
use App\Enums\AccountType;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Employees\EmployeeAccountRequest;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The employee's own login account (employees.manage): create it (the
 * employee receives a link to set their password), resend that link, or
 * switch the account off / on. Admin accounts linked to a profile are
 * managed under Admin users, not here.
 */
class EmployeeAccountController extends Controller
{
    public function store(EmployeeAccountRequest $request, Employee $employee, ManageEmployeeAccount $accounts): RedirectResponse
    {
        $accounts->create($employee, $request->validated('account_email'), $request->validated('locale'));

        return back()->with('status', __('employees.account.created'));
    }

    public function invite(Employee $employee, ManageEmployeeAccount $accounts): RedirectResponse
    {
        Gate::authorize('manage', $employee);
        $user = $employee->user()->where('account_type', AccountType::Employee)->firstOrFail();

        $accounts->invite($user);

        return back()->with('status', __('employees.account.invited', ['email' => $user->email]));
    }

    public function status(Request $request, Employee $employee, ManageEmployeeAccount $accounts): RedirectResponse
    {
        Gate::authorize('manage', $employee);
        $user = $employee->user()->where('account_type', AccountType::Employee)->firstOrFail();
        $status = $request->validate(['status' => ['required', Rule::in([UserStatus::Active->value, UserStatus::Inactive->value])]])['status'];

        abort_if($status === UserStatus::Active->value && $employee->isTerminated(), 422);

        $accounts->setStatus($user, UserStatus::from($status));

        return back()->with('status', __('employees.account.status_changed'));
    }
}
