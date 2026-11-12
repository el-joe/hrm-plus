<?php

use App\Models\AuditLog;
use App\Models\LoginHistory;
use App\Models\Role;
use App\Models\User;

/*
| The Phase 2 system figures, kept as dashboard widgets in Phase 8c (App\Services\Dashboard\WidgetRegistry,
| "system" widgets: their own permission only).
*/

it('shows real counts on the dashboard of a Super Admin', function () {
    $admin = superAdmin();
    User::factory()->admin()->count(2)->create();
    LoginHistory::factory()->create(['successful' => false]);

    $widgets = collect($this->actingAs($admin, 'admin')->get('/admin')->assertOk()->viewData('widgets'))->keyBy('key');

    expect($widgets->keys()->all())->toContain('active_admins', 'roles', 'audit_today', 'failed_logins', 'payroll_pending')
        ->and($widgets['active_admins']['data']['raw'])->toBe(User::query()->admins()->where('status', 'active')->count())
        ->and($widgets['roles']['data']['raw'])->toBe(Role::query()->count())
        ->and($widgets['failed_logins']['data']['raw'])->toBe(1);
});

it('only shows figures the admin has permission to see', function () {
    $response = $this->actingAs(adminWithPermissions('roles.view'), 'admin')->get('/admin')->assertOk();

    expect(array_column($response->viewData('widgets'), 'key'))->toBe(['roles'])
        ->and($response->viewData('recentActivity'))->toBeNull();
});

it('lists recent audit activity for an admin allowed to read the audit log', function () {
    $admin = adminWithPermissions('audit_logs.view');
    AuditLog::factory()->create(['event' => 'payroll.exported']);

    $this->actingAs($admin, 'admin')
        ->get('/admin')
        ->assertOk()
        ->assertSee('payroll.exported');
});
