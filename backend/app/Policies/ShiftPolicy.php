<?php

namespace App\Policies;

class ShiftPolicy extends StructurePolicy
{
    protected function module(): string
    {
        return 'shifts';
    }
}
