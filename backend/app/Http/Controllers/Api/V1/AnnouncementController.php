<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Announcements\ManageAnnouncement;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AnnouncementResource;
use App\Models\Announcement;
use App\Models\Employee;
use App\Services\Announcements\AnnouncementFeed;
use App\Services\Announcements\AnnouncementFileStore;
use App\Services\Employees\PortalProfile;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET  /api/v1/me/announcements                         live announcements for me, pinned first (?unread=1, ?per_page=)
 *                                                       meta: pagination, unread_count, pending_acknowledgements
 * GET  /api/v1/me/announcements/{id}                    detail with body_html / body_text / attachments; marks it read
 * POST /api/v1/me/announcements/{id}/read               mark read (idempotent)
 * POST /api/v1/me/announcements/{id}/acknowledge        acknowledge (422 when the announcement needs none)
 * GET  /api/v1/me/announcements/{id}/attachments/{aid}  download an attachment
 *
 * An announcement not addressed to the token owner (or not live) is a 404.
 */
class AnnouncementController extends Controller
{
    public function __construct(private readonly PortalProfile $profile, private readonly AnnouncementFeed $feed) {}

    public function index(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $filters = $request->validate(['unread' => ['nullable', 'boolean'], 'per_page' => ['nullable', 'integer', 'between:1,50']]);
        $page = $this->feed->forEmployee($employee, (bool) ($filters['unread'] ?? false))->paginate((int) ($filters['per_page'] ?? 20));
        $response = ApiResponse::paginated($page, AnnouncementResource::class);
        $payload = $response->getData(true);
        $payload['meta']['unread_count'] = $this->feed->unreadCount($employee);
        $payload['meta']['pending_acknowledgements'] = $this->feed->pendingAcknowledgements($employee);

        return response()->json($payload);
    }

    public function show(Request $request, int $announcement, ManageAnnouncement $announcements): JsonResponse
    {
        $employee = $this->employee($request);
        $announcements->markRead($this->find($employee, $announcement), $employee);

        return ApiResponse::success((new AnnouncementResource($this->find($employee, $announcement)))->withBody());
    }

    public function read(Request $request, int $announcement, ManageAnnouncement $announcements): JsonResponse
    {
        $employee = $this->employee($request);
        $announcements->markRead($this->find($employee, $announcement), $employee);

        return ApiResponse::success(new AnnouncementResource($this->find($employee, $announcement)), meta: ['unread_count' => $this->feed->unreadCount($employee)]);
    }

    public function acknowledge(Request $request, int $announcement, ManageAnnouncement $announcements): JsonResponse
    {
        $employee = $this->employee($request);
        $announcements->acknowledge($this->find($employee, $announcement), $employee, $request->user(), 'api');

        return ApiResponse::success(new AnnouncementResource($this->find($employee, $announcement)), __('announcements.portal.acknowledged'));
    }

    public function attachment(Request $request, int $announcement, int $attachment, AnnouncementFileStore $files): StreamedResponse
    {
        $file = $this->find($this->employee($request), $announcement)->attachments->firstWhere('id', $attachment);
        abort_if($file === null, 404);

        return $files->response($file->file_path, $file->file_name, $file->file_mime);
    }

    private function find(Employee $employee, int $id): Announcement
    {
        $announcement = $this->feed->find($employee, $id);
        abort_if($announcement === null, 404);

        return $announcement;
    }

    private function employee(Request $request): Employee
    {
        $employee = $this->profile->employeeFor($request->user());
        abort_if($employee === null, 404);

        return $employee;
    }
}
