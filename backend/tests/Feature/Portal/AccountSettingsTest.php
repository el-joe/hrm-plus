<?php

use App\Models\AuditLog;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/*
| Account settings (Phase 9 portal page, Phase 10 API): change password, where the account is signed in
| (web sessions and app tokens) and revoking one of them — always the signed-in account only.
*/

beforeEach(function () {
    seedRoles();
    $this->user = User::factory()->employee()->create(['password' => 'Old-password1']);
    $this->other = User::factory()->employee()->create();
});

function accountSession(User $user, string $agent = 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0'): string
{
    $id = Str::random(40);
    DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'ip_address' => '10.0.0.5', 'user_agent' => $agent, 'payload' => '', 'last_activity' => now()->getTimestamp()]);

    return $id;
}

describe('portal', function () {
    // Browser sessions are listed from the database session store (the production driver).
    beforeEach(fn () => config(['session.driver' => 'database']));

    it('shows the password form, preferences, browsers, app devices and recent sign-ins', function () {
        $this->user->createToken('Pixel 9');

        $this->actingAs($this->user, 'employee')->get(route('portal.account'))
            ->assertOk()
            ->assertSee('data-region="account-password"', false)
            ->assertSee('data-region="account-preferences"', false)
            ->assertSee('data-region="account-sessions"', false)
            ->assertSee('data-region="account-devices"', false)
            ->assertSee('data-region="account-logins"', false)
            ->assertSee('Pixel 9')
            ->assertSee(route('portal.notification-preferences.edit'), false);
    });

    it('changes the password and signs every other device out', function () {
        $this->user->createToken('Pixel 9');
        $elsewhere = accountSession($this->user);
        $theirs = accountSession($this->other);

        $this->actingAs($this->user, 'employee')->put(route('portal.account.password'), [
            'current_password' => 'Old-password1', 'password' => 'New-password2', 'password_confirmation' => 'New-password2',
        ])->assertRedirect(route('portal.account'))->assertSessionHas('status', __('passwords.changed'));

        $this->user->refresh();
        expect(Hash::check('New-password2', $this->user->password))->toBeTrue()
            ->and($this->user->password_changed_at)->not->toBeNull()
            ->and($this->user->tokens()->count())->toBe(0)
            ->and(DB::table('sessions')->where('id', $elsewhere)->exists())->toBeFalse()
            ->and(DB::table('sessions')->where('id', $theirs)->exists())->toBeTrue()
            ->and(AuditLog::query()->where('event', 'auth.password_changed')->where('auditable_id', $this->user->id)->exists())->toBeTrue();
    });

    it('refuses a wrong current password, a weak or unconfirmed new one', function () {
        $this->actingAs($this->user, 'employee')->put(route('portal.account.password'), [
            'current_password' => 'nope', 'password' => 'New-password2', 'password_confirmation' => 'New-password2',
        ])->assertSessionHasErrors(['current_password' => __('passwords.current_wrong')]);

        $this->actingAs($this->user, 'employee')->put(route('portal.account.password'), [
            'current_password' => 'Old-password1', 'password' => 'short', 'password_confirmation' => 'other',
        ])->assertSessionHasErrors('password');

        expect(Hash::check('Old-password1', $this->user->refresh()->password))->toBeTrue();
    });

    it('signs out one of its own browser sessions or app devices, never someone else\'s', function () {
        $mine = accountSession($this->user);
        $theirs = accountSession($this->other);
        $myToken = $this->user->createToken('Pixel 9')->accessToken;
        $theirToken = $this->other->createToken('iPhone')->accessToken;

        $this->actingAs($this->user, 'employee')->delete(route('portal.account.sessions.destroy', $theirs))->assertNotFound();
        $this->actingAs($this->user, 'employee')->delete(route('portal.account.devices.destroy', $theirToken->id))->assertNotFound();
        expect(DB::table('sessions')->where('id', $theirs)->exists())->toBeTrue()
            ->and($this->other->tokens()->count())->toBe(1);

        $this->actingAs($this->user, 'employee')->delete(route('portal.account.sessions.destroy', $mine))->assertRedirect();
        $this->actingAs($this->user, 'employee')->delete(route('portal.account.devices.destroy', $myToken->id))->assertRedirect();

        expect(DB::table('sessions')->where('id', $mine)->exists())->toBeFalse()
            ->and($this->user->tokens()->count())->toBe(0);
    });

    it('forgets the push registrations of a revoked app device', function () {
        $token = $this->user->createToken('Pixel 9')->accessToken;
        DeviceToken::query()->create([
            'user_id' => $this->user->id, 'personal_access_token_id' => $token->id, 'token' => 'fcm-1', 'token_hash' => DeviceToken::hashOf('fcm-1'),
            'platform' => 'android', 'last_seen_at' => now(),
        ]);

        $this->actingAs($this->user, 'employee')->delete(route('portal.account.devices.destroy', $token->id))->assertRedirect();

        expect(DeviceToken::query()->where('user_id', $this->user->id)->count())->toBe(0);
    });
});

