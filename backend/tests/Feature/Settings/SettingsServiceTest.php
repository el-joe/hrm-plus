<?php

use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\DB;

it('returns the configured default when nothing is stored', function () {
    expect(setting('attendance.late_grace_minutes'))->toBe(10)
        ->and(setting('leave.allow_half_day'))->toBeTrue()
        ->and(setting('payroll.overtime_rate_multiplier'))->toBe('1.50')
        ->and(setting('employee_number.prefix'))->toBe('EMP-');
});

it('stores a value and reads it back in its declared type', function (string $key, mixed $written, mixed $read) {
    $settings = app(SettingsService::class);

    $settings->set($key, $written);

    expect($settings->get($key))->toBe($read);
})->with([
    'integer from form string' => ['attendance.late_grace_minutes', '25', 25],
    'boolean false' => ['leave.allow_half_day', false, false],
    'boolean from form string' => ['leave.allow_negative_balance', '1', true],
    'decimal stays a string' => ['payroll.overtime_rate_multiplier', '2.25', '2.25'],
    'list' => ['attendance.working_days', ['monday', 'tuesday'], ['monday', 'tuesday']],
    'string' => ['employee_number.prefix', 'موظف-', 'موظف-'],
]);

it('returns a whole group as name => value', function () {
    app(SettingsService::class)->set('employee_number.prefix', 'HR-');

    expect(app(SettingsService::class)->group('employee_number'))
        ->toBe(['pattern' => '{prefix}{number}', 'prefix' => 'HR-', 'padding' => 5, 'next_sequence' => 1]);
});

it('fails loudly for a key that is not declared', function () {
    setting('attendance.does_not_exist');
})->throws(InvalidArgumentException::class);

it('serves repeated reads from the cache and refreshes it after a write', function () {
    $settings = app(SettingsService::class);
    $settings->set('attendance.late_grace_minutes', 15);
    $settings->get('attendance.late_grace_minutes');

    DB::enableQueryLog();
    $settings->get('attendance.late_grace_minutes');
    $settings->get('payroll.period_start_day');
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(0);

    $settings->set('attendance.late_grace_minutes', 20);

    expect($settings->get('attendance.late_grace_minutes'))->toBe(20);
});

it('audits a changed setting with its old and new value', function () {
    $settings = app(SettingsService::class);
    $settings->set('attendance.late_grace_minutes', 15);
    $settings->set('attendance.late_grace_minutes', 20);

    $setting = Setting::query()->where('key', 'attendance.late_grace_minutes')->sole();
    $log = AuditLog::query()->where('event', 'updated')->where('auditable_id', $setting->id)->sole();

    expect($log->old_values)->toBe(['value' => '15'])
        ->and($log->new_values)->toBe(['value' => '20']);
});
