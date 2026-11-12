<?php

namespace App\Actions\Announcements;

use App\Enums\AnnouncementStatus;
use App\Enums\AnnouncementTargetType;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use App\Models\AnnouncementRead;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\Announcements\AnnouncementPublishedNotification;
use App\Services\Announcements\AnnouncementAudience;
use App\Services\Announcements\AnnouncementFileStore;
use App\Services\Announcements\HtmlSanitizer;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\DataScopeManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The life of an announcement:
 *
 * save():      create / edit (bodies sanitised, targets checked against the author's data scope:
 *              an admin with data restrictions cannot address everyone and may only target their own
 *              branches / departments (or departments of their branches) / employee groups — never an
 *              employment type, which spans the company); optionally publish in the same step;
 * publish():   draft → published (publish_at in the future = scheduled); notifications go out at once
 *              when it is visible, else through sendDue() (command announcements:publish-scheduled);
 * archive():   published → archived (hidden from employees, statistics kept);
 * pin() / delete() (drafts only) / removeAttachment();
 * markRead() / acknowledge(): the employee side.
 */
class ManageAnnouncement
{
    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
        private readonly AnnouncementFileStore $files,
        private readonly AnnouncementAudience $audience,
        private readonly DataScopeManager $scopes,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{title_ar?: ?string, title_en?: ?string, body_ar?: ?string, body_en?: ?string, type: string, audience: string, targets?: array<string, list<int|string>>, publish_at?: ?string, expires_at?: ?string, is_pinned?: bool, requires_acknowledgement?: bool, notify_email?: bool, notify_push?: bool}  $data
     * @param  list<UploadedFile>  $attachments
     *
     * @throws ValidationException
     */
    public function save(array $data, User $actor, ?Announcement $announcement = null, array $attachments = [], bool $publish = false): Announcement
    {
        if ($announcement !== null && $announcement->isArchived()) {
            throw ValidationException::withMessages(['status' => __('announcements.validation.archived')]);
        }

        $bodies = ['body_ar' => $this->sanitizer->sanitize($data['body_ar'] ?? null), 'body_en' => $this->sanitizer->sanitize($data['body_en'] ?? null)];
        $titles = ['title_ar' => trim((string) ($data['title_ar'] ?? '')) ?: null, 'title_en' => trim((string) ($data['title_en'] ?? '')) ?: null];
        $complete = fn (string $locale): bool => filled($titles['title_'.$locale]) && $this->sanitizer->toText($bodies['body_'.$locale]) !== '';

        if (! $complete('ar') && ! $complete('en')) {
            throw ValidationException::withMessages(['title_ar' => __('announcements.validation.one_language')]);
        }

        $targets = $data['audience'] === Announcement::AUDIENCE_TARGETED ? $this->normaliseTargets($data['targets'] ?? []) : [];

        if ($data['audience'] === Announcement::AUDIENCE_TARGETED && $targets === []) {
            throw ValidationException::withMessages(['targets' => __('announcements.validation.targets_required')]);
        }

        $this->assertWithinScope($actor, $data['audience'], $targets);

        $announcement = DB::transaction(function () use ($data, $actor, $announcement, $attachments, $bodies, $titles, $targets): Announcement {
            $announcement ??= new Announcement(['created_by' => $actor->id, 'status' => AnnouncementStatus::Draft]);
            $announcement->fill([
                ...$titles,
                ...$bodies,
                'type' => $data['type'],
                'audience' => $data['audience'],
                'publish_at' => $data['publish_at'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'is_pinned' => (bool) ($data['is_pinned'] ?? false),
                'requires_acknowledgement' => (bool) ($data['requires_acknowledgement'] ?? false),
                'notify_email' => (bool) ($data['notify_email'] ?? false),
                'notify_push' => (bool) ($data['notify_push'] ?? false),
                'updated_by' => $actor->id,
            ])->save();

            $this->syncTargets($announcement, $targets);

            foreach ($attachments as $file) {
                $announcement->attachments()->create([...$this->files->store($announcement, $file), 'uploaded_by' => $actor->id]);
            }

            return $announcement;
        });

        if ($publish && $announcement->isDraft()) {
            $this->publish($announcement, $actor);
        } elseif ($announcement->status === AnnouncementStatus::Published) {
            // An edit may bring a scheduled announcement forward: send what is due now.
            $this->notifyIfDue($announcement->refresh());
        }

        return $announcement->refresh();
    }

    /**
     * @throws ValidationException
     */
    public function publish(Announcement $announcement, User $actor): Announcement
    {
        if (! $announcement->isDraft()) {
            throw ValidationException::withMessages(['status' => __('announcements.validation.not_draft')]);
        }

        if ($announcement->expires_at !== null && ! $announcement->expires_at->isFuture()) {
            throw ValidationException::withMessages(['expires_at' => __('announcements.validation.expired')]);
        }

        $announcement->forceFill([
            'status' => AnnouncementStatus::Published,
            'published_at' => now(),
            'published_by' => $actor->id,
        ])->save();

        $this->audit->log('announcement.published', $announcement, meta: [
            'audience' => $announcement->audience,
            'scheduled_for' => $announcement->publish_at?->isFuture() ? $announcement->publish_at->toIso8601String() : null,
        ], actor: $actor);

        $this->notifyIfDue($announcement);

        return $announcement;
    }

    /**
     * @throws ValidationException
     */
    public function archive(Announcement $announcement, User $actor): Announcement
    {
        if ($announcement->status !== AnnouncementStatus::Published) {
            throw ValidationException::withMessages(['status' => __('announcements.validation.not_published')]);
        }

        $announcement->forceFill(['status' => AnnouncementStatus::Archived, 'archived_at' => now(), 'archived_by' => $actor->id, 'is_pinned' => false])->save();
        $this->audit->log('announcement.archived', $announcement, actor: $actor);

        return $announcement;
    }

    public function pin(Announcement $announcement, bool $pinned, User $actor): Announcement
    {
        if ($announcement->isArchived()) {
            throw ValidationException::withMessages(['status' => __('announcements.validation.archived')]);
        }

        $announcement->forceFill(['is_pinned' => $pinned, 'updated_by' => $actor->id])->save();
        $this->audit->log($pinned ? 'announcement.pinned' : 'announcement.unpinned', $announcement, actor: $actor);

        return $announcement;
    }

    /**
     * Only drafts are deleted; published announcements are archived to keep their read history.
     *
     * @throws ValidationException
     */
    public function delete(Announcement $announcement, User $actor): void
    {
        if (! $announcement->isDraft()) {
            throw ValidationException::withMessages(['status' => __('announcements.validation.delete_draft_only')]);
        }

        DB::transaction(function () use ($announcement, $actor): void {
            $paths = $announcement->attachments()->pluck('file_path')->all();
            $this->audit->log('announcement.deleted', $announcement, meta: ['title' => $announcement->title()], actor: $actor);
            $announcement->delete();

            DB::afterCommit(fn () => array_map($this->files->delete(...), $paths));
        });
    }

    /**
     * @throws ValidationException
     */
    public function removeAttachment(Announcement $announcement, AnnouncementAttachment $attachment, User $actor): void
    {
        if ($announcement->isArchived()) {
            throw ValidationException::withMessages(['status' => __('announcements.validation.archived')]);
        }

        $path = $attachment->file_path;
        $attachment->delete();
        $this->files->delete($path);
        $this->audit->log('announcement.attachment_removed', $announcement, meta: ['file' => $attachment->file_name], actor: $actor);
    }

    /**
     * Send the notifications of every published announcement that has become visible (scheduler).
     *
     * @return int announcements notified
     */
    public function sendDue(): int
    {
        $count = 0;

        Announcement::query()->live()->whereNull('notified_at')->orderBy('id')->each(function (Announcement $announcement) use (&$count): void {
            if ($this->notifyIfDue($announcement)) {
                $count++;
            }
        });

        return $count;
    }

    /**
     * The employee opened the announcement (portal or API).
     */
    public function markRead(Announcement $announcement, Employee $employee): AnnouncementRead
    {
        return AnnouncementRead::query()->firstOrCreate(
            ['announcement_id' => $announcement->id, 'employee_id' => $employee->id],
            ['read_at' => now()],
        );
    }

    /**
     * @param  'portal'|'api'  $via
     *
     * @throws ValidationException
     */
    public function acknowledge(Announcement $announcement, Employee $employee, User $user, string $via): AnnouncementRead
    {
        if (! $announcement->requires_acknowledgement) {
            throw ValidationException::withMessages(['announcement' => __('announcements.validation.no_acknowledgement')]);
        }

        $read = $this->markRead($announcement, $employee);

        if ($read->acknowledged_at === null) {
            $read->forceFill(['acknowledged_at' => now(), 'acknowledged_via' => $via])->save();
            $this->audit->log('announcement.acknowledged', $announcement, meta: ['employee' => $employee->id, 'via' => $via], actor: $user);
        }

        return $read;
    }

    /**
     * Notify the audience once, when the announcement is visible. Claims notified_at first so two
     * workers never notify twice.
     */
    private function notifyIfDue(Announcement $announcement): bool
    {
        if (! $announcement->isLive() || $announcement->notified_at !== null) {
            return false;
        }

        $claimed = Announcement::query()->whereKey($announcement->id)->whereNull('notified_at')->update(['notified_at' => now()]);

        if ($claimed === 0) {
            return false;
        }

        $sent = 0;
        $notification = new AnnouncementPublishedNotification($announcement->refresh());

        $this->scopes->withoutScoping(function () use ($announcement, $notification, &$sent): void {
            $this->audience->employees($announcement)->select('employees.id')->chunkById(500, function (Collection $employees) use ($notification, &$sent): void {
                $users = User::query()->whereIn('employee_id', $employees->pluck('id'))->where('status', UserStatus::Active)->get();
                Notification::send($users, $notification);
                $sent += $users->count();
            }, 'employees.id', 'id');
        });

        $announcement->forceFill(['notified_count' => $sent])->saveQuietly();

        return true;
    }

    /**
     * @param  array<string, list<int|string>>  $targets
     * @return array<string, list<int>> type => ids
     */
    private function normaliseTargets(array $targets): array
    {
        $normalised = [];

        foreach (AnnouncementTargetType::cases() as $type) {
            $ids = collect($targets[$type->value] ?? [])->filter(fn ($id): bool => filled($id))->map(fn ($id): int => (int) $id)->unique()->values()->all();

            if ($ids !== []) {
                $normalised[$type->value] = $ids;
            }
        }

        return $normalised;
    }

    /**
     * Whether the admin may address this audience: unrestricted admins anything; an admin with data
     * restrictions only targeted audiences inside their scope.
     *
     * @param  array<string, list<int>>  $targets  type => ids
     */
    public function canTarget(User $actor, string $audience, array $targets): bool
    {
        if (! $actor->hasDataRestrictions()) {
            return true;
        }

        if ($audience !== Announcement::AUDIENCE_TARGETED || $targets === []) {
            return false;
        }

        $scope = $this->scopes->effectiveScopeIds($actor);
        $branches = $scope['branch'] ?? [];
        $departments = $scope['department'] ?? [];
        $groups = $scope['employee_group'] ?? [];

        foreach ($targets as $type => $ids) {
            $allowed = match (AnnouncementTargetType::from($type)) {
                AnnouncementTargetType::Branch => array_diff($ids, $branches) === [],
                AnnouncementTargetType::Department => collect($ids)->every(fn (int $id): bool => in_array($id, $departments, true)
                    || in_array((int) Department::query()->withoutGlobalScopes()->whereKey($id)->value('branch_id'), $branches, true)),
                AnnouncementTargetType::EmployeeGroup => array_diff($ids, $groups) === [],
                AnnouncementTargetType::EmploymentType => false,
            };

            if (! $allowed) {
                return false;
            }
        }

        return true;
    }

    /**
     * Targets of a stored announcement as type => ids.
     *
     * @return array<string, list<int>>
     */
    public static function targetMap(Announcement $announcement): array
    {
        return $announcement->targets->groupBy(fn ($target): string => $target->target_type->value)
            ->map(fn ($rows): array => $rows->pluck('target_id')->map(fn ($id): int => (int) $id)->values()->all())->all();
    }

    /**
     * @param  array<string, list<int>>  $targets
     *
     * @throws ValidationException
     */
    private function assertWithinScope(User $actor, string $audience, array $targets): void
    {
        if ($this->canTarget($actor, $audience, $targets)) {
            return;
        }

        throw ValidationException::withMessages([
            $audience === Announcement::AUDIENCE_TARGETED ? 'targets' : 'audience' => __($audience === Announcement::AUDIENCE_TARGETED
                ? 'announcements.validation.target_scope' : 'announcements.validation.audience_scope'),
        ]);
    }

    /**
     * @param  array<string, list<int>>  $targets
     */
    private function syncTargets(Announcement $announcement, array $targets): void
    {
        $wanted = collect($targets)->flatMap(fn (array $ids, string $type) => array_map(fn (int $id): string => $type.':'.$id, $ids));
        $existing = $announcement->targets()->get();

        $existing->reject(fn ($target): bool => $wanted->contains($target->target_type->value.':'.$target->target_id))->each->delete();
        $have = $existing->map(fn ($target): string => $target->target_type->value.':'.$target->target_id);

        foreach ($wanted->diff($have) as $key) {
            [$type, $id] = explode(':', $key);
            $announcement->targets()->create(['target_type' => $type, 'target_id' => (int) $id]);
        }
    }
}
