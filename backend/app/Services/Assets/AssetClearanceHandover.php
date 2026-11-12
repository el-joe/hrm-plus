<?php

namespace App\Services\Assets;

use App\Enums\HandoverCategory;
use App\Enums\TaskAssignee;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Services\Offboarding\Contracts\HandoverItemProvider;

/**
 * Offboarding handover provider (config lifecycle.handover_providers): one
 * "asset" clearance item per asset the employee still holds, linked to the
 * assignment (offboarding_items.itemable). Recording the return on the asset
 * completes the item (App\Actions\Assets\ManageAssetCustody::returnAsset).
 */
class AssetClearanceHandover implements HandoverItemProvider
{
    public function itemsFor(Employee $employee, OffboardingCase $case): array
    {
        return AssetAssignment::query()->withoutGlobalScopes()
            ->where('employee_id', $employee->id)->active()
            ->orderBy('assigned_on')->orderBy('id')->get()
            ->map(fn (AssetAssignment $assignment): array => $this->itemFor($assignment))
            ->values()->all();
    }

    /**
     * @return array{category: string, assignee: string, title: string, description: string, itemable_type: string, itemable_id: int}
     */
    public function itemFor(AssetAssignment $assignment): array
    {
        $locale = (string) setting('localisation.default_locale');
        $asset = $assignment->asset()->withoutGlobalScopes()->with('category')->firstOrFail();

        return [
            'category' => HandoverCategory::Asset->value,
            'assignee' => TaskAssignee::It->value,
            'title' => __('assets.clearance.title', ['asset' => $asset->label()], $locale),
            'description' => __('assets.clearance.description', [
                'category' => $asset->category?->localized('name', $locale),
                'date' => $assignment->assigned_on->toDateString(),
            ], $locale),
            'itemable_type' => $assignment->getMorphClass(),
            'itemable_id' => $assignment->id,
        ];
    }
}
