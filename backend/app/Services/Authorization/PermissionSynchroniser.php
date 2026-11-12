<?php

namespace App\Services\Authorization;

use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

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
 *   are never removed;
 * - a permission REMOVED from a default role's default set (Phase 8b) is revoked
 *   from that role only when sync itself granted it (role_default_grants.granted_by_sync)
 *   and no administrator re-granted it by hand since (manually_granted_at, set by
 *   AdminAccessManager); otherwise it is kept and reported. Either way the
 *   bookkeeping row is dropped, so the permission would be offered again if it
 *   returns to the default set.
 *
 * Run through `php artisan permissions:sync [--dry-run]` (also called by the seeder).
 * A dry run computes the same report inside a transaction that is rolled back.
 */
class PermissionSynchroniser
{
    public function __construct(
        private readonly PermissionCatalogue $catalogue,
        private readonly PermissionRegistrar $registrar,
    ) {}

    /**
     * @return array{created: list<string>, removed: list<string>, roles_created: list<string>, granted: array<string, list<string>>, revoked: array<string, list<string>>, kept: array<string, list<string>>}
     */
    public function sync(bool $dryRun = false): array
    {
        $guard = config('permissions.guard');
        $report = ['created' => [], 'removed' => [], 'roles_created' => [], 'granted' => [], 'revoked' => [], 'kept' => []];

        DB::beginTransaction();

        try {
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
                    $this->remember($role, $defaults, $defaults);

                    continue;
                }

                $held = $role->permissions()->pluck('name')->all();
                $this->revokeDropped($role, $name, $defaults, $held, $report);

                $alreadyOffered = DB::table('role_default_grants')->where('role_id', $role->getKey())->pluck('permission')->all();
                $toOffer = array_values(array_diff($defaults, $alreadyOffered));

                if ($toOffer === []) {
                    continue;
                }

                $grant = array_values(array_diff($toOffer, $held));

                if ($grant !== []) {
                    $role->givePermissionTo($grant);

                    if (! $isNew) {
                        $report['granted'][$name] = $grant;
                    }
                }

                $this->remember($role, $toOffer, $grant);
            }

            $this->registrar->forgetCachedPermissions();

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        } finally {
            $this->registrar->forgetCachedPermissions();
        }

        return $report;
    }

    /**
     * Permissions sync once offered to the role that are no longer in its default set: revoke the ones
     * sync granted and nobody re-granted by hand; keep (and report) the rest; forget them all.
     *
     * @param  list<string>  $defaults
     * @param  list<string>  $held
     * @param  array<string, mixed>  $report
     */
    private function revokeDropped(Role $role, string $name, array $defaults, array $held, array &$report): void
    {
        $dropped = DB::table('role_default_grants')->where('role_id', $role->getKey())
            ->whereNotIn('permission', $defaults === [] ? [''] : $defaults)->get();

        foreach ($dropped as $row) {
            if (in_array($row->permission, $held, true)) {
                if ($row->granted_by_sync && $row->manually_granted_at === null) {
                    $role->revokePermissionTo($row->permission);
                    $report['revoked'][$name][] = $row->permission;
                } else {
                    $report['kept'][$name][] = $row->permission;
                }
            }

            DB::table('role_default_grants')->where('role_id', $role->getKey())->where('permission', $row->permission)->delete();
        }
    }

    /**
     * @param  list<string>  $permissions  offered now
     * @param  list<string>  $granted  actually given by this sync (the rest the role already held)
     */
    private function remember(Role $role, array $permissions, array $granted): void
    {
        DB::table('role_default_grants')->insertOrIgnore(array_map(fn (string $permission): array => [
            'role_id' => $role->getKey(), 'permission' => $permission, 'granted_at' => now(),
            'granted_by_sync' => in_array($permission, $granted, true),
        ], $permissions));
    }

    /**
     * An administrator added these permissions to the role by hand: a later removal from the default set
     * must not revoke them (called by AdminAccessManager).
     *
     * @param  list<string>  $permissions
     */
    public function markManuallyGranted(Role $role, array $permissions): void
    {
        if ($permissions === []) {
            return;
        }

        DB::table('role_default_grants')->where('role_id', $role->getKey())->whereIn('permission', $permissions)
            ->update(['manually_granted_at' => now()]);
    }
}
