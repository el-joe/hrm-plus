<?php

namespace App\Policies;

class WorkSchedulePolicy extends StructurePolicy
{
    protected function module(): string
    {
        return 'schedules';
    }
}
