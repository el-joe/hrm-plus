<?php

namespace App\Http\Controllers\Admin\Employees;

use App\Actions\Employees\ChangeEmployeeSalary;
use App\Enums\SalaryChangeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Employees\BankAccountRequest;
use App\Http\Requests\Admin\Employees\SalaryChangeRequest;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeSalary;
use App\Services\Employees\EmploymentHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Financial tab actions (employee_financials.update): dated salary changes
 * and bank accounts. Allowances, deductions, loans and payroll history
 * arrive with the payroll phase.
 */
class EmployeeFinanceController extends Controller
{
    public function storeSalary(SalaryChangeRequest $request, Employee $employee, ChangeEmployeeSalary $change): RedirectResponse
    {
        $salary = $change->handle(
            $employee,
            (string) $request->validated('basic_salary'),
            $request->validated('effective_date'),
            $request->validated('reason'),
            $request->filled('change_type') ? SalaryChangeType::from($request->validated('change_type')) : null,
        );

        return redirect()->route('admin.employees.tab', [$employee, 'financial'])
            ->with('status', __($salary->isPending() ? 'employees.messages.salary_scheduled' : 'employees.messages.salary_changed', ['date' => $salary->effective_date->toDateString()]));
    }

    public function cancelSalary(Employee $employee, EmployeeSalary $salary, EmploymentHistory $history): RedirectResponse
    {
        Gate::authorize('updateFinancial', $employee);
        abort_unless($salary->isPending(), 404);

        $history->cancel($salary);

        return back()->with('status', __('employees.messages.change_cancelled'));
    }

    public function storeBankAccount(BankAccountRequest $request, Employee $employee): RedirectResponse
    {
        $this->saveAccount($employee, new EmployeeBankAccount, $request);

        return redirect()->route('admin.employees.tab', [$employee, 'financial'])->with('status', __('employees.messages.bank_saved'));
    }

    public function updateBankAccount(BankAccountRequest $request, Employee $employee, EmployeeBankAccount $bankAccount): RedirectResponse
    {
        $this->saveAccount($employee, $bankAccount, $request);

        return redirect()->route('admin.employees.tab', [$employee, 'financial'])->with('status', __('employees.messages.bank_saved'));
    }

    public function destroyBankAccount(Employee $employee, EmployeeBankAccount $bankAccount): RedirectResponse
    {
        Gate::authorize('updateFinancial', $employee);

        $bankAccount->delete();

        return redirect()->route('admin.employees.tab', [$employee, 'financial'])->with('status', __('employees.messages.bank_deleted'));
    }

    private function saveAccount(Employee $employee, EmployeeBankAccount $account, BankAccountRequest $request): void
    {
        DB::transaction(function () use ($employee, $account, $request): void {
            $data = $request->validated();
            $isPrimary = $request->boolean('is_primary') || ! $employee->bankAccounts()->whereKeyNot($account->getKey() ?? 0)->exists();

            $account->fill([...$data, 'is_primary' => $isPrimary, 'employee_id' => $employee->getKey()])->save();

            if ($isPrimary) {
                $employee->bankAccounts()->whereKeyNot($account->getKey())->where('is_primary', true)->get()
                    ->each(fn (EmployeeBankAccount $other) => $other->update(['is_primary' => false]));
            }
        });
    }
}
