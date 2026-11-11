<?php

namespace App\Http\Controllers\Admin\Employees;

use App\Actions\Employees\ManageEmployeeContracts;
use App\Enums\ContractType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Employees\ContractRequest;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Services\Employees\EmployeeFileStore;
use App\Services\Employees\SensitiveDataAccessLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Employment contracts: view with employees.view, add / correct / renew with
 * employees.update. The signed copy is downloaded through download() only
 * (permission + data scope checked, access logged).
 */
class EmployeeContractController extends Controller
{
    public function create(Employee $employee): View
    {
        Gate::authorize('update', $employee);

        return $this->form($employee, new EmployeeContract([
            'start_date' => $employee->hire_date,
            'contract_type' => $employee->employmentType?->is_fixed_term ? ContractType::FixedTerm : ContractType::Permanent,
        ]));
    }

    public function store(ContractRequest $request, Employee $employee, ManageEmployeeContracts $contracts): RedirectResponse
    {
        $contracts->create($employee, $request->validated(), $request->file('file'));

        return redirect()->route('admin.employees.tab', [$employee, 'contracts'])->with('status', __('employees.messages.contract_saved'));
    }

    public function edit(Employee $employee, EmployeeContract $contract): View
    {
        Gate::authorize('update', $employee);

        return $this->form($employee, $contract);
    }

    public function update(ContractRequest $request, Employee $employee, EmployeeContract $contract, ManageEmployeeContracts $contracts): RedirectResponse
    {
        $contracts->update($contract, $request->validated(), $request->file('file'));

        return redirect()->route('admin.employees.tab', [$employee, 'contracts'])->with('status', __('employees.messages.contract_saved'));
    }

    public function renewForm(Employee $employee, EmployeeContract $contract): View
    {
        Gate::authorize('update', $employee);
        abort_if($contract->end_date === null || $contract->renewedBy()->exists(), 404);

        return $this->form($employee, new EmployeeContract([
            'contract_type' => $contract->contract_type,
            'start_date' => $contract->end_date->copy()->addDay(),
            'end_date' => $contract->end_date->copy()->addDay()->addDays($contract->start_date->diffInDays($contract->end_date)),
        ]), $contract);
    }

    public function renew(ContractRequest $request, Employee $employee, EmployeeContract $contract, ManageEmployeeContracts $contracts): RedirectResponse
    {
        abort_if($contract->end_date === null || $contract->renewedBy()->exists(), 404);

        $contracts->create($employee, $request->validated(), $request->file('file'), $contract);

        return redirect()->route('admin.employees.tab', [$employee, 'contracts'])->with('status', __('employees.messages.contract_renewed'));
    }

    public function download(Request $request, Employee $employee, EmployeeContract $contract, EmployeeFileStore $files, SensitiveDataAccessLog $log): StreamedResponse
    {
        Gate::authorize('view', $employee);
        abort_unless($files->exists($contract->file_path), 404);

        $log->record(SensitiveDataAccessLog::CONTRACT, $contract, ['file'], ['employee_id' => $employee->getKey()], $request->user());

        return $files->response($contract->file_path, $contract->file_name, $contract->file_mime);
    }

    private function form(Employee $employee, EmployeeContract $contract, ?EmployeeContract $renewing = null): View
    {
        return view('admin.employees.contract', [
            'employee' => $employee,
            'contract' => $contract,
            'renewing' => $renewing,
            'contractTypes' => ContractType::cases(),
            'action' => match (true) {
                $renewing !== null => route('admin.employees.contracts.renew', [$employee, $renewing]),
                $contract->exists => route('admin.employees.contracts.update', [$employee, $contract]),
                default => route('admin.employees.contracts.store', $employee),
            },
        ]);
    }
}
