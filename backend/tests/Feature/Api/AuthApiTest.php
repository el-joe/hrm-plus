<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

function apiLogin(array $overrides = []): array
{
    return array_merge(['email' => 'employee@example.com', 'password' => 'password', 'device_name' => 'Pixel 9'], $overrides);
}

/**
 * Forget the guards between two requests of one test, as two real HTTP
 * requests would; otherwise the first request's user is reused.
 */
function nextRequest(): void
{
    app('auth')->forgetGuards();
}

describe('login', function () {
    it('issues an expiring token to an employee and records the login', function () {
        $this->freezeSecond();
        $employee = User::factory()->employee()->create(['email' => 'employee@example.com']);

        $response = $this->postJson('/api/v1/auth/login', apiLogin())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('api.logged_in'))
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_at', now()->addMinutes(setting('security.api_token_lifetime_minutes'))->toIso8601String())
            ->assertJsonPath('data.user.id', $employee->id)
            ->assertJsonPath('data.user.account_type', 'employee')
            ->assertJsonMissingPath('data.user.password');

        $token = PersonalAccessToken::findToken($response->json('data.token'));
        expect($token->tokenable->is($employee))->toBeTrue()
            ->and($token->name)->toBe('Pixel 9')
            ->and($token->expires_at->equalTo(now()->addMinutes(setting('security.api_token_lifetime_minutes'))))->toBeTrue();

        $this->assertDatabaseHas('login_histories', ['user_id' => $employee->id, 'area' => 'api', 'successful' => true]);
    });

    it('answers a wrong password with the validation envelope and records the failure', function () {
        User::factory()->employee()->create(['email' => 'employee@example.com']);

        $this->postJson('/api/v1/auth/login', apiLogin(['password' => 'wrong-password']))
            ->assertUnprocessable()
            ->assertExactJson([
                'success' => false,
                'message' => __('api.validation_failed'),
                'code' => 'validation_error',
                'errors' => ['email' => [__('auth.failed')]],
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('login_histories', ['email' => 'employee@example.com', 'area' => 'api', 'successful' => false, 'failure_reason' => 'invalid_credentials']);
    });

    it('requires email, password and a device name', function () {
        $this->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonValidationErrors(['email', 'password', 'device_name']);
    });

    it('refuses an admin account even with the right password', function () {
        $admin = adminWithRoles('HR Manager');

        $this->postJson('/api/v1/auth/login', apiLogin(['email' => $admin->email]))
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'forbidden')
            ->assertJsonPath('message', __('auth.employee_only'));

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('login_histories', ['user_id' => $admin->id, 'area' => 'api', 'failure_reason' => 'account_type_not_allowed']);
    });

    it('refuses an employee whose account is not active', function () {
        User::factory()->employee()->suspended()->create(['email' => 'employee@example.com']);

        $this->postJson('/api/v1/auth/login', apiLogin())
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', __('auth.inactive'));

        $this->assertDatabaseCount('personal_access_tokens', 0);
    });

    it('locks the login out with a 429 after too many failed attempts', function () {
        User::factory()->employee()->create(['email' => 'employee@example.com']);

        foreach (range(1, setting('security.login_max_attempts')) as $attempt) {
            $this->postJson('/api/v1/auth/login', apiLogin(['password' => 'wrong-password']))->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', apiLogin())
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'too_many_requests')
            ->assertHeader('Retry-After');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    });
});

describe('protected endpoints', function () {
    it('returns the signed-in employee from me', function () {
        $employee = User::factory()->employee()->create();

        $this->withToken($employee->createToken('phone', ['employee'])->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $employee->id)
            ->assertJsonPath('data.employee_id', $employee->employee_id);
    });

    it('answers 401 in the envelope without a valid token', function (?string $token) {
        $request = $token === null ? $this : $this->withToken($token);

        $request->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertExactJson(['success' => false, 'message' => __('api.unauthenticated'), 'code' => 'unauthenticated']);
    })->with([
        'no token' => [null],
        'unknown token' => ['1|not-a-real-token'],
    ]);

    it('rejects a token once it has expired', function () {
        $employee = User::factory()->employee()->create();
        $token = $employee->createToken('phone', ['employee'], now()->addHour())->plainTextToken;

        $this->travel(61)->minutes();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    });

    it('refuses a token that belongs to an admin account', function () {
        $admin = adminWithRoles('Super Admin');

        $this->withToken($admin->createToken('stolen')->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
    });

    it('refuses the token of an employee deactivated after logging in', function () {
        $employee = User::factory()->employee()->create();
        $token = $employee->createToken('phone', ['employee'])->plainTextToken;
        $employee->update(['status' => 'inactive']);

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath('code', 'account_inactive');
    });
});

describe('token lifecycle', function () {
    it('revokes only the current token on logout', function () {
        $employee = User::factory()->employee()->create();
        $phone = $employee->createToken('phone', ['employee'])->plainTextToken;
        $tablet = $employee->createToken('tablet', ['employee'])->plainTextToken;

        $this->withToken($phone)->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', __('api.logged_out'));

        nextRequest();
        $this->withToken($phone)->getJson('/api/v1/auth/me')->assertUnauthorized();
        nextRequest();
        $this->withToken($tablet)->getJson('/api/v1/auth/me')->assertOk();
    });

    it('revokes every token of the account on logout-all', function () {
        $employee = User::factory()->employee()->create();
        $other = User::factory()->employee()->create();
        $phone = $employee->createToken('phone', ['employee'])->plainTextToken;
        $employee->createToken('tablet', ['employee']);
        $other->createToken('phone', ['employee']);

        $this->withToken($phone)->postJson('/api/v1/auth/logout-all')->assertOk();

        expect($employee->tokens()->count())->toBe(0)
            ->and($other->tokens()->count())->toBe(1);
    });

    it('rotates the token on refresh', function () {
        $employee = User::factory()->employee()->create();
        $old = $employee->createToken('phone', ['employee'])->plainTextToken;

        $new = $this->withToken($old)->postJson('/api/v1/auth/refresh')
            ->assertOk()
            ->json('data.token');

        expect(PersonalAccessToken::findToken($old))->toBeNull()
            ->and(PersonalAccessToken::findToken($new)->tokenable->is($employee))->toBeTrue()
            ->and(PersonalAccessToken::findToken($new)->name)->toBe('phone');
    });
});
