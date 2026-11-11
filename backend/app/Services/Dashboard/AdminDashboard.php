<?php

namespace App\Services\Dashboard;

use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\LoginHistory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Figures shown on the admin dashboard. Each figure is only computed for an
 * admin holding the permission of the screen it summarises, so the
 * dashboard never reveals more than the admin could open anyway.
 *
 * Phase 2 has no HR data yet; later modules add their KPIs here.
 */
class AdminDashboard
{
    /**
     * @return list<array{key: string, label: string, value: int, icon: string, url: ?string, hint: ?string}>
     */
    public function stats(User $admin): array
    {
        $stats = [];

        if ($admin->can('admin_users.view')) {
            $stats[] = [
                'key' => 'active_admins',
                'label' => __('app.dashboard.stats.active_admins'),
                'value' => User::query()->admins()->where('status', UserStatus::Active)->count(),
                'icon' => 'users',
                'url' => route('admin.admin-users.index', ['status' => UserStatus::Active->value]),
                'hint' => null,
            ];
        }

        if ($admin->can('roles.view')) {
            $stats[] = [
                'key' => 'roles',
                'label' => __('app.dashboard.stats.roles'),
                'value' => Role::query()->count(),
                'icon' => 'shield',
                'url' => route('admin.roles.index'),
                'hint' => null,
            ];
        }

        if ($admin->can('audit_logs.view')) {
            $stats[] = [
                'key' => 'audit_today',
                'label' => __('app.dashboard.stats.audit_today'),
                'value' => AuditLog::query()->where('created_at', '>=', today())->count(),
                'icon' => 'history',
                'url' => route('admin.audit-logs.index', ['date_from' => today()->toDateString()]),
                'hint' => null,
            ];
        }

        if ($admin->can('admin_users.view')) {
            $stats[] = [
                'key' => 'failed_logins',
                'label' => __('app.dashboard.stats.failed_logins'),
                'value' => LoginHistory::query()->where('successful', false)->where('created_at', '>=', now()->subDay())->count(),
                'icon' => 'lock',
                'url' => null,
                'hint' => __('app.dashboard.stats.last_24_hours'),
            ];
        }

        return $stats;
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
}
