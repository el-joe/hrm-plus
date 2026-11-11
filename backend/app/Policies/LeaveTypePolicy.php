<?php

namespace App\Policies;

class LeaveTypePolicy extends StructurePolicy
{
    protected function module(): string
    {
        return 'leave_types';
    }
}
