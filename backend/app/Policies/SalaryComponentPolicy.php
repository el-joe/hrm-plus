<?php

namespace App\Policies;

class SalaryComponentPolicy extends StructurePolicy
{
    protected function module(): string
    {
        return 'salary_components';
    }
}
