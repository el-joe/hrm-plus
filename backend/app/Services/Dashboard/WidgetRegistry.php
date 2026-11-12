<?php

namespace App\Services\Dashboard;

use App\Models\DashboardPreference;
use App\Models\User;

/**
 * Registry of the admin dashboard widgets (spec module 1 — 22 widgets — plus the five system
 * figures of Phase 2). Each widget:
 *
 *   any       the admin needs ONE of these permissions (the source module's view permission, or
 *             reports.manage: every report area — the aggregates the reports centre already offers;
 *             Phase 11: plain reports.view no longer shows other modules' figures);
 *   requires  permissions needed on top (money → employee_financials.view);
 *   scope     how the data scope applies (documentation; enforced by the scoped models / queries);
 *   filters   which dashboard filters it honours (date range, branch, department, employee group);
 *   size      sm (one cell), md (two), lg (full row on wide screens);
 *   method    App\Services\Dashboard\DashboardWidgets::{method}.
 *
 * System figures (system => true) keep their Phase 2 rule: only their own permission, no dashboard.view.
 *
 * Per-admin visibility and order come from dashboard_preferences; a widget the admin may not see is
 * never shown whatever the preference says.
 */
class WidgetRegistry
{
    /**
     * @var array<string, array{system?: bool, any: list<string>, requires?: list<string>, scope: string, filters: list<string>, size: string, icon: string, method: string}>
     */
    public const WIDGETS = [
        // Module 1 (spec order).
        'total_employees' => ['any' => ['employees.view', 'reports.manage'], 'scope' => 'employee', 'filters' => ['branch', 'department', 'group'], 'size' => 'sm', 'icon' => 'users', 'method' => 'totalEmployees'],
        'active_employees' => ['any' => ['employees.view', 'reports.manage'], 'scope' => 'employee', 'filters' => ['branch', 'department', 'group'], 'size' => 'sm', 'icon' => 'check-circle', 'method' => 'activeEmployees'],
        'inactive_employees' => ['any' => ['employees.view', 'reports.manage'], 'scope' => 'employee', 'filters' => ['branch', 'department', 'group'], 'size' => 'sm', 'icon' => 'minus-circle', 'method' => 'inactiveEmployees'],
        'employees_on_leave' => ['any' => ['leaves.view', 'reports.manage'], 'scope' => 'employee', 'filters' => ['branch', 'department', 'group'], 'size' => 'sm', 'icon' => 'calendar', 'method' => 'employeesOnLeave'],
        'absent_today' => ['any' => ['attendance.view', 'reports.manage'], 'scope' => 'employee', 'filters' => ['branch', 'department', 'group'], 'size' => 'sm', 'icon' => 'x-circle', 'method' => 'absentToday'],
        'present_now' => ['any' => ['attendance.view', 'reports.manage'], 'scope' => 'employee', 'filters' => ['branch', 'department', 'group'], 'size' => 'sm', 'icon' => 'log-in', 'method' => 'presentNow'],
        'late_arrivals' => ['any' => ['attendance.view', 'reports.manage'], 'scope' => 'attendance day (branch / department snapshot)', 'filters' => ['date', 'branch', 'department', 'group'], 'size' => 'sm', 'icon' => 'clock', 'method' => 'lateArrivals'],
        'pending_leave_requests' => ['any' => ['leaves.view', 'reports.manage'], 'scope' => 'employee', 'filters' => ['branch', 'department', 'group'], 'size' => 'sm', 'icon' => 'inbox', 'method' => 'pendingLeaveRequests'],
        'pending_hr_requests' => ['any' => ['hr_requests.view', 'reports.manage'], 'scope' => 'employee + HrRequestVisibility', 'filters' => ['branch', 'department', 'group'], 'size' => 'sm', 'icon' => 'message-square', 'method' => 'pendingHrRequests'],
        'new_candidates' => ['any' => ['recruitment.view', 'reports.manage'], 'scope' => 'vacancy', 'filters' => ['date', 'branch', 'department'], 'size' => 'sm', 'icon' => 'user-plus', 'method' => 'newCandidates'],
        'open_vacancies' => ['any' => ['recruitment.view', 'reports.manage'], 'scope' => 'vacancy', 'filters' => ['branch', 'department'], 'size' => 'sm', 'icon' => 'briefcase', 'method' => 'openVacancies'],
        'contract_expirations' => ['any' => ['employees.view', 'reports.manage'], 'scope' => 'employee', 'filters' => ['branch', 'department', 'group'], 'size' => 'md', 'icon' => 'file-text', 'method' => 'contractExpirations'],
        'upcoming_birthdays' => ['any' => ['employees.view', 'reports.manage'], 'scope' => 'employee', 'filters' => ['branch', 'department', 'group'], 'size' => 'md', 'icon' => 'award', 'method' => 'upcomingBirthdays'],
        'upcoming_anniversaries' => ['any' => ['employees.view', 'reports.manage'], 'scope' => 'employee', 'filters' => ['branch', 'department', 'group'], 'size' => 'md', 'icon' => 'history', 'method' => 'upcomingAnniversaries'],
        'payroll_summary' => ['any' => ['payroll.view', 'reports.manage'], 'requires' => ['employee_financials.view'], 'scope' => 'payroll item (period-end placement)', 'filters' => ['date', 'branch', 'department', 'group'], 'size' => 'md', 'icon' => 'coins', 'method' => 'payrollSummary'],
        'monthly_salary_expenses' => ['any' => ['payroll.view', 'reports.manage'], 'requires' => ['employee_financials.view'], 'scope' => 'payroll item (period-end placement)', 'filters' => ['date', 'branch', 'department', 'group'], 'size' => 'md', 'icon' => 'bar-chart', 'method' => 'monthlySalaryExpenses'],
        'branch_comparison' => ['any' => ['employees.view', 'reports.manage'], 'scope' => 'employee', 'filters' => ['date', 'department', 'group'], 'size' => 'md', 'icon' => 'building', 'method' => 'branchComparison'],
        'department_comparison' => ['any' => ['employees.view', 'reports.manage'], 'scope' => 'employee', 'filters' => ['date', 'branch', 'group'], 'size' => 'md', 'icon' => 'sitemap', 'method' => 'departmentComparison'],
        'recruitment_statistics' => ['any' => ['recruitment.view', 'reports.manage'], 'scope' => 'vacancy', 'filters' => ['date', 'branch', 'department'], 'size' => 'md', 'icon' => 'briefcase', 'method' => 'recruitmentStatistics'],
        'attendance_trends' => ['any' => ['attendance.view', 'reports.manage'], 'scope' => 'attendance day (branch / department snapshot)', 'filters' => ['date', 'branch', 'department', 'group'], 'size' => 'lg', 'icon' => 'trend-up', 'method' => 'attendanceTrends'],
        'employee_turnover' => ['any' => ['employees.view', 'reports.manage'], 'scope' => 'employee', 'filters' => ['date', 'branch', 'department', 'group'], 'size' => 'md', 'icon' => 'refresh', 'method' => 'employeeTurnover'],
        'employee_distribution' => ['any' => ['employees.view', 'reports.manage'], 'scope' => 'employee', 'filters' => ['branch', 'department', 'group'], 'size' => 'md', 'icon' => 'layout-grid', 'method' => 'employeeDistribution'],

        // System figures (Phase 2, kept).
        'payroll_pending' => ['system' => true, 'any' => ['payroll.view'], 'scope' => 'payroll run', 'filters' => [], 'size' => 'sm', 'icon' => 'coins', 'method' => 'payrollPending'],
        'active_admins' => ['system' => true, 'any' => ['admin_users.view'], 'scope' => 'none', 'filters' => [], 'size' => 'sm', 'icon' => 'users', 'method' => 'activeAdmins'],
        'roles' => ['system' => true, 'any' => ['roles.view'], 'scope' => 'none', 'filters' => [], 'size' => 'sm', 'icon' => 'shield', 'method' => 'roles'],
        'audit_today' => ['system' => true, 'any' => ['audit_logs.view'], 'scope' => 'none', 'filters' => [], 'size' => 'sm', 'icon' => 'history', 'method' => 'auditToday'],
        'failed_logins' => ['system' => true, 'any' => ['admin_users.view'], 'scope' => 'none', 'filters' => [], 'size' => 'sm', 'icon' => 'lock', 'method' => 'failedLogins'],
    ];

