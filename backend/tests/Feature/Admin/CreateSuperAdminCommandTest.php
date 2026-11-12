<?php

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Spatie\Permission\Models\Permission;

it('creates the first Super Admin from the console with a hidden password prompt', function () {
    $this->artisan('hrm:create-super-admin', ['--name' => 'Owner', '--email' => 'owner@example.com'])
        ->expectsQuestion('Password (not shown)', 'Install-2026x')
        ->expectsQuestion('Repeat the password', 'Install-2026x')
        ->assertSuccessful();

    $admin = User::query()->where('email', 'owner@example.com')->sole();

    expect($admin->isAdmin())->toBeTrue()
        ->and($admin->hasRole(config('permissions.super_admin_role')))->toBeTrue()
        ->and(password_verify('Install-2026x', $admin->password))->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['event' => 'admin_user.created_from_console', 'auditable_id' => $admin->id]);
});

it('refuses a weak or mismatched password and a taken e-mail', function () {
    User::factory()->admin()->create(['email' => 'taken@example.com']);

    $this->artisan('hrm:create-super-admin', ['--name' => 'Owner', '--email' => 'taken@example.com'])
        ->expectsQuestion('Password (not shown)', 'short')
        ->expectsQuestion('Repeat the password', 'other')
        ->assertFailed();

    expect(User::query()->count())->toBe(1);
});

it('seeds roles, permissions and reference data but no demo data or accounts in production', function () {
    app()['env'] = 'production';

    // db:seed asks for confirmation in production; --force is what the deployment guide uses.
    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();

    expect(Permission::query()->count())->toBeGreaterThan(100)
        ->and(Role::query()->pluck('name')->all())->toContain('Super Admin', 'Reports Viewer')
        ->and(User::query()->count())->toBe(0)
        ->and(Employee::query()->count())->toBe(0);
});
