<?php

namespace App\Services\Authorization;

use InvalidArgumentException;

/**
 * Reads config/permissions.php, the single source of truth for admin
 * permissions. Permission names are always "module.action".
 */
class PermissionCatalogue
{
    /**
     * @return array<string, list<string>> module => supported actions
     */
    public function modules(): array
    {
        return config('permissions.modules');
    }

    /**
     * All actions, in matrix display order.
     *
     * @return list<string>
     */
    public function actions(): array
    {
        return config('permissions.actions');
    }

    /**
     * Every permission name in the catalogue.
     *
     * @return list<string>
     */
    public function all(): array
    {
        $names = [];

        foreach ($this->modules() as $module => $actions) {
            foreach ($actions as $action) {
                $names[] = $module.'.'.$action;
            }
        }

        return $names;
    }

    public function has(string $permission): bool
    {
        return in_array($permission, $this->all(), true);
    }

    /**
     * Default role definitions keyed by role name.
     *
     * @return array<string, array{description: string, permissions: list<string>}>
     */
    public function defaultRoles(): array
    {
        return config('permissions.roles');
    }

    /**
     * Expand "*", "module.*" and exact names into concrete permission names.
     *
     * @param  list<string>  $patterns
     * @return list<string>
     *
     * @throws InvalidArgumentException when a pattern matches nothing in the catalogue
     */
    public function expand(array $patterns): array
    {
        $names = [];

        foreach ($patterns as $pattern) {
            if ($pattern === '*') {
                return $this->all();
            }

            if (str_ends_with($pattern, '.*')) {
                $module = substr($pattern, 0, -2);
                $actions = $this->modules()[$module] ?? throw new InvalidArgumentException("Unknown permission module [{$module}].");

                foreach ($actions as $action) {
                    $names[] = $module.'.'.$action;
                }

                continue;
            }

            if (! $this->has($pattern)) {
                throw new InvalidArgumentException("Unknown permission [{$pattern}].");
            }

            $names[] = $pattern;
        }

        return array_values(array_unique($names));
    }
}
