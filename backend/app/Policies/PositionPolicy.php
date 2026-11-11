<?php

namespace App\Policies;

class PositionPolicy extends StructurePolicy
{
    protected function module(): string
    {
        return 'positions';
    }
}
