<?php

use App\Notifications\Automation\AutomationNotification;

require_once __DIR__.'/NotificationHelpers.php';

/*
| Notification centre pages (Phase 8b): the bell and the list in the admin area and the portal, open (mark
| read + follow an internal link only), mark read / all read, preferences — always the user's own rows.
*/

beforeEach(fn () => seedRoles());

it('shows the bell with the unread count and the list in the portal', function () {
    $user = employeeAccount(user: ['locale' => 'en']);
    $user->notify(greeting('Sara'));

    $this->actingAs($user, 'employee')->get(route('portal.home'))
        ->assertOk()->assertSee('data-notification-bell', false)->assertSee(trans('notifications.bell_unread', ['count' => 1], 'en'));

    $this->actingAs($user, 'employee')->get(route('portal.notifications.index'))->assertOk()->assertSee('Sara');
});

it('opens a notification: marks it read and follows its internal link', function () {
    $user = employeeAccount();
    $user->notify(new AutomationNotification('birthday_greeting', ['employee' => 'A', 'company' => 'B'], ['url' => route('portal.training')]));
    $notification = $user->notifications()->first();

    $this->actingAs($user, 'employee')->get(route('portal.notifications.open', $notification->id))->assertRedirect(route('portal.training'));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('never redirects to an external url stored in a notification', function () {
    $user = employeeAccount();
    $user->notify(new AutomationNotification('birthday_greeting', ['employee' => 'A', 'company' => 'B'], ['url' => 'https://evil.example/phish']));

    $this->actingAs($user, 'employee')->get(route('portal.notifications.open', $user->notifications()->first()->id))
        ->assertRedirect(route('portal.notifications.index'));
});

it('returns 404 for another user\'s notification', function () {
    $owner = employeeAccount();
    $owner->notify(greeting());
    $id = $owner->notifications()->first()->id;

    $this->actingAs(employeeAccount(), 'employee')->get(route('portal.notifications.open', $id))->assertNotFound();
    $this->actingAs(employeeAccount(), 'employee')->post(route('portal.notifications.read', $id))->assertNotFound();

    expect($owner->notifications()->first()->read_at)->toBeNull();
});

it('marks all own notifications read', function () {
    $user = employeeAccount();
    $other = employeeAccount();
    $user->notify(greeting());
    $user->notify(greeting());
    $other->notify(greeting());

    $this->actingAs($user, 'employee')->post(route('portal.notifications.read-all'))->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0)->and($other->unreadNotifications()->count())->toBe(1);
});

it('serves the unread count for the bell poll', function () {
    $user = employeeAccount();
    $user->notify(greeting());

    $this->actingAs($user, 'employee')->getJson(route('portal.notifications.unread-count'))->assertOk()->assertExactJson(['count' => 1]);
});

it('gives every admin their own notification centre without a permission', function () {
    $admin = adminWithPermissions('dashboard.view');
    $admin->notify(new AutomationNotification('approvals_digest', ['count' => 2, 'date' => '2026-11-01', 'summary' => 'Leave (2)'], ['url' => route('admin.dashboard')]));

    $this->actingAs($admin, 'admin')->get(route('admin.notifications.index'))->assertOk()->assertSee('Leave (2)');
    $this->actingAs($admin, 'admin')->get(route('admin.notification-preferences.edit'))->assertOk();
});

it('saves preferences from the form, ignoring mandatory events', function () {
    $user = employeeAccount();

    $this->actingAs($user, 'employee')->put(route('portal.notification-preferences.update'), ['preferences' => [
        'leave_approved' => ['database' => '1', 'mail' => '0', 'push' => '1'],
        'disciplinary_issued' => ['database' => '0'],
        'payroll_run_awaiting_approval' => ['mail' => '0'],
    ]])->assertRedirect(route('portal.notification-preferences.edit'));

    expect($user->notificationPreferences()->pluck('enabled', 'event')->all())->toBe(['leave_approved' => false]);
});

it('lets the user remove one of their own devices only', function () {
    $user = employeeAccount();
    $device = registerDevice($user);
    $foreign = registerDevice(employeeAccount());

    $this->actingAs($user, 'employee')->delete(route('portal.notification-preferences.devices.destroy', $foreign->id))->assertNotFound();
    $this->actingAs($user, 'employee')->delete(route('portal.notification-preferences.devices.destroy', $device->id))->assertRedirect();

    expect($device->fresh())->toBeNull()->and($foreign->fresh())->not->toBeNull();
});

it('shows the delivery log with the simulated label to notifications.view only', function () {
    $this->actingAs(adminWithPermissions('notifications.view'), 'admin')->get(route('admin.notification-deliveries.index'))
        ->assertOk()->assertSee(trans('devices.simulated.badge'));
    $this->actingAs(adminWithPermissions('dashboard.view'), 'admin')->get(route('admin.notification-deliveries.index'))->assertForbidden();
});
