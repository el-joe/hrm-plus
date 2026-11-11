<?php

namespace App\Services\Authorization;

use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Brings the database in line with config/permissions.php, idempotently and
 * without undoing client configuration:
 *
 * - catalogue permissions that are missing are created; permissions no longer
 *   in the catalogue are removed;
 * - missing default roles are created with their default permissions;
 * - Super Admin always holds every permission;
 * - every other default role receives each permission of its default set
 *   ONCE: role_default_grants remembers what was granted, so a permission an
 *   administrator later removes from the role is never granted back, while a
 *   permission added to the catalogue in a later release is. Custom grants
 *   are never removed.
 *
 * Run through `php artisan permissions:sync` (also called by the seeder).
 */
class PermissionSynchroniser
{
    public function __construct(
        private readonly PermissionCatalogue $catalogue,
        private readonly PermissionRegistrar $registrar,
    ) {}

    /**
     * @return array{created: list<string>, removed: list<string>, roles_created: list<string>, granted: array<string, list<string>>}
     */
    public function sync(): array
    {
        $guard = config('permissions.guard');
        $report = ['created' => [], 'removed' => [], 'roles_created' => [], 'granted' => []];

        DB::transaction(function () use ($guard, &$report): void {
            $this->registrar->forgetCachedPermissions();
            $existing = Permission::query()->where('guard_name', $guard)->pluck('name')->all();

            foreach (array_diff($this->catalogue->all(), $existing) as $name) {
                Permission::findOrCreate($name, $guard);
                $report['created'][] = $name;
            }

            $stale = Permission::query()->where('guard_name', $guard)->whereNotIn('name', $this->catalogue->all());
            $report['removed'] = $stale->pluck('name')->all();
            $stale->delete();

            // Seeders may run without model events, so the cache is not flushed for us.
            $this->registrar->forgetCachedPermissions();

            foreach ($this->catalogue->defaultRoles() as $name => $definition) {
                $role = Role::query()->firstOrNew(['name' => $name, 'guard_name' => $guard]);
                $isNew = ! $role->exists;
                $role->fill(['is_system' => true, 'description' => $role->description ?? $definition['description']])->save();
                $defaults = $this->catalogue->expand($definition['permissions']);

                if ($isNew) {
                    $report['roles_created'][] = $name;
                }

                if ($role->isSuperAdminRole()) {
                    $role->syncPermissions($defaults);
                    $this->remember($role, $defaults);

                    continue;
                }

                $alreadyOffered = DB::table('role_default_grants')->where('role_id', $role->getKey())->pluck('permission')->all();
                $toOffer = array_values(array_diff($defaults, $alreadyOffered));

                if ($toOffer === []) {
                    continue;
                }

                $held = $role->permissions()->pluck('name')->all();
                $grant = array_values(array_diff($toOffer, $held));

                if ($grant !== []) {
                    $role->givePermissionTo($grant);

                    if (! $isNew) {
                        $report['granted'][$name] = $grant;
                    }
                }

                $this->remember($role, $toOffer);
            }

            $this->registrar->forgetCachedPermissions();
        });

        return $report;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function remember(Role $role, array $permissions): void
    {
        DB::table('role_default_grants')->insertOrIgnore(array_map(fn (string $permission): array => [
            'role_id' => $role->getKey(), 'permission' => $permission, 'granted_at' => now(),
        ], $permissions));
    }
}
