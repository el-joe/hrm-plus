<?php

use App\Models\User;
use App\Notifications\Auth\PasswordResetCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/*
| Forgot password in the mobile app (Phase 10): a 6-digit code by e-mail, checked, then used once to set a
| new password. Same answer for unknown addresses, hashed short-lived codes, attempt limit, rate limits.
*/

beforeEach(function () {
    Notification::fake();
    $this->user = User::factory()->employee()->create(['email' => 'sara@example.com', 'password' => 'Old-password1', 'locale' => 'en']);
});

/**
 * Ask for a code and return the one that was e-mailed.
 */
function requestResetCode(string $email = 'sara@example.com'): ?string
{
    test()->postJson(route('api.v1.auth.password.forgot'), ['email' => $email])->assertOk();
    $code = null;

    Notification::assertSentTo(User::query()->where('email', $email)->first(), PasswordResetCode::class, function (PasswordResetCode $notification, array $channels, object $notifiable) use (&$code): bool {
        $code = collect($notification->toMail($notifiable)->introLines)->map(fn (string $line): string => trim($line, '*'))->first(fn (string $line): bool => preg_match('/^\d{6}$/', $line) === 1);

        return $channels === ['mail'];
    });

    return $code;
}

it('e-mails a six-digit code and answers with its lifetime', function () {
    $this->postJson(route('api.v1.auth.password.forgot'), ['email' => 'sara@example.com'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.code_length', 6)
        ->assertJsonPath('data.expires_in', 600)
        ->assertJsonPath('data.resend_after', 60);

    Notification::assertSentTo($this->user, PasswordResetCode::class);
});

it('answers the same way for unknown addresses and admin accounts without sending anything', function () {
    $admin = User::factory()->admin()->create(['email' => 'boss@example.com']);
    $known = $this->postJson(route('api.v1.auth.password.forgot'), ['email' => 'sara@example.com'])->json();

    foreach (['nobody@example.com', 'boss@example.com'] as $email) {
        expect($this->postJson(route('api.v1.auth.password.forgot'), ['email' => $email])->assertOk()->json())->toBe($known);
    }

    Notification::assertNotSentTo($admin, PasswordResetCode::class);
    Notification::assertSentTimes(PasswordResetCode::class, 1);
});

it('never stores the code in clear', function () {
    $code = requestResetCode();
    $stored = cache()->get('password-reset-code:'.hash('sha256', 'sara@example.com'));

    expect($code)->toMatch('/^\d{6}$/')
        ->and(json_encode($stored))->not->toContain($code)
        ->and($stored['hash'])->toBe(hash_hmac('sha256', $code, config('app.key')));
});

it('verifies a code without consuming it and rejects a wrong one', function () {
    $code = requestResetCode();
    $wrong = $code === '000000' ? '111111' : '000000';

    $this->postJson(route('api.v1.auth.password.verify-otp'), ['email' => 'sara@example.com', 'code' => $wrong])
        ->assertUnprocessable()->assertJsonPath('code', 'validation_error')->assertJsonPath('errors.code.0', __('passwords.code.invalid'));

    $this->postJson(route('api.v1.auth.password.verify-otp'), ['email' => 'sara@example.com', 'code' => $code])
        ->assertOk()->assertJsonPath('data.valid', true);
    // Still usable afterwards.
    $this->postJson(route('api.v1.auth.password.verify-otp'), ['email' => 'sara@example.com', 'code' => $code])->assertOk();
});

it('resets the password once with a valid code and revokes every token', function () {
    $this->user->createToken('Pixel 9', ['employee']);
    $code = requestResetCode();

    $this->postJson(route('api.v1.auth.password.reset'), [
        'email' => 'sara@example.com', 'code' => $code, 'password' => 'New-password2', 'password_confirmation' => 'New-password2',
    ])->assertOk()->assertJsonPath('message', __('passwords.reset'));

    expect(Hash::check('New-password2', $this->user->refresh()->password))->toBeTrue()
        ->and($this->user->tokens()->count())->toBe(0);

    // The code is gone.
    $this->postJson(route('api.v1.auth.password.reset'), [
        'email' => 'sara@example.com', 'code' => $code, 'password' => 'Other-password3', 'password_confirmation' => 'Other-password3',
    ])->assertUnprocessable()->assertJsonValidationErrors('code');
    expect(Hash::check('New-password2', $this->user->refresh()->password))->toBeTrue();
});

it('expires a code after the configured minutes', function () {
    $code = requestResetCode();

    $this->travel(11)->minutes();

    $this->postJson(route('api.v1.auth.password.reset'), [
        'email' => 'sara@example.com', 'code' => $code, 'password' => 'New-password2', 'password_confirmation' => 'New-password2',
    ])->assertUnprocessable()->assertJsonValidationErrors('code');
    expect(Hash::check('Old-password1', $this->user->refresh()->password))->toBeTrue();
});

it('burns the code after too many wrong attempts', function () {
    $code = requestResetCode();
    $wrong = $code === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $attempt) {
        $this->postJson(route('api.v1.auth.password.verify-otp'), ['email' => 'sara@example.com', 'code' => $wrong])->assertUnprocessable();
    }

    $this->postJson(route('api.v1.auth.password.verify-otp'), ['email' => 'sara@example.com', 'code' => $code])->assertUnprocessable();
});

it('rate limits code requests per address', function () {
    foreach (range(1, 3) as $attempt) {
        $this->postJson(route('api.v1.auth.password.forgot'), ['email' => 'sara@example.com'])->assertOk();
    }

    $this->postJson(route('api.v1.auth.password.forgot'), ['email' => 'sara@example.com'])
        ->assertTooManyRequests()->assertJsonPath('code', 'too_many_requests');

    // A new code is only sent once the resend delay has passed.
    Notification::assertSentTimes(PasswordResetCode::class, 1);
});

it('validates the payloads', function () {
    $this->postJson(route('api.v1.auth.password.forgot'), ['email' => 'not-an-email'])->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->postJson(route('api.v1.auth.password.verify-otp'), ['email' => 'sara@example.com', 'code' => '12ab'])->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->postJson(route('api.v1.auth.password.reset'), ['email' => 'sara@example.com', 'code' => '123456', 'password' => 'x'])
        ->assertUnprocessable()->assertJsonValidationErrors('password');
});
