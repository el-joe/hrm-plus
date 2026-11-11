<?php

namespace App\Actions\Employees;

use App\Enums\EmployeeStatus;
use App\Enums\SalaryChangeType;
use App\Models\Branch;
use App\Models\Employee;
use App\Services\Employees\EmployeeNumberGenerator;
use App\Services\Employees\EmployeePlacement;
use App\Services\Employees\EmploymentHistory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Hires an employee: personal data, initial placement (with defaults from
 * the position and department), the opening "hire" history row, the initial
 * salary, emergency contacts, groups, custom fields and photo — atomically.
 */
class CreateEmployee
{
    public function __construct(
        private readonly EmployeeNumberGenerator $numbers,
        private readonly EmployeePlacement $placement,
        private readonly EmploymentHistory $history,
        private readonly SyncEmployeeDetails $details,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated input (see App\Http\Requests\Admin\Employees\EmployeeRequest)
     */
    public function handle(array $data, ?UploadedFile $photo = null): Employee
    {
        return DB::transaction(function () use ($data, $photo): Employee {
            $placement = $this->placement->complete(array_merge(
                array_fill_keys(Employee::PLACEMENT_COLUMNS, null),
                array_intersect_key($data, array_flip(Employee::PLACEMENT_COLUMNS)),
            ));
            $hireDate = Carbon::parse($data['hire_date']);

            $employee = new Employee(array_intersect_key($data, array_flip((new Employee)->getFillable())));
            $employee->forceFill([
                ...$placement,
                'status' => EmployeeStatus::Active,
                'hire_date' => $hireDate,
                'original_hire_date' => $hireDate,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
            $employee->employee_number = filled($data['employee_number'] ?? null)
                ? $data['employee_number']
                : $this->numbers->next(Branch::query()->withoutGlobalScopes()->find($placement['branch_id']), $hireDate);
            $employee->save();

            $this->history->recordHire($employee, $data['hire_reason'] ?? null);

            if (filled($data['basic_salary'] ?? null)) {
                $this->history->recordSalary($employee, (string) $data['basic_salary'], SalaryChangeType::Initial, $hireDate, applyNow: true);
            }

            $this->details->handle($employee, $data, $photo);

            return $employee->refresh();
        });
    }
}
