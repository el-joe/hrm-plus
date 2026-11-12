<?php

namespace App\Http\Controllers\Admin\Announcements;

use App\Actions\Announcements\ManageAnnouncement;
use App\Enums\AnnouncementStatus;
use App\Enums\AnnouncementTargetType;
use App\Enums\AnnouncementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Announcements\AnnouncementRequest;
use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use App\Models\AnnouncementTarget;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeGroup;
use App\Models\EmploymentType;
use App\Models\User;
use App\Services\Announcements\AnnouncementFileStore;
use App\Services\Announcements\AnnouncementReadStats;
use App\Services\Authorization\DataScopeManager;
use App\Services\Employees\Spreadsheet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Announcements (admin): list, compose (draft / publish / schedule), preview with read and
 * acknowledgement statistics, publish, archive, pin, delete drafts, attachments and the read report.
 * Rules live in App\Actions\Announcements\ManageAnnouncement and AnnouncementPolicy.
 */
class AnnouncementController extends Controller
{
    public const PER_PAGE = 20;

    public function index(Request $request, AnnouncementReadStats $stats): View
    {
        Gate::authorize('viewAny', Announcement::class);

        $user = $request->user();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(AnnouncementStatus::class)],
            'type' => ['nullable', Rule::enum(AnnouncementType::class)],
        ]);
        $base = fn (): Builder => Announcement::query()->visibleToAdmin($user);

        $announcements = $base()
            ->with(['author', 'targets'])
            ->withCount('reads')
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query
                ->whereLike('title_ar', "%{$term}%")->orWhereLike('title_en', "%{$term}%")))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->inState(AnnouncementStatus::from($status)))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->orderByRaw("CASE WHEN status = 'archived' THEN 1 ELSE 0 END")
            ->feedOrder()
            ->paginate(self::PER_PAGE)->withQueryString();

        return view('admin.announcements.index', [
            'announcements' => $announcements,
            'summaries' => $announcements->getCollection()->mapWithKeys(fn (Announcement $announcement): array => [
                $announcement->id => $announcement->isDraft() ? null : $stats->summary($announcement),
            ]),
            'filters' => $filters,
            'statuses' => AnnouncementStatus::cases(),
            'types' => AnnouncementType::cases(),
            'counts' => [
                'published' => $base()->inState(AnnouncementStatus::Published)->count(),
                'scheduled' => $base()->inState(AnnouncementStatus::Scheduled)->count(),
                'drafts' => $base()->inState(AnnouncementStatus::Draft)->count(),
                'pinned' => $base()->live()->where('is_pinned', true)->count(),
            ],
            'targetLabels' => $this->targetLabels($announcements->getCollection()->flatMap->targets),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Announcement::class);

        return view('admin.announcements.form', [
            'announcement' => new Announcement([
                'notify_email' => (bool) setting('announcements.email_by_default'),
                'notify_push' => (bool) setting('announcements.push_by_default'),
                'audience' => $request->user()->hasDataRestrictions() ? Announcement::AUDIENCE_TARGETED : Announcement::AUDIENCE_ALL,
            ]),
            ...$this->formOptions($request->user()),
        ]);
    }

    public function store(AnnouncementRequest $request, ManageAnnouncement $announcements): RedirectResponse
    {
        $announcement = $announcements->save($request->announcementData(), $request->user(), null, $request->attachmentFiles(), $request->wantsPublish());

        return redirect()->route('admin.announcements.show', $announcement)->with('status', $this->savedMessage($announcement));
    }

    public function show(Request $request, Announcement $announcement, AnnouncementReadStats $stats): View
    {
        Gate::authorize('view', $announcement);

        $filters = $request->validate([
            'reads' => ['nullable', Rule::in(['read', 'unread', 'acknowledged', 'not_acknowledged'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $announcement->load(['targets', 'attachments', 'author', 'publisher']);
        $recipients = $announcement->isDraft() ? null
            : $stats->recipients($announcement, $filters['reads'] ?? null, $filters['search'] ?? null)->paginate(25, ['employees.*'], 'recipients')->withQueryString();

        return view('admin.announcements.show', [
            'announcement' => $announcement,
            'summary' => $announcement->isDraft() ? null : $stats->summary($announcement),
            'recipients' => $recipients,
            'reads' => $recipients ? $stats->readsFor($announcement, $recipients->getCollection()) : collect(),
            'filters' => $filters,
            'targetLabels' => $this->targetLabels($announcement->targets),
        ]);
    }

    public function edit(Request $request, Announcement $announcement): View
    {
        Gate::authorize('update', $announcement);

        return view('admin.announcements.form', [
            'announcement' => $announcement->load(['targets', 'attachments']),
            ...$this->formOptions($request->user()),
        ]);
    }

    public function update(AnnouncementRequest $request, Announcement $announcement, ManageAnnouncement $announcements): RedirectResponse
    {
        $announcement = $announcements->save($request->announcementData(), $request->user(), $announcement, $request->attachmentFiles(), $request->wantsPublish());

        return redirect()->route('admin.announcements.show', $announcement)->with('status', $this->savedMessage($announcement));
    }

    public function publish(Request $request, Announcement $announcement, ManageAnnouncement $announcements): RedirectResponse
    {
        Gate::authorize('update', $announcement);

        $announcements->publish($announcement, $request->user());

        return back()->with('status', $this->savedMessage($announcement->refresh()));
    }

    public function archive(Request $request, Announcement $announcement, ManageAnnouncement $announcements): RedirectResponse
    {
        Gate::authorize('archive', $announcement);

        $announcements->archive($announcement, $request->user());

        return back()->with('status', __('announcements.messages.archived'));
    }

    public function pin(Request $request, Announcement $announcement, ManageAnnouncement $announcements): RedirectResponse
    {
        Gate::authorize('update', $announcement);

        $pinned = $request->boolean('pinned');
        $announcements->pin($announcement, $pinned, $request->user());

        return back()->with('status', __($pinned ? 'announcements.messages.pinned' : 'announcements.messages.unpinned'));
    }

    public function destroy(Request $request, Announcement $announcement, ManageAnnouncement $announcements): RedirectResponse
    {
        Gate::authorize('delete', $announcement);

        $announcements->delete($announcement, $request->user());

        return redirect()->route('admin.announcements.index')->with('status', __('announcements.messages.deleted'));
    }

    public function attachment(Announcement $announcement, AnnouncementAttachment $attachment, AnnouncementFileStore $files): StreamedResponse
    {
        abort_unless($attachment->announcement_id === $announcement->id, 404);
        Gate::authorize('view', $announcement);

        return $files->response($attachment->file_path, $attachment->file_name, $attachment->file_mime);
    }

    public function destroyAttachment(Request $request, Announcement $announcement, AnnouncementAttachment $attachment, ManageAnnouncement $announcements): RedirectResponse
    {
        abort_unless($attachment->announcement_id === $announcement->id, 404);
        Gate::authorize('update', $announcement);

        $announcements->removeAttachment($announcement, $attachment, $request->user());

        return back()->with('status', __('announcements.messages.attachment_removed'));
    }

    /**
     * The read / acknowledgement report of one announcement (xlsx or csv).
     */
    public function export(Request $request, Announcement $announcement, AnnouncementReadStats $stats, Spreadsheet $spreadsheet): BinaryFileResponse
    {
        Gate::authorize('export', $announcement);

        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $path = storage_path('app/private/announcement-'.uniqid().'.'.$format);
        $headers = array_map(fn (string $key): string => __('announcements.report.'.$key), ['employee_number', 'employee', 'branch', 'department', 'read_at', 'acknowledged_at']);
        $rows = $stats->recipients($announcement)->lazyById(500, 'employees.id', 'id')->chunk(500)->flatMap(function ($employees) use ($announcement, $stats) {
            $reads = $stats->readsFor($announcement, $employees);

            return $employees->map(fn (Employee $employee): array => [
                $employee->employee_number, $employee->name, $employee->branch?->name ?? '', $employee->department?->name ?? '',
                $reads->get($employee->id)?->read_at?->toDateTimeString() ?? '', $reads->get($employee->id)?->acknowledged_at?->toDateTimeString() ?? '',
            ]);
        });

        $spreadsheet->write($path, $format, $headers, $rows);

        return response()->download($path, 'announcement-'.$announcement->id.'-reads-'.now()->format('Ymd').'.'.$format)->deleteFileAfterSend();
    }

    private function savedMessage(Announcement $announcement): string
    {
        return match ($announcement->state()) {
            AnnouncementStatus::Draft => __('announcements.messages.saved_draft'),
            AnnouncementStatus::Scheduled => __('announcements.messages.scheduled', ['date' => $announcement->publish_at?->format('Y-m-d H:i')]),
            default => __('announcements.messages.published'),
        };
    }

    /**
     * Records the admin may target: branches and departments through the global data scope, employee
     * groups limited to the admin's group scope, employment types only for unrestricted admins.
     *
     * @return array<string, mixed>
     */
    private function formOptions(User $user): array
    {
        $restricted = $user->hasDataRestrictions();
        $groupScope = $restricted ? (app(DataScopeManager::class)->effectiveScopeIds($user)['employee_group'] ?? []) : null;

        return [
            'types' => AnnouncementType::cases(),
            'restricted' => $restricted,
            'targetOptions' => [
                AnnouncementTargetType::Branch->value => Branch::query()->active()->orderByName()->get(),
                AnnouncementTargetType::Department->value => Department::query()->active()->with('branch')->orderByName()->get(),
                AnnouncementTargetType::EmployeeGroup->value => EmployeeGroup::query()->active()
                    ->when($groupScope !== null, fn (Builder $query) => $query->whereKey($groupScope ?: [0]))->orderByName()->get(),
                AnnouncementTargetType::EmploymentType->value => $restricted ? collect() : EmploymentType::query()->active()->orderByName()->get(),
            ],
        ];
    }

    /**
     * Names of targets, keyed "type:id".
     *
     * @param  iterable<AnnouncementTarget>  $targets
     * @return array<string, string>
     */
    private function targetLabels(iterable $targets): array
    {
        $models = [
            AnnouncementTargetType::Branch->value => Branch::class,
            AnnouncementTargetType::Department->value => Department::class,
            AnnouncementTargetType::EmployeeGroup->value => EmployeeGroup::class,
            AnnouncementTargetType::EmploymentType->value => EmploymentType::class,
        ];
        $labels = [];

        foreach (collect($targets)->groupBy(fn ($target): string => $target->target_type->value) as $type => $rows) {
            $records = $models[$type]::query()->withoutGlobalScopes()->whereKey($rows->pluck('target_id'))->get()->keyBy('id');

            foreach ($rows as $row) {
                $labels[$type.':'.$row->target_id] = $records->get($row->target_id)?->name ?? '#'.$row->target_id;
            }
        }

        return $labels;
    }
}
