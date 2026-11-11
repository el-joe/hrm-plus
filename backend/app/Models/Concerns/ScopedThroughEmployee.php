<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Data scope of records that belong to an employee (branch, department and
 * employee group of the employee as they are today).
 *
 * @mixin Model
 */
trait ScopedThroughEmployee
{
    use HasDataScope;

    /**
     * @return array<string, string>
     */
    public function dataScopeMap(): array
    {
        return [
            'branch' => 'employee.branch_id',
            'department' => 'employee.department_id',
            'employee_group' => 'employee.groups.id',
        ];
    }
}
