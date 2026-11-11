<?php

namespace App\Models\Contracts;

/**
 * A record that may only be deleted while nothing references it.
 *
 * usageCounts() returns "what still uses me" as a translation key under
 * structure.usage (e.g. "departments") => count. Later modules add their own
 * references (Phase 3b: employees) by extending the method of the model.
 * App\Actions\Structure\DeleteStructureRecord calls it with data scopes
 * switched off so references outside the admin's scope count too.
 */
interface GuardsDeletion
{
    /**
     * @return array<string, int>
     */
    public function usageCounts(): array;
}
