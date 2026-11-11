<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * The list conventions of /api/v1, in one place:
 *
 *     ?filter[status]=approved&filter[type]=annual   exact-match or custom filters
 *     ?sort=-created_at,name                         "-" prefix = descending
 *     ?page=2&per_page=20                            per_page capped at MAX_PER_PAGE
 *
 * Filters and sorts are allow-listed; anything else is a 422.
 *
 *     ApiListQuery::for($query, $request)
 *         ->allowFilters(['status', 'from' => fn (Builder $q, string $v) => $q->whereDate('date', '>=', $v)])
 *         ->allowSorts(['created_at', 'name'])
 *         ->defaultSort('-created_at')
 *         ->paginate();
 */
class ApiListQuery
{
    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 100;

    /** @var array<string, Closure|null> */
    private array $filters = [];

    /** @var list<string> */
    private array $sorts = [];

    private string $defaultSort = '-id';

    /**
     * @param  Builder<*>  $query
     */
    private function __construct(private readonly Builder $query, private readonly Request $request) {}

    /**
     * @param  Builder<*>  $query
     */
    public static function for(Builder $query, Request $request): self
    {
        return new self($query, $request);
    }

    /**
     * @param  array<int|string, string|Closure>  $filters  Column names (exact match) or name => fn (Builder, string $value)
     */
    public function allowFilters(array $filters): self
    {
        foreach ($filters as $name => $filter) {
            if ($filter instanceof Closure) {
                $this->filters[$name] = $filter;
            } else {
                $this->filters[$filter] = null;
            }
        }

        return $this;
    }

    /**
     * @param  list<string>  $columns
     */
    public function allowSorts(array $columns): self
    {
        $this->sorts = $columns;

        return $this;
    }

    public function defaultSort(string $sort): self
    {
        $this->defaultSort = $sort;

        return $this;
    }

    /**
     * @throws ValidationException
     */
    public function paginate(): LengthAwarePaginator
    {
        $this->applyFilters();
        $this->applySorts();

        $perPage = (int) $this->request->query('per_page', (string) self::DEFAULT_PER_PAGE);

        return $this->query
            ->paginate(max(1, min($perPage, self::MAX_PER_PAGE)))
            ->withQueryString();
    }

    private function applyFilters(): void
    {
        $requested = $this->request->query('filter', []);

        if (! is_array($requested)) {
            throw ValidationException::withMessages(['filter' => __('api.invalid_filter', ['filter' => 'filter'])]);
        }

        foreach ($requested as $name => $value) {
            if (! array_key_exists($name, $this->filters) || is_array($value)) {
                throw ValidationException::withMessages(['filter' => __('api.invalid_filter', ['filter' => (string) $name])]);
            }

            if ($value === null || $value === '') {
                continue;
            }

            $filter = $this->filters[$name];

            if ($filter !== null) {
                $filter($this->query, (string) $value);
            } else {
                $this->query->where($this->query->qualifyColumn($name), $value);
            }
        }
    }

    private function applySorts(): void
    {
        $requested = $this->request->query('sort');
        $sorts = is_string($requested) && $requested !== '' ? $requested : $this->defaultSort;

        foreach (explode(',', $sorts) as $sort) {
            $column = ltrim($sort, '-');

            if ($sorts !== $this->defaultSort && ! in_array($column, $this->sorts, true)) {
                throw ValidationException::withMessages(['sort' => __('api.invalid_sort', ['sort' => $column])]);
            }

            $this->query->orderBy($this->query->qualifyColumn($column), str_starts_with($sort, '-') ? 'desc' : 'asc');
        }

        // Stable tie-breaker so pages never overlap.
        $this->query->orderBy($this->query->getModel()->getQualifiedKeyName());
    }
}
