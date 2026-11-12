<?php

use App\Enums\NotificationDeliveryStatus;
use App\Models\DeviceToken;
use App\Models\NotificationDelivery;
use App\Notifications\Automation\AutomationNotification;
use App\Notifications\Channels\PushNotificationChannel;
use App\Services\Notifications\NotificationPreferences;
use App\Services\Notifications\Push\FcmHttpV1PushChannel;
use App\Services\Notifications\Push\PushMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/NotificationHelpers.php';

/*
| Channel routing (Phase 8b): database / mail / push chosen per recipient from the channel settings,
| preferences (mandatory events always on) and registered devices; queued with retries; e-mail and
| push sends land in the delivery log; the simulated push provider is labelled as such.
*/

beforeEach(function () {
    seedRoles();
    setSetting('notifications.email_enabled', true);
    setSetting('notifications.push_enabled', true);
});

it('uses in-app, e-mail and push by default when configured', function () {
    $user = employeeAccount();
    registerDevice($user);

    expect(greeting()->via($user))->toBe(['database', 'mail', PushNotificationChannel::class]);
});

it('skips push without a registered device and e-mail when switched off system-wide', function () {
    $user = employeeAccount();
    setSetting('notifications.email_enabled', false);

    expect(greeting()->via($user))->toBe(['database']);
});

it('respects the user preferences per event and channel', function () {
    $user = employeeAccount();
    registerDevice($user);
    app(NotificationPreferences::class)->save($user, ['birthday_greeting' => ['mail' => false, 'push' => false]]);

    expect(greeting()->via($user))->toBe(['database']);
});

it('ignores preferences of mandatory events', function () {
    $user = employeeAccount();
    $preferences = app(NotificationPreferences::class);
    $preferences->save($user, ['disciplinary_issued' => ['database' => false, 'mail' => false]]);

    expect($preferences->enabled($user, 'disciplinary_issued', 'database'))->toBeTrue()
        ->and($preferences->enabled($user, 'disciplinary_issued', 'mail'))->toBeTrue()
        ->and($user->notificationPreferences()->count())->toBe(0);
});

it('keeps channels that are off by default off until the user opts in', function () {
    $user = employeeAccount();
    $preferences = app(NotificationPreferences::class);

    expect($preferences->enabled($user, 'attendance_late_arrival', 'mail'))->toBeFalse();

    $preferences->save($user, ['attendance_late_arrival' => ['mail' => true]]);

    expect($preferences->enabled($user, 'attendance_late_arrival', 'mail'))->toBeTrue();
});

it('only offers a user the events of their audience', function () {
    $employee = employeeAccount();
    $matrix = app(NotificationPreferences::class)->matrix($employee);
    $events = collect($matrix)->flatMap(fn (array $rows): array => array_keys($rows))->all();

    expect($events)->toContain('leave_approved', 'payslip_issued')
        ->not->toContain('leave_awaiting_approval', 'payroll_run_awaiting_approval');
});

it('stores the in-app notification in the shape the modules already used', function () {
    $user = employeeAccount(user: ['locale' => 'en']);
    $user->notify(greeting('Sara'));

    $data = $user->notifications()->first()->data;

    expect($data)->toHaveKeys(['kind', 'event', 'title', 'message', 'url'])
        ->and($data['event'])->toBe('birthday_greeting')
        ->and($data['title'])->toContain('Sara');
});

it('renders the e-mail from the mail template with the action button', function () {
    $user = employeeAccount(user: ['locale' => 'en']);
    $mail = greeting('Sara')->toMail($user);

    expect($mail->subject)->toContain('Sara')
        ->and($mail->actionUrl)->toBe('/portal');
});

it('queues notifications with retries', function () {
    expect(greeting()->tries)->toBe(3)
        ->and(greeting()->backoff())->toBe([60, 300]);
});

it('records simulated pushes and e-mails in the delivery log', function () {
    config(['queue.default' => 'sync', 'mail.default' => 'array']);
    $user = employeeAccount();
    registerDevice($user);

    $user->notify(greeting());

    $push = NotificationDelivery::query()->where('channel', 'push')->sole();
    $mail = NotificationDelivery::query()->where('channel', 'mail')->sole();

    expect($push->status)->toBe(NotificationDeliveryStatus::Simulated)
        ->and($push->provider)->toBe('simulated')
        ->and($push->event)->toBe('birthday_greeting')
        ->and($mail->status)->toBe(NotificationDeliveryStatus::Sent)
        ->and($mail->recipient)->toBe($user->email)
        ->and($mail->attempts)->toBe(1);
});

it('sends the push data payload the mobile app routes on', function () {
    $user = employeeAccount();
    $notification = greeting();
    $notification->id = 'f3b0c6a2-1111-4a1a-9c1e-000000000001';

    $push = $notification->toPush($user);

    expect($push)->toBeInstanceOf(PushMessage::class)
        ->and($push->data)->toMatchArray(['event' => 'birthday_greeting', 'kind' => 'birthday_greeting', 'notification_id' => $notification->id, 'url' => '/portal'])
        ->and(collect($push->data)->every(fn ($value): bool => is_string($value)))->toBeTrue();
});

it('sends through FCM HTTP v1 with a signed service-account JWT and drops unregistered tokens', function () {
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);
    $credentials = FcmHttpV1PushChannel::parseCredentials(json_encode([
        'type' => 'service_account', 'project_id' => 'demo-project', 'client_email' => 'push@demo-project.iam.gserviceaccount.com',
        'private_key' => $pem, 'token_uri' => 'https://oauth2.googleapis.com/token',
    ]));
    $user = employeeAccount();
    $good = registerDevice($user);
    $gone = registerDevice($user, 'ios');

    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
        'fcm.googleapis.com/*' => Http::sequence()
            ->push(['name' => 'projects/demo-project/messages/1'])
            ->push(['error' => ['status' => 'UNREGISTERED', 'message' => 'Requested entity was not found.']], 404),
    ]);

    $result = (new FcmHttpV1PushChannel($credentials))->send(DeviceToken::query()->whereKey([$good->id, $gone->id])->orderBy('id')->get(), new PushMessage('T', 'B', ['event' => 'x']));

    expect($result->sent)->toBe(1)->and($result->invalidDeviceIds)->toBe([$gone->id])->and($result->simulated)->toBeFalse();
    Http::assertSent(fn ($request) => str_contains($request->url(), 'oauth2.googleapis.com')
        && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer' && substr_count($request['assertion'], '.') === 2);
    Http::assertSent(fn ($request) => $request->url() === 'https://fcm.googleapis.com/v1/projects/demo-project/messages:send'
        && $request->hasHeader('Authorization', 'Bearer ya29.test') && $request['message']['token'] === $good->token);
});

it('refuses a file that is not a service-account key', function () {
    expect(fn () => FcmHttpV1PushChannel::parseCredentials('{"type":"authorized_user"}'))->toThrow(RuntimeException::class);
});

it('fakes cleanly with Notification::fake for module tests', function () {
    Notification::fake();
    $user = employeeAccount();
    $user->notify(greeting());

    Notification::assertSentTo($user, AutomationNotification::class);
});
