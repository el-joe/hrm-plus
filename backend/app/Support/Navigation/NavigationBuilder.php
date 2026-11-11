<?php

namespace App\Support\Navigation;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Builds the navigation of the admin sidebar and the employee portal from
 * config/navigation.php, keeping only the entries the user may open.
 */
class NavigationBuilder
{
    /**
     * Sidebar sections of the admin area; sections left without items are dropped.
     *
     * @return list<array{key: string, label: string, items: list<array{key: string, label: string, url: string, icon: string, active: bool}>}>
     */
    public function admin(User $user, ?string $currentRoute = null): array
    {
        $sections = [];

        foreach (config('navigation.admin', []) as $section) {
            $items = $this->visibleItems($section['items'] ?? [], $user, $currentRoute);

            if ($items !== []) {
                $sections[] = [
                    'key' => $section['key'],
                    'label' => __('app.nav.sections.'.$section['key']),
                    'items' => $items,
                ];
            }
        }

        return $sections;
    }

    /**
     * Top-level items of the employee portal.
     *
     * @return list<array{key: string, label: string, url: string, icon: string, active: bool}>
     */
    public function portal(User $user, ?string $currentRoute = null): array
    {
        return $this->visibleItems(config('navigation.portal', []), $user, $currentRoute);
    }

    /**
     * @param  list<array{key: string, route: string, ability: ?string, icon: string, active?: list<string>}>  $items
     * @return list<array{key: string, label: string, url: string, icon: string, active: bool}>
     */
    private function visibleItems(array $items, User $user, ?string $currentRoute): array
    {
        $visible = [];

        foreach ($items as $item) {
            if (! Route::has($item['route'])) {
                continue;
            }

            if ($item['ability'] !== null && ! $user->can($item['ability'])) {
                continue;
            }

            $visible[] = [
                'key' => $item['key'],
                'label' => __('app.nav.'.$item['key']),
                'url' => route($item['route']),
                'icon' => $item['icon'],
                'active' => $currentRoute !== null && Str::is($item['active'] ?? [$item['route']], $currentRoute),
            ];
        }

        return $visible;
    }
}
