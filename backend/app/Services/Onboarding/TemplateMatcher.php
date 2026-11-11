<?php

namespace App\Services\Onboarding;

use App\Models\Employee;
use App\Models\OnboardingTemplate;

/**
 * Picks the onboarding template for an employee: among active templates
 * whose every set criterion matches (position, department, employment type),
 * the most specific one wins (position 4, department 2, employment type 1),
 * then the newest. A template without criteria is the company default.
 */
class TemplateMatcher
{
    public function for(Employee $employee): ?OnboardingTemplate
    {
        return OnboardingTemplate::query()->active()
            ->where(fn ($query) => $query->whereNull('position_id')->orWhere('position_id', $employee->position_id))
            ->where(fn ($query) => $query->whereNull('department_id')->orWhere('department_id', $employee->department_id))
            ->where(fn ($query) => $query->whereNull('employment_type_id')->orWhere('employment_type_id', $employee->employment_type_id))
            ->get()
            ->sortByDesc(fn (OnboardingTemplate $template): string => sprintf('%d-%010d',
                ($template->position_id ? 4 : 0) + ($template->department_id ? 2 : 0) + ($template->employment_type_id ? 1 : 0),
                $template->getKey()))
            ->first();
    }
}
