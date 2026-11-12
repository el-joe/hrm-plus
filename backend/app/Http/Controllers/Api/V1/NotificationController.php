<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\NotificationResource;
use App\Services\Notifications\NotificationInbox;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET  /api/v1/me/notifications                 paginated, newest first (?unread=1, ?per_page ≤ 50); meta.unread_count
 * GET  /api/v1/me/notifications/unread-count    {count}
 * POST /api/v1/me/notifications/{id}/read       mark one read (another user's id is a 404)
 * POST /api/v1/me/notifications/read-all        mark every unread one read; data.updated
 */
class NotificationController extends Controller
{
    public function __construct(private readonly NotificationInbox $inbox) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['unread' => ['nullable', 'boolean'], 'per_page' => ['nullable', 'integer', 'between:1,50']]);
        $page = $this->inbox->query($request->user(), (bool) ($filters['unread'] ?? false))->paginate((int) ($filters['per_page'] ?? 20));
        $response = ApiResponse::paginated($page, NotificationResource::class);
        $payload = $response->getData(true);
        $payload['meta']['unread_count'] = $this->inbox->unreadCount($request->user());

        return response()->json($payload);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success(['count' => $this->inbox->unreadCount($request->user())]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $record = $this->inbox->find($request->user(), $notification);
        abort_if($record === null, 404);

        $this->inbox->markRead($record);

        return ApiResponse::success(new NotificationResource($record->refresh()), __('notifications.api.marked_read'), meta: [
            'unread_count' => $this->inbox->unreadCount($request->user()),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $updated = $this->inbox->markAllRead($request->user());

        return ApiResponse::success(['updated' => $updated], __('notifications.api.all_marked_read'), meta: ['unread_count' => 0]);
    }
}
