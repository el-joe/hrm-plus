<?php

use App\Models\AuditLog;
use App\Models\LoginHistory;
use App\Models\Role;
use App\Models\User;

it('shows real counts on the dashboard of a Super Admin', function () {
    $admin = superAdmin();
    User::factory()->admin()->count(2)->create();
    LoginHistory::factory()->create(['successful' => false]);

    $stats = collect($this->actingAs($admin, 'admin')->get('/admin')->assertOk()->viewData('stats'))->keyBy('key');

    expect($stats->keys()->all())->toBe(['active_admins', 'roles', 'audit_today', 'failed_logins'])
        ->and($stats['active_admins']['value'])->toBe(User::query()->admins()->where('status', 'active')->count())
        ->and($stats['roles']['value'])->toBe(Role::query()->count())
        ->and($stats['failed_logins']['value'])->toBe(1);
});

it('only shows figures the admin has permission to see', function () {
    $response = $this->actingAs(adminWithPermissions('roles.view'), 'admin')->get('/admin')->assertOk();

    expect(array_column($response->viewData('stats'), 'key'))->toBe(['roles'])
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
