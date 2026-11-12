<?php

namespace App\Services\Dashboard;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Attendance\BulkAttendance;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * The admin dashboard (spec module 1, Phase 8c): the widgets the admin may see
 * (WidgetRegistry: permission + data scope per widget) in their preferred order, each computed by
 * DashboardWidgets from live aggregate queries.
 *
 * Caching: each widget's data is cached for CACHE_SECONDS per admin, data scope, permission set
 * and filter values (a short TTL, no write-time busting — correctness over freshness is preferred,
 * so the "Refresh" action (?refresh=1) recomputes everything at once).
 */
class AdminDashboard
{
    public const CACHE_SECONDS = 120;

    /**
     * Longest dashboard date range, in days.
     */
    public const MAX_RANGE_DAYS = 366;

    public function __construct(private readonly WidgetRegistry $registry, private readonly DashboardWidgets $widgets) {}

    /**
     * Validation rules of the shared filter bar (all optional, persisted in the query string).
     *
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'department_id' => ['nullable', 'integer', 'min:1'],
            'group_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Validated filters with defaults: this month up to today.
     *
     * @param  array<string, mixed>  $validated
     * @return array{from: string, to: string, branch_id?: int, department_id?: int, group_id?: int}
     */
    public function filters(array $validated): array
    {
        $today = BulkAttendance::today();
        $filters = array_filter($validated, fn ($value) => $value !== null && $value !== '');
        $filters['from'] ??= $today->startOfMonth()->format('Y-m-d');
        $filters['to'] ??= max($today->format('Y-m-d'), $filters['from']);

        foreach (['branch_id', 'department_id', 'group_id'] as $key) {
            if (isset($filters[$key])) {
                $filters[$key] = (int) $filters[$key];
            }
        }

        if (CarbonImmutable::parse($filters['from'])->diffInDays(CarbonImmutable::parse($filters['to'])) > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['to' => __('reports.messages.range_too_long', ['days' => self::MAX_RANGE_DAYS])]);
        }

        ksort($filters);

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{key: string, title: string, size: string, icon: string, filters: list<string>, data: array<string, mixed>}>
     */
    public function widgets(User $admin, array $filters, bool $refresh = false): array
    {
        $widgets = [];
        $access = $this->accessHash($admin);

        foreach ($this->registry->visible($admin) as $key) {
            $definition = WidgetRegistry::WIDGETS[$key];
            $relevant = $this->relevantFilters($filters, $definition['filters']);
            $cacheKey = 'dashboard:'.$admin->id.':'.$key.':'.$access.':'.md5((string) json_encode($relevant));

            if ($refresh) {
                Cache::forget($cacheKey);
            }

            $data = Cache::remember($cacheKey, self::CACHE_SECONDS, fn (): array => $this->widgets->{$definition['method']}($filters, $admin));

            $widgets[] = [
                'key' => $key,
                'title' => __('dashboard.widgets.'.$key.'.title'),
                'size' => $definition['size'],
                'icon' => $definition['icon'],
                'filters' => $definition['filters'],
                'data' => $data,
            ];
        }

        return $widgets;
    }

    /**
     * Data of one widget without the cache (tests, API).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>|null null when the admin may not see the widget
     */
    public function widget(User $admin, string $key, array $filters): ?array
    {
        if (! $this->registry->canSee($admin, $key)) {
            return null;
        }

        return $this->widgets->{WidgetRegistry::WIDGETS[$key]['method']}($filters, $admin);
    }

    /**
     * Latest audit entries, for admins allowed to read the audit log.
     *
     * @return Collection<int, AuditLog>|null
     */
    public function recentActivity(User $admin, int $limit = 6): ?Collection
    {
        if (! $admin->can('audit_logs.view')) {
            return null;
        }

        return AuditLog::query()->with('user')->orderByDesc('id')->limit($limit)->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $honoured
     * @return array<string, mixed>
     */
    private function relevantFilters(array $filters, array $honoured): array
    {
        $map = ['date' => ['from', 'to'], 'branch' => ['branch_id'], 'department' => ['department_id'], 'group' => ['group_id']];
        $keys = array_merge(...array_map(fn (string $filter): array => $map[$filter] ?? [], $honoured ?: ['none']));

        return array_intersect_key($filters, array_flip($keys));
    }

    /**
     * Everything that changes what an admin may see: data scope, permissions, language, the day.
     */
    private function accessHash(User $admin): string
    {
        return md5((string) json_encode([
            $admin->dataScopeIds(),
            $admin->getAllPermissions()->pluck('name')->sort()->values()->all(),
            $admin->isSuperAdmin(),
            app()->getLocale(),
            BulkAttendance::today()->format('Y-m-d'),
        ]));
    }
}
