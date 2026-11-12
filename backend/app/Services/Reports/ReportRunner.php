<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Services\Employees\SensitiveDataAccessLog;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Runs a report for an admin: the columns they may see (guards), one page of rows for the
 * screen, totals (bcmath) over every row, the chart, and the full row stream for exports.
 * Logs the sensitive-data access when guarded columns (salary, disciplinary) are shown.
 */
class ReportRunner
{
    /**
     * Totals and charts are computed over at most this many rows on screen.
     */
    public const SCREEN_TOTALS_LIMIT = 20000;

    public function __construct(private readonly SensitiveDataAccessLog $sensitive) {}

    /**
     * @return array<string, Column>
     */
    public function columns(Report $report, User $user): array
    {
        return array_filter($report->columns(), fn (Column $column): bool => $column->permission() === null || $user->can($column->permission()));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{rows: LengthAwarePaginator<int, array<string, mixed>>, totals: array<string, string>|null, chart: array<string, mixed>|null, sort: ?string, direction: string, sortableKeys: list<string>}
     */
    public function screen(Report $report, array $filters, User $user, ?string $sort, string $direction, int $perPage = 25): array
    {
        $columns = $this->columns($report, $user);
        $source = $report->query($filters, $user);
        $sort = $sort !== null && array_key_exists($sort, $columns) && ($source instanceof Collection || array_key_exists($sort, $report->sortable())) ? $sort : null;
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        if ($source instanceof Builder) {
            if ($sort !== null) {
                $source->reorder()->orderByRaw($report->sortable()[$sort].' '.$direction);
            }

            $page = $source->paginate($perPage)->withQueryString();
            $report->prepareRows($page->getCollection(), $filters);
            $mapped = $page->getCollection()->map(fn ($record): array => $this->only($report->row($record, $filters), $columns));
            $page->setCollection($mapped);
            $total = $page->total();
            $all = $report->hasChart() || $this->hasTotals($columns) ? ($total <= self::SCREEN_TOTALS_LIMIT ? collect($this->stream($report, $filters, $user)) : null) : collect();
        } else {
            $report->prepareRows($source, $filters);
            $all = $this->sorted($source->map(fn ($record): array => $this->only($report->row($record, $filters), $columns))->values(), $sort, $direction);
            $current = LengthAwarePaginator::resolveCurrentPage();
            $page = new LengthAwarePaginator($all->forPage($current, $perPage)->values(), $all->count(), $perPage, $current, [
                'path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => request()->query(),
            ]);
        }

        $this->logSensitive($report, $columns, $user, 'screen', $filters);

        return [
            'rows' => $page,
            'totals' => $all === null || ! $this->hasTotals($columns) ? null : $this->totals($all, $columns),
            'chart' => $all !== null && $report->hasChart() ? $report->chart($all, $filters) : null,
            'sort' => $sort,
            'direction' => $direction,
            'sortableKeys' => $source instanceof Collection ? array_keys($columns) : array_values(array_intersect(array_keys($columns), array_keys($report->sortable()))),
        ];
    }

    /**
     * Every row (visible columns only), streamed.
     *
     * @param  array<string, mixed>  $filters
     * @return Generator<int, array<string, mixed>>
     */
    public function stream(Report $report, array $filters, User $user): Generator
    {
        $columns = $this->columns($report, $user);
        $source = $report->query($filters, $user);

        if ($source instanceof Collection) {
            $report->prepareRows($source, $filters);

            foreach ($source as $record) {
                yield $this->only($report->row($record, $filters), $columns);
            }

            return;
        }

        foreach ($source->lazy(500)->chunk(500) as $chunk) {
            $records = collect($chunk->all());
            $report->prepareRows($records, $filters);

            foreach ($records as $record) {
                yield $this->only($report->row($record, $filters), $columns);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function count(Report $report, array $filters, User $user): int
    {
        $source = $report->query($filters, $user);

        return $source instanceof Collection ? $source->count() : $source->toBase()->getCountForPagination();
    }

    /**
     * @param  iterable<array<string, mixed>>  $rows
     * @param  array<string, Column>  $columns
     * @return array<string, string>
     */
    public function totals(iterable $rows, array $columns): array
    {
        $totals = [];

        foreach ($columns as $key => $column) {
            if ($column->total && $column->isNumeric()) {
                $totals[$key] = '0';
            }
        }

        foreach ($rows as $row) {
            foreach ($totals as $key => $sum) {
                $value = $row[$key] ?? null;

                if ($value !== null && $value !== '' && is_numeric($value)) {
                    $totals[$key] = bcadd($sum, (string) $value, 4);
                }
            }
        }

        foreach ($totals as $key => $sum) {
            $column = $columns[$key];
            $totals[$key] = $column->type === 'number' ? bcadd($sum, '0', 0) : bcadd($sum, '0', $column->type === 'money' ? (int) setting('localisation.currency_decimals') : $column->decimals);
        }

        return $totals;
    }

    /**
     * @param  array<string, Column>  $columns
     * @param  array<string, mixed>  $filters
     */
    public function logSensitive(Report $report, array $columns, User $user, string $context, array $filters, ?string $format = null): void
    {
        $guards = collect($columns)->map(fn (Column $column): ?string => $column->guard)->filter()->unique();

        foreach ($guards as $guard) {
            $this->sensitive->record(
                $guard === 'disciplinary' ? SensitiveDataAccessLog::DISCIPLINARY : SensitiveDataAccessLog::FINANCIAL,
                null,
                array_keys(array_filter($columns, fn (Column $column): bool => $column->guard === $guard)),
                array_filter(['report' => $report->key(), 'context' => $context, 'format' => $format, 'filters' => $filters]),
                $user,
            );
        }
    }

    /**
     * @param  array<string, Column>  $columns
     */
    private function hasTotals(array $columns): bool
    {
        foreach ($columns as $column) {
            if ($column->total && $column->isNumeric()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, Column>  $columns
     * @return array<string, mixed>
     */
    private function only(array $row, array $columns): array
    {
        $visible = [];

        foreach ($columns as $key => $column) {
            $visible[$key] = $row[$key] ?? null;
        }

        return $visible;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function sorted(Collection $rows, ?string $sort, string $direction): Collection
    {
        if ($sort === null) {
            return $rows;
        }

        $sorted = $rows->sort(function (array $a, array $b) use ($sort): int {
            $left = $a[$sort] ?? null;
            $right = $b[$sort] ?? null;

            if (is_numeric($left) && is_numeric($right)) {
                return bccomp((string) $left, (string) $right, 4);
            }

            return strnatcasecmp((string) $left, (string) $right);
        });

        return ($direction === 'desc' ? $sorted->reverse() : $sorted)->values();
    }
}
