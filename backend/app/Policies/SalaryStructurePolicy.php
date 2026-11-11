<?php

namespace App\Policies;

class SalaryStructurePolicy extends StructurePolicy
{
    protected function module(): string
    {
        return 'salary_structures';
    }
}
