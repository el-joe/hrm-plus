<?php

namespace App\Services\Payroll;

use App\Enums\HandoverCategory;
use App\Enums\TaskAssignee;
use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Services\Offboarding\Contracts\HandoverItemProvider;

/**
 * Offboarding handover provider (config lifecycle.handover_providers): the
 * "Final financial settlement" finance item, linked to its own case
 * (itemable = the case). The case page offers "Prepare final settlement"
 * (App\Actions\Payroll\CreateFinalSettlement) from this item, and finalising
 * the settlement run marks it done.
 */
class FinalSettlementHandover implements HandoverItemProvider
{
    public function itemsFor(Employee $employee, OffboardingCase $case): array
    {
        $locale = setting('localisation.default_locale') === 'ar' ? 'ar' : 'en';

        return [[
            'category' => HandoverCategory::Finance->value,
            'assignee' => TaskAssignee::Finance->value,
            'title' => __('payroll_runs.final_settlement.handover_title', [], $locale),
            'description' => __('payroll_runs.final_settlement.handover_description', [], $locale),
            'itemable_type' => $case->getMorphClass(),
            'itemable_id' => $case->id,
        ]];
    }
}
