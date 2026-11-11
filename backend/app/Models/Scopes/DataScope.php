<?php

namespace App\Models\Scopes;

use App\Services\Authorization\DataScopeManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Applied by the HasDataScope trait. It restricts queries only while an admin
 * is active in the DataScopeManager (set by the admin area middleware), so
 * console commands, the employee portal and the mobile API are unaffected.
 */
class DataScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $manager = app(DataScopeManager::class);

        if ($admin = $manager->activeAdmin()) {
            $manager->apply($builder, $admin);
        }
    }
}