describe('api', function () {
    it('changes the password, keeps the current token and revokes the others', function () {
        $current = $this->user->createToken('Pixel 9', ['employee']);
        $this->user->createToken('Old tablet', ['employee']);

        $this->withToken($current->plainTextToken)->putJson(route('api.v1.auth.password.change'), [
            'current_password' => 'Old-password1', 'password' => 'New-password2', 'password_confirmation' => 'New-password2',
        ])->assertOk()->assertJsonPath('success', true)->assertJsonPath('message', __('passwords.changed'));

        expect(Hash::check('New-password2', $this->user->refresh()->password))->toBeTrue()
            ->and($this->user->tokens()->pluck('id')->all())->toBe([$current->accessToken->id]);
    });

    it('answers a wrong current password with the validation envelope', function () {
        Sanctum::actingAs($this->user, ['employee']);

        $this->putJson(route('api.v1.auth.password.change'), [
            'current_password' => 'nope', 'password' => 'New-password2', 'password_confirmation' => 'New-password2',
        ])->assertUnprocessable()->assertJsonPath('code', 'validation_error')->assertJsonValidationErrors('current_password');
    });

    it('lists the account\'s app devices, flags the current one and revokes only its own', function () {
        $current = $this->user->createToken('Pixel 9', ['employee']);
        $old = $this->user->createToken('Old tablet', ['employee'])->accessToken;
        $theirs = $this->other->createToken('iPhone', ['employee'])->accessToken;

        $this->withToken($current->plainTextToken)->getJson(route('api.v1.auth.sessions.index'))
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $current->accessToken->id, 'name' => 'Pixel 9', 'is_current' => true])
            ->assertJsonFragment(['id' => $old->id, 'name' => 'Old tablet', 'is_current' => false]);

        app('auth')->forgetGuards();
        $this->withToken($current->plainTextToken)->deleteJson(route('api.v1.auth.sessions.destroy', $theirs->id))->assertNotFound();
        app('auth')->forgetGuards();
        $this->withToken($current->plainTextToken)->deleteJson(route('api.v1.auth.sessions.destroy', $old->id))->assertOk();

        expect($this->user->tokens()->pluck('name')->all())->toBe(['Pixel 9'])
            ->and($this->other->tokens()->count())->toBe(1);
    });

    it('saves the language preference and answers in it', function () {
        Sanctum::actingAs($this->user, ['employee']);

        $this->patchJson(route('api.v1.me.profile.update'), ['locale' => 'en'])
            ->assertOk()->assertJsonPath('data.locale', 'en')->assertJsonPath('message', __('app.account.language_saved', [], 'en'));
        expect($this->user->refresh()->locale)->toBe('en');

        $this->patchJson(route('api.v1.me.profile.update'), ['locale' => 'fr'])->assertUnprocessable()->assertJsonValidationErrors('locale');
    });
});
