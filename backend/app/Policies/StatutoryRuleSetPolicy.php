<?php

namespace App\Policies;

class StatutoryRuleSetPolicy extends StructurePolicy
{
    protected function module(): string
    {
        return 'statutory_rules';
    }
}
