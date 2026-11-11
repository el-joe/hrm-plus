<?php

namespace App\Policies;

class LeavePolicyPolicy extends StructurePolicy
{
    protected function module(): string
    {
        return 'leave_policies';
    }
}
