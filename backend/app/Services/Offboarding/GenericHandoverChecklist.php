<?php

namespace App\Services\Offboarding;

use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Services\Offboarding\Contracts\HandoverItemProvider;

/**
 * The generic checklist from config('lifecycle.generic_handover'), in the
 * company's default language (items are records, not interface strings).
 */
class GenericHandoverChecklist implements HandoverItemProvider
{
    public function itemsFor(Employee $employee, OffboardingCase $case): array
    {
        $arabic = setting('localisation.default_locale') === 'ar';

        return array_map(fn (array $item): array => [
            'category' => $item[0],
            'assignee' => $item[1],
            'title' => $arabic ? $item[2] : $item[3],
        ], config('lifecycle.generic_handover'));
    }
}
