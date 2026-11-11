<?php

namespace App\Policies;

class CostCenterPolicy extends StructurePolicy
{
    protected function module(): string
    {
        return 'cost_centers';
    }
}
