<?php

namespace App\Policies;

class EmployeeGroupPolicy extends StructurePolicy
{
    protected function module(): string
    {
        return 'employee_groups';
    }
}