    /**
     * The 22 widgets of spec module 1, in spec order.
     */
    public const SPEC_WIDGETS = [
        'total_employees', 'active_employees', 'inactive_employees', 'employees_on_leave', 'absent_today', 'present_now', 'late_arrivals',
        'pending_leave_requests', 'pending_hr_requests', 'new_candidates', 'open_vacancies', 'contract_expirations', 'upcoming_birthdays',
        'upcoming_anniversaries', 'payroll_summary', 'monthly_salary_expenses', 'branch_comparison', 'department_comparison',
        'recruitment_statistics', 'attendance_trends', 'employee_turnover', 'employee_distribution',
    ];

    public function canSee(User $user, string $key): bool
    {
        $widget = self::WIDGETS[$key] ?? null;

        if ($widget === null || ! $user->isAdmin() || (! ($widget['system'] ?? false) && ! $user->can('dashboard.view'))) {
            return false;
        }

        $any = collect($widget['any'])->contains(fn (string $permission): bool => $user->can($permission));

        return $any && collect($widget['requires'] ?? [])->every(fn (string $permission): bool => $user->can($permission));
    }

    /**
     * Widgets the admin may see, in their preferred order (unknown / newer widgets keep the default order at the end).
     *
     * @return list<string>
     */
    public function available(User $user): array
    {
        $allowed = array_values(array_filter(array_keys(self::WIDGETS), fn (string $key): bool => $this->canSee($user, $key)));
        $order = $this->preference($user)?->order ?? [];
        $rank = array_flip(array_values(array_intersect($order, $allowed)));
        $default = array_flip($allowed);

        usort($allowed, fn (string $a, string $b): int => [$rank[$a] ?? PHP_INT_MAX, $default[$a]] <=> [$rank[$b] ?? PHP_INT_MAX, $default[$b]]);

        return $allowed;
    }

    /**
     * Widgets shown on the dashboard: available minus the ones the admin hid.
     *
     * @return list<string>
     */
    public function visible(User $user): array
    {
        $hidden = $this->preference($user)?->hidden ?? [];

        return array_values(array_diff($this->available($user), $hidden));
    }

    /**
     * @param  list<string>  $order
     * @param  list<string>  $hidden
     */
    public function save(User $user, array $order, array $hidden): DashboardPreference
    {
        $known = array_keys(self::WIDGETS);

        return DashboardPreference::query()->updateOrCreate(['user_id' => $user->id], [
            'order' => array_values(array_intersect($order, $known)),
            'hidden' => array_values(array_intersect($hidden, $known)),
        ]);
    }

    public function preference(User $user): ?DashboardPreference
    {
        return DashboardPreference::query()->where('user_id', $user->id)->first();
    }
}
