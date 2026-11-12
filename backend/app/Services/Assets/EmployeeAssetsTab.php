<?php

namespace App\Services\Assets;

use App\Models\AssetAssignment;
use App\Models\AssetEvent;
use App\Models\Employee;
use App\Models\User;
use App\Services\Employees\Contracts\ProfileTab;

/**
 * Employee profile tab "Assets" (assets.view): what the employee holds now,
 * every past custody period and the custody events. Assignments are listed
 * for the employee whatever the asset's branch (custody follows the person),
 * links to an asset only when the admin may open it.
 */
class EmployeeAssetsTab implements ProfileTab
{
    public function visibleTo(User $user, Employee $employee): bool
    {
        return $user->can('assets.view');
    }

    public function data(User $user, Employee $employee): array
    {
        $assignments = AssetAssignment::query()->where('employee_id', $employee->id)
            ->with(['asset' => fn ($query) => $query->withoutGlobalScopes()->with('category'), 'issuer', 'receiver'])
            ->orderByDesc('assigned_on')->orderByDesc('id')->get();

        return [
            'currentAssets' => $assignments->filter->isActive()->values(),
            'pastAssets' => $assignments->reject->isActive()->values(),
            'assetEvents' => AssetEvent::query()->where('employee_id', $employee->id)
                ->with(['asset' => fn ($query) => $query->withoutGlobalScopes(), 'creator'])
                ->latest('occurred_on')->latest('id')->limit(50)->get(),
            'currency' => (string) setting('localisation.currency_code'),
        ];
    }
}
