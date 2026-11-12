<?php

use App\Models\DeviceToken;
use App\Models\User;

require_once __DIR__.'/NotificationHelpers.php';

/*
| /api/v1/me notifications, preferences and push devices (Phase 8b) for the Flutter app.
*/

beforeEach(fn () => seedRoles());

/**
 * Authenticate the next API calls with a real employee token (the device is tied to it).
 */
function apiAs(User $user): void
{
    app('auth')->forgetGuards();
    test()->withToken($user->createToken('phone', ['employee'])->plainTextToken);
}

it('lists the token owner\'s notifications with the unread count', function () {
    $user = employeeAccount();
    $user->notify(greeting('Sara'));
    employeeAccount()->notify(greeting('Other'));
    apiAs($user);

    $this->getJson(route('api.v1.me.notifications.index'))->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.event', 'birthday_greeting')
        ->assertJsonPath('data.0.is_read', false)
        ->assertJsonStructure(['data' => [['id', 'event', 'kind', 'module', 'title', 'message', 'data', 'url', 'is_read', 'read_at', 'created_at']], 'meta' => ['pagination', 'unread_count']])
        ->assertJsonPath('meta.unread_count', 1);

    $this->getJson(route('api.v1.me.notifications.unread-count'))->assertOk()->assertJsonPath('data.count', 1);
});

it('marks one or all notifications read and 404s for others', function () {
    $user = employeeAccount();
    $user->notify(greeting());
    $user->notify(greeting());
    $other = employeeAccount();
    $other->notify(greeting());
    apiAs($user);

    $this->postJson(route('api.v1.me.notifications.read', $other->notifications()->first()->id))->assertNotFound();
    $this->postJson(route('api.v1.me.notifications.read', $user->notifications()->first()->id))->assertOk()
        ->assertJsonPath('data.is_read', true)->assertJsonPath('meta.unread_count', 1);
    $this->postJson(route('api.v1.me.notifications.read-all'))->assertOk()->assertJsonPath('data.updated', 1);
});

it('reads and updates preferences; mandatory events stay on', function () {
    $user = employeeAccount();
    apiAs($user);

    $this->getJson(route('api.v1.me.notification-preferences.show'))->assertOk()
        ->assertJsonStructure(['data' => [['module', 'label', 'events' => [['event', 'label', 'mandatory', 'channels' => ['database', 'mail', 'push']]]]], 'meta' => ['channels']]);

    $this->putJson(route('api.v1.me.notification-preferences.update'), ['preferences' => [
        'payslip_issued' => ['push' => false], 'disciplinary_issued' => ['push' => false],
    ]])->assertOk();

    expect($user->notificationPreferences()->pluck('enabled', 'event')->all())->toBe(['payslip_issued' => false]);

    $this->putJson(route('api.v1.me.notification-preferences.update'), ['preferences' => 'x'])->assertUnprocessable();
});

it('registers a device idempotently, moves a token to a new account, and unregisters it', function () {
    $user = employeeAccount();
    apiAs($user);
    $token = str_repeat('a', 30).':APA91b-token';

    $this->postJson(route('api.v1.me.devices.store'), ['token' => $token, 'platform' => 'android', 'device_name' => 'Pixel'])->assertCreated()
        ->assertJsonMissingPath('data.token');
    $this->postJson(route('api.v1.me.devices.store'), ['token' => $token, 'platform' => 'android', 'app_version' => '1.0.1'])->assertOk();

    expect(DeviceToken::query()->count())->toBe(1);

    $next = employeeAccount();
    apiAs($next);
    $this->postJson(route('api.v1.me.devices.store'), ['token' => $token, 'platform' => 'android'])->assertCreated();

    expect(DeviceToken::query()->sole()->user_id)->toBe($next->id);

    $this->getJson(route('api.v1.me.devices.index'))->assertOk()->assertJsonCount(1, 'data');
    $this->deleteJson(route('api.v1.me.devices.destroy'), ['token' => $token])->assertOk();
    $this->deleteJson(route('api.v1.me.devices.destroy'), ['token' => $token])->assertNotFound();
});

it('validates device registration', function () {
    apiAs(employeeAccount());

    $this->postJson(route('api.v1.me.devices.store'), ['token' => 'short', 'platform' => 'symbian'])->assertUnprocessable()
        ->assertJsonValidationErrors(['token', 'platform']);
});

it('removes the devices of a token on logout', function () {
    $user = employeeAccount(user: ['password' => bcrypt('secret-password-1')]);
    $login = $this->postJson(route('api.v1.auth.login'), ['email' => $user->email, 'password' => 'secret-password-1', 'device_name' => 'Pixel'])->assertOk();
    $bearer = $login->json('data.token');

    $this->withToken($bearer)->postJson(route('api.v1.me.devices.store'), ['token' => str_repeat('b', 40), 'platform' => 'android'])->assertCreated();
    $this->withToken($bearer)->postJson(route('api.v1.auth.logout'))->assertOk();

    expect(DeviceToken::query()->count())->toBe(0);
});
