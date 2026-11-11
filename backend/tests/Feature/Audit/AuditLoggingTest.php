<?php

use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;

it('logs the creation of an auditable model with its new values', function () {
    $setting = Setting::create(['key' => 'company.name', 'group' => 'company', 'value' => 'Acme']);

    $log = AuditLog::query()->where('event', 'created')->sole();

    expect($log->auditable_type)->toBe($setting->getMorphClass())
        ->and($log->auditable_id)->toBe($setting->id)
        ->and($log->old_values)->toBeNull()
        ->and($log->new_values)->toMatchArray(['key' => 'company.name', 'value' => 'Acme']);
});

it('logs an update with only the changed attributes, old and new', function () {
    $user = User::factory()->admin()->create(['name' => 'Old Name']);

    $user->update(['name' => 'New Name', 'status' => UserStatus::Suspended]);

    $log = AuditLog::query()->where('event', 'updated')->sole();

    expect($log->old_values)->toBe(['name' => 'Old Name', 'status' => 'active'])
        ->and($log->new_values)->toBe(['name' => 'New Name', 'status' => 'suspended']);
});

it('logs a deletion with the values the record had', function () {
    $setting = Setting::create(['key' => 'company.name', 'group' => 'company', 'value' => 'Acme']);

    $setting->delete();

    expect(AuditLog::query()->where('event', 'deleted')->sole()->old_values)
        ->toMatchArray(['key' => 'company.name', 'value' => 'Acme']);
});

it('records who acted, from where and on which URL', function () {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'admin');

    $log = app(AuditLogger::class)->log('payroll.exported', meta: ['format' => 'xlsx']);

    expect($log->user_id)->toBe($actor->id)
        ->and($log->ip_address)->toBe('127.0.0.1')
        ->and($log->meta)->toBe(['format' => 'xlsx'])
        ->and($log->created_at)->not->toBeNull();
});

it('never stores a password, neither plain nor hashed, when a user is created or updated', function () {
    $user = User::factory()->admin()->create(['password' => 'first-secret-1']);
    $user->update(['password' => 'second-secret-2']);

    $logged = AuditLog::query()->get()->toJson();

    expect($logged)->not->toContain('first-secret-1')
        ->and($logged)->not->toContain('second-secret-2')
        ->and($logged)->not->toContain($user->password)
        ->and($logged)->not->toContain($user->getOriginal('remember_token') ?? 'no-remember-token');

    expect(AuditLog::query()->where('event', 'updated')->sole()->new_values)->toBe(['password' => '[REDACTED]']);
});

it('redacts secret-looking keys at any depth of a custom event', function () {
    $log = app(AuditLogger::class)->log(
        'integration.configured',
        oldValues: ['api_key' => 'old-key-value'],
        newValues: ['api_key' => 'new-key-value', 'name' => 'Device A'],
        meta: ['request' => ['Authorization' => 'Bearer abc123', 'device_token' => 'tok-999', 'client_secret' => 's3cr3t'], 'attempt' => 2],
    );

    $stored = $log->fresh()->toJson();

    expect($stored)->not->toContain('old-key-value')
        ->and($stored)->not->toContain('new-key-value')
        ->and($stored)->not->toContain('abc123')
        ->and($stored)->not->toContain('tok-999')
        ->and($stored)->not->toContain('s3cr3t');

    expect($log->new_values)->toBe(['api_key' => '[REDACTED]', 'name' => 'Device A'])
        ->and($log->meta['attempt'])->toBe(2);
});

it('writes no audit entry when only excluded attributes change', function () {
    $user = User::factory()->admin()->create();
    $before = AuditLog::query()->count();

    $user->forceFill(['last_login_at' => now(), 'last_login_ip' => '10.1.1.1'])->save();

    expect(AuditLog::query()->count())->toBe($before);
});

it('does not allow an audit entry to be changed or deleted', function (Closure $tamper) {
    $log = AuditLog::factory()->create();

    expect(fn () => $tamper($log))->toThrow(LogicException::class);

    $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'event' => 'test.event']);
})->with([
    'update' => [fn (AuditLog $log) => $log->update(['event' => 'tampered'])],
    'delete' => [fn (AuditLog $log) => $log->delete()],
]);

it('audits a change to a secret-named setting without storing its value', function () {
    config(['settings.notifications.smtp_password' => ['type' => 'string', 'default' => null, 'rules' => ['nullable', 'string']]]);

    app(SettingsService::class)->set('notifications.smtp_password', 'mail-secret-1');
    app(SettingsService::class)->set('notifications.smtp_password', 'mail-secret-2');

    $logs = AuditLog::query()->where('auditable_type', 'setting')->orderBy('id')->get();

    expect($logs->pluck('event')->all())->toBe(['created', 'updated'])
        ->and($logs[0]->new_values['value'])->toBe(config('audit.redacted_placeholder'))
        ->and($logs[1]->old_values['value'])->toBe(config('audit.redacted_placeholder'))
        ->and($logs[1]->new_values['value'])->toBe(config('audit.redacted_placeholder'))
        ->and(AuditLog::query()->where('new_values', 'like', '%mail-secret%')->orWhere('old_values', 'like', '%mail-secret%')->exists())->toBeFalse();
});
