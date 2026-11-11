<?php

namespace App\Policies;

class EmploymentTypePolicy extends StructurePolicy
{
    protected function module(): string
    {
        return 'employment_types';
    }
}
