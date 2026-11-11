<?php

use App\Support\Navigation\NavigationBuilder;

/**
 * @return list<string> item keys of every sidebar section
 */
function sidebarKeys(array $sections): array
{
    return collect($sections)->flatMap(fn (array $section): array => array_column($section['items'], 'key'))->all();
}

it('shows every sidebar entry to a Super Admin', function () {
    $sections = app(NavigationBuilder::class)->admin(superAdmin(), 'admin.dashboard');

    expect(sidebarKeys($sections))->toBe([
        'dashboard',
        'employees', 'employee_groups', 'employee_custom_fields',
        'vacancies', 'candidates', 'interviews', 'offers', 'recruitment_stages', 'evaluation_forms',
        'onboarding', 'offboarding', 'onboarding_templates',
        'attendance_dashboard', 'attendance_daily', 'attendance_timesheet', 'attendance_calendar', 'attendance_bulk', 'attendance_batches',
        'attendance_corrections', 'attendance_reviews', 'attendance_events', 'attendance_reports', 'attendance_locks',
        'attendance_devices', 'attendance_work_locations', 'face_enrolments',
        'schedule_calendar', 'shifts', 'work_schedules', 'schedule_assignments', 'schedule_changes', 'holidays', 'attendance_policies', 'attendance_statuses',
        'company', 'org_chart', 'branches', 'departments', 'teams', 'positions', 'job_titles', 'job_grades', 'employment_types', 'cost_centers',
        'admin_users', 'roles', 'audit_logs', 'settings', 'ui_kit',
    ]);
});

it('keeps only the entries matching the admin permissions', function () {
    $sections = app(NavigationBuilder::class)->admin(adminWithPermissions('audit_logs.view'), 'admin.dashboard');

    expect(sidebarKeys($sections))->toBe(['dashboard', 'audit_logs'])
        ->and(array_column($sections, 'key'))->toBe(['main', 'system']);
});

it('drops a section with no visible entry', function () {
    $sections = app(NavigationBuilder::class)->admin(adminWithRoles('Attendance Officer'), 'admin.dashboard');

    expect(array_column($sections, 'key'))->not->toContain('permissions', 'system');
});

it('marks the entry of the current route as active', function () {
    $sections = app(NavigationBuilder::class)->admin(superAdmin(), 'admin.roles.edit');

    $active = collect($sections)->flatMap(fn (array $section): array => $section['items'])->where('active', true)->pluck('key')->all();

    expect($active)->toBe(['roles']);
});

it('renders only permitted links in the sidebar', function () {
    $this->actingAs(adminWithPermissions('roles.view'), 'admin')
        ->get('/admin')
        ->assertOk()
        ->assertSee('data-nav-item="roles"', false)
        ->assertSee(route('admin.roles.index'))
        ->assertDontSee('data-nav-item="admin_users"', false)
        ->assertDontSee('data-nav-item="settings"', false)
        ->assertDontSee('data-nav-item="ui_kit"', false)
        ->assertDontSee(route('admin.ui-kit'));
});
