<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('emails an admin a reset link that points to the admin reset form', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);

    $this->post('/admin/forgot-password', ['email' => 'admin@example.com'])
        ->assertSessionHas('status', __('passwords.sent'));

    Notification::assertSentTo($admin, ResetPassword::class, function (ResetPassword $notification) use ($admin) {
        return str_contains($notification->toMail($admin)->actionUrl, '/admin/reset-password/');
    });
});

it('emails an employee a reset link that points to the portal reset form', function () {
    Notification::fake();
    $employee = User::factory()->employee()->create(['email' => 'employee@example.com']);

    $this->post('/portal/forgot-password', ['email' => 'employee@example.com'])
        ->assertSessionHas('status', __('passwords.sent'));

    Notification::assertSentTo($employee, ResetPassword::class, function (ResetPassword $notification) use ($employee) {
        return str_contains($notification->toMail($employee)->actionUrl, '/portal/reset-password/');
    });
});

it('answers the same way but sends nothing when the email is not an account of that area', function (string $email) {
    Notification::fake();
    User::factory()->employee()->create(['email' => 'employee@example.com']);

    $this->post('/admin/forgot-password', ['email' => $email])
        ->assertSessionHas('status', __('passwords.sent'));

    Notification::assertNothingSent();
})->with(['employee@example.com', 'nobody@example.com']);

it('resets the password with a valid token, revokes API tokens and keeps the secret out of the audit log', function () {
    $employee = User::factory()->employee()->create(['email' => 'employee@example.com']);
    $employee->createToken('phone');
    $token = Password::createToken($employee);

    $this->post('/portal/reset-password', [
        'token' => $token,
        'email' => 'employee@example.com',
        'password' => 'new-secret-123',
        'password_confirmation' => 'new-secret-123',
    ])->assertRedirect(route('portal.login'));

    $employee->refresh();

    expect(Hash::check('new-secret-123', $employee->password))->toBeTrue()
        ->and($employee->password_changed_at)->not->toBeNull()
        ->and($employee->tokens()->count())->toBe(0);

    $this->assertDatabaseHas('audit_logs', ['event' => 'auth.password_reset', 'auditable_id' => $employee->id]);

    $logged = AuditLog::query()->get()->toJson();

    expect($logged)->not->toContain('new-secret-123')
        ->and($logged)->not->toContain($employee->password)
        ->and($logged)->not->toContain($token);
});

it('rejects an invalid reset token', function () {
    $employee = User::factory()->employee()->create(['email' => 'employee@example.com']);

    $this->post('/portal/reset-password', [
        'token' => 'not-a-real-token',
        'email' => 'employee@example.com',
        'password' => 'new-secret-123',
        'password_confirmation' => 'new-secret-123',
    ])->assertSessionHasErrors(['email' => __('passwords.token')]);

    expect(Hash::check('password', $employee->fresh()->password))->toBeTrue();
});

it('does not let an employee reset through the admin form even with a valid token', function () {
    $employee = User::factory()->employee()->create(['email' => 'employee@example.com']);

    $this->post('/admin/reset-password', [
        'token' => Password::createToken($employee),
        'email' => 'employee@example.com',
        'password' => 'new-secret-123',
        'password_confirmation' => 'new-secret-123',
    ])->assertSessionHasErrors(['email' => __('passwords.token')]);

    expect(Hash::check('password', $employee->fresh()->password))->toBeTrue();
});

it('rejects a new password that is too weak', function () {
    $employee = User::factory()->employee()->create(['email' => 'employee@example.com']);

    $this->post('/portal/reset-password', [
        'token' => Password::createToken($employee),
        'email' => 'employee@example.com',
        'password' => 'short1',
        'password_confirmation' => 'short1',
    ])->assertSessionHasErrors('password');
});
