<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Records with an `is_active` flag. Inactive records stay attached to
 * whatever already references them but are no longer offered for new
 * assignments.
 *
 * @mixin Model
 */
trait HasActiveFlag
{
    public function initializeHasActiveFlag(): void
    {
        $this->mergeCasts(['is_active' => 'boolean']);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($query->qualifyColumn('is_active'), true);
    }

    /**
     * Active rows, plus the given id even when inactive (so an edit form keeps
     * showing the value a record already has).
     *
     * @param  Builder<static>  $query
     */
    public function scopeActiveOrKeep(Builder $query, int|string|null $keepId): void
    {
        $query->where(function (Builder $query) use ($keepId): void {
            $query->where($query->qualifyColumn('is_active'), true);

            if (filled($keepId)) {
                $query->orWhere($query->qualifyColumn($this->getKeyName()), $keepId);
            }
        });
    }
}
