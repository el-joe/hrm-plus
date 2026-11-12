<?php

use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Password;

it('shows a settings group with its current values', function () {
    $response = $this->actingAs(adminWithPermissions('settings.view'), 'admin')
        ->get('/admin/settings/attendance')
        ->assertOk();

    expect($response->viewData('values')['late_grace_minutes'])->toBe(10);
});

it('redirects the settings home to the first group', function () {
    $this->actingAs(adminWithPermissions('settings.view'), 'admin')
        ->get('/admin/settings')
        ->assertRedirect(route('admin.settings.edit', 'localisation'));
});

it('returns 404 for an unknown settings group', function () {
    $this->actingAs(adminWithPermissions('settings.view'), 'admin')
        ->get('/admin/settings/nonsense')
        ->assertNotFound();
});

it('returns 404 when saving an unknown settings group', function () {
    $this->actingAs(adminWithPermissions('settings.manage'), 'admin')
        ->put('/admin/settings/nonsense', ['anything' => 1])
        ->assertNotFound();
});

it('returns 403 when an admin who may only view settings tries to save them', function () {
    $this->actingAs(adminWithPermissions('settings.view'), 'admin')
        ->put('/admin/settings/leave', ['year_start_month' => 4])
        ->assertForbidden();

    expect(setting('leave.year_start_month'))->toBe(1);
});

it('saves a group, treating unticked checkboxes as false', function () {
    $this->actingAs(adminWithPermissions('settings.view', 'settings.manage'), 'admin')
        ->put('/admin/settings/leave', ['year_start_month' => '4', 'allow_negative_balance' => '1', 'max_past_days' => '30', 'calendar_conflict_threshold' => '2'])
        ->assertRedirect(route('admin.settings.edit', 'leave'))
        ->assertSessionHas('status', __('app.settings.saved'));

    expect(setting('leave.year_start_month'))->toBe(4)
        ->and(setting('leave.allow_negative_balance'))->toBeTrue()
        ->and(setting('leave.allow_half_day'))->toBeFalse();
});

it('saves list settings and rejects values outside the allowed options', function () {
    $admin = adminWithPermissions('settings.view', 'settings.manage');
    $valid = [
        'late_grace_minutes' => 5, 'early_leave_grace_minutes' => 5, 'overtime_minimum_minutes' => 30,
        'geofence_default_radius_meters' => 200, 'gps_max_accuracy_meters' => 40, 'face_verification_max_retries' => 3,
        'gps_accuracy_tolerance_meters' => 20, 'gps_max_time_drift_seconds' => 300, 'gps_repeated_attempts_threshold' => 3, 'device_stale_after_hours' => 24,
    ];

    $this->actingAs($admin, 'admin')
        ->put('/admin/settings/attendance', $valid + ['working_days' => ['saturday', 'sunday']])
        ->assertSessionHasNoErrors();

    expect(setting('attendance.working_days'))->toBe(['saturday', 'sunday']);

    $this->actingAs($admin, 'admin')
        ->put('/admin/settings/attendance', $valid + ['working_days' => ['funday']])
        ->assertSessionHasErrors('working_days.0');
});

it('rejects an out-of-range value with a message naming the setting', function () {
    $this->actingAs(adminWithPermissions('settings.view', 'settings.manage'), 'admin')
        ->put('/admin/settings/leave', ['year_start_month' => 13])
        ->assertSessionHasErrors(['year_start_month' => __('validation.between.numeric', [
            'attribute' => __('settings.keys.leave.year_start_month'), 'min' => 1, 'max' => 12,
        ])]);

    expect(setting('leave.year_start_month'))->toBe(1);
});

it('applies the configured minimum password length to new passwords', function () {
    app(SettingsService::class)->set('security.password_min_length', 12);
    $employee = User::factory()->employee()->create(['email' => 'employee@example.com']);

    $this->post('/portal/reset-password', [
        'token' => Password::createToken($employee),
        'email' => 'employee@example.com',
        'password' => 'tenchars12',
        'password_confirmation' => 'tenchars12',
    ])->assertSessionHasErrors('password');
});
