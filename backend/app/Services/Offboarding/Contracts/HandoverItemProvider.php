<?php

namespace App\Services\Offboarding\Contracts;

use App\Models\Employee;
use App\Models\OffboardingCase;

/**
 * Supplies handover / clearance items when an offboarding case is opened.
 * Register implementations in config('lifecycle.handover_providers').
 *
 * The Assets module (later phase) implements this to return one "asset"
 * item per asset assigned to the employee, with `itemable` set to the asset
 * so marking the item done can record the return on the asset.
 */
interface HandoverItemProvider
{
    /**
     * @return list<array{category: string, assignee: string, title: string, description?: string|null, itemable_type?: string|null, itemable_id?: int|null}>
     */
    public function itemsFor(Employee $employee, OffboardingCase $case): array;
}
