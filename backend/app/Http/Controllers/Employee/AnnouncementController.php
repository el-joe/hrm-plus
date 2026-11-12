<?php

namespace App\Http\Controllers\Employee;

use App\Actions\Announcements\ManageAnnouncement;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Employee;
use App\Services\Announcements\AnnouncementFeed;
use App\Services\Announcements\AnnouncementFileStore;
use App\Services\Employees\PortalProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Portal "Announcements": live announcements addressed to the signed-in employee (pinned first),
 * opening one marks it read, acknowledgement when required, attachments. Anything else is a 404.
 */
class AnnouncementController extends Controller
{
    public function __construct(private readonly PortalProfile $profile, private readonly AnnouncementFeed $feed) {}

    public function index(Request $request): View
    {
        $employee = $this->employee($request);
        $unreadOnly = $request->query('filter') === 'unread';

        return view('employee.announcements.index', [
            'announcements' => $this->feed->forEmployee($employee, $unreadOnly)->paginate(12)->withQueryString(),
            'unreadOnly' => $unreadOnly,
            'unreadCount' => $this->feed->unreadCount($employee),
            'pendingAcknowledgements' => $this->feed->pendingAcknowledgements($employee),
        ]);
    }

    public function show(Request $request, int $announcement, ManageAnnouncement $announcements): View
    {
        $employee = $this->employee($request);
        $record = $this->find($employee, $announcement);
        $read = $announcements->markRead($record, $employee);

        return view('employee.announcements.show', ['announcement' => $record, 'read' => $read]);
    }

    public function acknowledge(Request $request, int $announcement, ManageAnnouncement $announcements): RedirectResponse
    {
        $employee = $this->employee($request);
        $announcements->acknowledge($this->find($employee, $announcement), $employee, $request->user(), 'portal');

        return back()->with('status', __('announcements.portal.acknowledged'));
    }

    public function attachment(Request $request, int $announcement, int $attachment, AnnouncementFileStore $files): StreamedResponse
    {
        $record = $this->find($this->employee($request), $announcement);
        $file = $record->attachments->firstWhere('id', $attachment);
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
