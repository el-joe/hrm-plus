<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Requests\Notifications\UpdateNotificationPreferencesRequest;
use App\Models\DeviceToken;
use App\Services\Notifications\DeviceTokens;
use App\Services\Notifications\NotificationEventRegistry;
use App\Services\Notifications\NotificationInbox;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The notification centre pages shared by the admin area and the portal: inbox, open (mark read and
 * follow the link), mark read / all read, unread count (bell polling), preferences and own devices.
 * Always the signed-in user's own notifications; another user's id is a 404. No permission needed.
 */
trait NotificationCentreActions
{
    /**
     * "admin" or "portal": route name prefix and layout.
     */
    abstract protected function area(): string;

    public function index(Request $request, NotificationInbox $inbox): View
    {
        $filters = $request->validate(['filter' => ['nullable', 'in:unread']]);
        $unreadOnly = ($filters['filter'] ?? null) === 'unread';
        $notifications = $inbox->query($request->user(), $unreadOnly)->paginate(20)->withQueryString();

        return view('notifications.index', [
            'area' => $this->area(),
            'notifications' => $notifications->through(fn ($notification): array => $inbox->present($notification)),
            'unreadOnly' => $unreadOnly,
            'unreadCount' => $inbox->unreadCount($request->user()),
        ]);
    }

    public function open(Request $request, string $notification, NotificationInbox $inbox): RedirectResponse
    {
        $record = $inbox->find($request->user(), $notification);
        abort_if($record === null, 404);

        $inbox->markRead($record);
        $url = $inbox->present($record)['url'];

        return $url !== null ? redirect()->to($url) : redirect()->route($this->area().'.notifications.index');
    }

    public function read(Request $request, string $notification, NotificationInbox $inbox): RedirectResponse
    {
        $record = $inbox->find($request->user(), $notification);
        abort_if($record === null, 404);

        $inbox->markRead($record);

        return back()->with('status', __('notifications.marked_read'));
    }

    public function readAll(Request $request, NotificationInbox $inbox): RedirectResponse
    {
        $inbox->markAllRead($request->user());

        return back()->with('status', __('notifications.all_marked_read'));
    }

    public function unreadCount(Request $request, NotificationInbox $inbox): JsonResponse
    {
        return response()->json(['count' => $inbox->unreadCount($request->user())]);
    }

    public function editPreferences(Request $request, NotificationPreferences $preferences, NotificationEventRegistry $events): View
    {
        $user = $request->user();

        return view('notifications.preferences', [
            'area' => $this->area(),
            'matrix' => $preferences->matrix($user),
            'channels' => $events->channels(),
            'mailEnabled' => (bool) setting('notifications.email_enabled'),
            'pushEnabled' => (bool) setting('notifications.push_enabled'),
            'devices' => DeviceToken::query()->where('user_id', $user->id)->latest('last_seen_at')->get(),
            'user' => $user,
        ]);
    }

    public function updatePreferences(UpdateNotificationPreferencesRequest $request, NotificationPreferences $preferences): RedirectResponse
    {
        $preferences->save($request->user(), $request->choices());

        return redirect()->route($this->area().'.notification-preferences.edit')->with('status', __('notifications.preferences.saved'));
    }

    public function destroyDevice(Request $request, int $device, DeviceTokens $devices): RedirectResponse
    {
        $record = DeviceToken::query()->where('user_id', $request->user()->id)->find($device);
        abort_if($record === null, 404);

        $devices->remove($record, $request->user());

        return back()->with('status', __('notifications.preferences.device_removed'));
    }
}
