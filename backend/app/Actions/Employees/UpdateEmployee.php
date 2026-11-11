<?php

namespace App\Actions\Employees;

use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Edits an employee's personal data (audited by the model). The job
 * placement and salary are NOT edited here: they change through dated
 * history entries (ChangeEmployeeAssignment, ChangeEmployeeSalary, ...).
 */
class UpdateEmployee
{
    public function __construct(private readonly SyncEmployeeDetails $details) {}

    /**
     * @param  array<string, mixed>  $data  validated input; national_id / passport_number only when the editor may change them
     */
    public function handle(Employee $employee, array $data, ?UploadedFile $photo = null): Employee
    {
        return DB::transaction(function () use ($employee, $data, $photo): Employee {
            $employee->fill(array_intersect_key($data, array_flip($employee->getFillable())));

            if (filled($data['employee_number'] ?? null)) {
                $employee->employee_number = $data['employee_number'];
            }

            if ($employee->isDirty()) {
                $employee->updated_by = Auth::id();
            }

            $employee->save();
            $this->details->handle($employee, $data, $photo);

            return $employee;
        });
    }
}
