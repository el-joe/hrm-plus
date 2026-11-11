<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Self-referencing trees (departments, cost centres, the position reporting
 * line). The model declares which column points at its parent through
 * hierarchyParentColumn(); this trait answers "which rows sit below this one",
 * ignoring data scopes so that cycle checks see the whole tree.
 *
 * @mixin Model
 */
trait HasHierarchy
{
    abstract public function hierarchyParentColumn(): string;

    /**
     * Ids of every row below this one, at any depth.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $descendants = [];
        $frontier = [$this->getKey()];

        while ($frontier !== []) {
            $frontier = $this->newQueryWithoutScopes()
                ->whereIn($this->hierarchyParentColumn(), $frontier)
                ->whereNotIn($this->getKeyName(), $descendants)
                ->pluck($this->getKeyName())
                ->map(fn ($id): int => (int) $id)
                ->all();

            array_push($descendants, ...$frontier);
        }

        return $descendants;
    }

    /**
     * Whether the given id may become this row's parent (not itself, not one of its descendants).
     */
    public function canHaveParent(int|string|null $parentId): bool
    {
        if (blank($parentId) || ! $this->exists) {
            return true;
        }

        return (int) $parentId !== (int) $this->getKey() && ! in_array((int) $parentId, $this->descendantIds(), true);
    }
}
