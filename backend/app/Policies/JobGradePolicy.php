<?php

namespace App\Policies;

class JobGradePolicy extends StructurePolicy
{
    protected function module(): string
    {
        return 'job_grades';
    }
}
