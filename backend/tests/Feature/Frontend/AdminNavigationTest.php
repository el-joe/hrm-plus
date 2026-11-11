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
