<?php

namespace App\Policies;

class TeamPolicy extends StructurePolicy
{
    protected function module(): string
    {
        return 'teams';
    }
}
