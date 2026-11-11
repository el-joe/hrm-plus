<?php

namespace App\Policies;

class JobTitlePolicy extends StructurePolicy
{
    protected function module(): string
    {
        return 'job_titles';
    }
}
