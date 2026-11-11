<?php

namespace App\Policies;

class AttendancePolicyPolicy extends StructurePolicy
{
    protected function module(): string
    {
        return 'attendance_policies';
    }
}
