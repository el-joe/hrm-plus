<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\UpdateNotificationPreferencesRequest;
use App\Services\Notifications\NotificationEventRegistry;
use App\Services\Notifications\NotificationPreferences;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/me/notification-preferences   events the user can receive, grouped by module, with each channel's state
 * PUT /api/v1/me/notification-preferences   {"preferences": {"leave_approved": {"mail": false, "push": true}}}
 *
 * Mandatory events are listed with "mandatory": true and cannot be changed (ignored on update).
 */
class NotificationPreferenceController extends Controller
{
    public function __construct(private readonly NotificationPreferences $preferences, private readonly NotificationEventRegistry $events) {}

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success($this->payload($request), meta: $this->meta());
    }

    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $this->preferences->save($request->user(), $request->choices());

        return ApiResponse::success($this->payload($request), __('notifications.api.preferences_saved'), meta: $this->meta());
    }

    /**
     * @return list<array{module: string, label: string, events: list<array{event: string, label: string, mandatory: bool, channels: array<string, bool>}>}>
     */
    private function payload(Request $request): array
    {
        $groups = [];

        foreach ($this->preferences->matrix($request->user()) as $module => $events) {
            $groups[] = [
                'module' => $module,
                'label' => __('notifications.modules.'.$module),
                'events' => collect($events)->map(fn (array $row, string $event): array => [
                    'event' => $event, 'label' => $row['label'], 'mandatory' => $row['mandatory'], 'channels' => $row['channels'],
                ])->values()->all(),
            ];
        }

        return $groups;
    }

    /**
     * @return array{channels: list<array{key: string, label: string, available: bool}>}
     */
    private function meta(): array
    {
        return ['channels' => array_map(fn (string $channel): array => [
            'key' => $channel,
            'label' => __('notifications.channels.'.$channel),
            'available' => match ($channel) {
                'mail' => (bool) setting('notifications.email_enabled'),
                'push' => (bool) setting('notifications.push_enabled'),
                default => true,
            },
        ], $this->events->channels())];
    }
}
