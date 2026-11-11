<?php

namespace App\Http\Requests\Admin\Payroll;

use App\Http\Requests\Loans\LoanRules;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A loan / advance requested by an admin on an employee's behalf.
 */
class StoreLoanRequest extends FormRequest
{
    use LoanRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', EmployeeLoan::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...$this->loanRules(), 'employee_id' => ['required', 'integer']];
    }

    public function employee(): Employee
    {
        $employee = Employee::query()->find($this->validated('employee_id'));
        abort_if($employee === null || ! $this->user()->can('createFor', [EmployeeLoan::class, $employee]), 403);

        return $employee;
    }
}
