<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Server-side sorting of admin list pages, driven by the `?sort=&direction=`
 * links of <x-table.th sort="...">. Only allow-listed keys are honoured; an
 * unknown key or direction silently falls back to the default.
 *
 *     IndexSorting::apply($query, $request, [
 *         'code' => 'code',
 *         'name' => Branch::localizedColumn(),
 *         'department' => fn (Builder $query, string $direction) => $query->orderBy(...),
 *     ], default: 'name');
 */
class IndexSorting
{
    /**
     * @param  Builder<*>  $query
     * @param  array<string, string|Closure(Builder<*>, string): mixed>  $columns  sort key => column or callback
     * @return array{sort: string, direction: string} the sort that was applied
     */
    public static function apply(Builder $query, Request $request, array $columns, string $default, string $defaultDirection = 'asc'): array
    {
        $sort = $request->query('sort');
        $sort = is_string($sort) && array_key_exists($sort, $columns) ? $sort : $default;
        $direction = $request->query('direction') === 'desc' ? 'desc' : ($request->query('direction') === 'asc' ? 'asc' : $defaultDirection);

        $column = $columns[$sort];

        if ($column instanceof Closure) {
            $column($query, $direction);
        } else {
            $query->orderBy($query->qualifyColumn($column), $direction);
        }

        // Stable tie-breaker so pages never overlap.
        $query->orderBy($query->getModel()->getQualifiedKeyName());

        return ['sort' => $sort, 'direction' => $direction];
    }
}
