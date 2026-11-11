<?php

namespace App\Policies;

class HolidayPolicy extends StructurePolicy
{
    protected function module(): string
    {
        return 'holidays';
    }
}
