<?php

namespace App\Models;

use App\Enums\AnnouncementStatus;
use App\Enums\AnnouncementTargetType;
use App\Enums\AnnouncementType;
use App\Models\Concerns\Auditable;
use App\Services\Announcements\AnnouncementAudience;
use App\Services\Announcements\HtmlSanitizer;
use App\Services\Authorization\DataScopeManager;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An announcement to all employees or to targeted branches / departments / groups / employment types
 * (Phase 8b). Created and moved only by App\Actions\Announcements\ManageAnnouncement. Bodies are stored
 * already sanitised (HtmlSanitizer).
 *
 * Not data-scoped by a global scope (the audience is a set of targets): admins go through
 * scopeVisibleToAdmin(), employees through scopeVisibleTo().
 */
#[Fillable([
    'title_ar', 'title_en', 'body_ar', 'body_en', 'type', 'status', 'audience', 'publish_at', 'expires_at',
    'is_pinned', 'requires_acknowledgement', 'notify_email', 'notify_push', 'created_by', 'updated_by',
])]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use Auditable, HasFactory;

    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_TARGETED = 'targeted';

    /**
     * Long texts change are audited as "changed" without their content.
     *
     * @var list<string>
     */
    protected array $auditRedact = ['body_ar', 'body_en'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'draft', 'audience' => 'all', 'type' => 'general'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AnnouncementType::class,
            'status' => AnnouncementStatus::class,
            'publish_at' => 'datetime',
            'expires_at' => 'datetime',
            'published_at' => 'datetime',
            'notified_at' => 'datetime',
            'archived_at' => 'datetime',
            'is_pinned' => 'boolean',
            'requires_acknowledgement' => 'boolean',
            'notify_email' => 'boolean',
            'notify_push' => 'boolean',
        ];
    }

    /**
     * @return HasMany<AnnouncementTarget, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(AnnouncementTarget::class);
    }

    /**
     * @return HasMany<AnnouncementAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(AnnouncementAttachment::class)->orderBy('id');
    }

    /**
     * @return HasMany<AnnouncementRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by')->withTrashed();
    }

    /**
     * Value of "{attribute}_{locale}", falling back to the other language when empty.
     */
    public function localized(string $attribute, ?string $locale = null): ?string
    {
        $locale = in_array($locale ?? app()->getLocale(), ['ar', 'en'], true) ? ($locale ?? app()->getLocale()) : 'ar';

        foreach ([$locale, $locale === 'ar' ? 'en' : 'ar'] as $candidate) {
            $value = $this->getAttribute($attribute.'_'.$candidate);

            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }

    public function title(?string $locale = null): string
    {
        return (string) $this->localized('title', $locale);
    }

    /**
     * The sanitised HTML body in the language (raw output is safe: it was sanitised on save).
     */
    public function body(?string $locale = null): string
    {
        return (string) $this->localized('body', $locale);
    }

    public function bodyText(?string $locale = null): string
    {
        return app(HtmlSanitizer::class)->toText($this->body($locale));
    }

    public function summary(?string $locale = null, int $length = 160): string
    {
        return Str::limit(preg_replace('/\s+/u', ' ', $this->bodyText($locale)) ?? '', $length);
    }

    public function isTargeted(): bool
    {
        return $this->audience === self::AUDIENCE_TARGETED;
    }

    /**
     * Status shown on screens: stored status, or scheduled / expired derived from the publish window.
     */
    public function state(): AnnouncementStatus
    {
        if ($this->status !== AnnouncementStatus::Published) {
            return $this->status;
        }

        if ($this->publish_at !== null && $this->publish_at->isFuture()) {
            return AnnouncementStatus::Scheduled;
        }

        if ($this->expires_at !== null && ! $this->expires_at->isFuture()) {
            return AnnouncementStatus::Expired;
        }

        return AnnouncementStatus::Published;
    }

    /**
     * Published and inside its publish window.
     */
    public function isLive(): bool
    {
        return $this->state() === AnnouncementStatus::Published;
    }

    public function isDraft(): bool
    {
        return $this->status === AnnouncementStatus::Draft;
    }

    public function isArchived(): bool
    {
        return $this->status === AnnouncementStatus::Archived;
    }

    /**
     * Whether the employee is in the audience (whatever the status).
     */
    public function targetsEmployee(Employee $employee): bool
    {
        return static::query()->whereKey($this->id)->forAudienceOf($employee)->exists();
    }

    /**
     * Published and inside the publish window.
     *
     * @param  Builder<static>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), AnnouncementStatus::Published)
            ->where(fn (Builder $query) => $query->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * Rows whose derived state is the given one.
     *
     * @param  Builder<static>  $query
     */
    public function scopeInState(Builder $query, AnnouncementStatus $state): void
    {
        match ($state) {
            AnnouncementStatus::Draft, AnnouncementStatus::Archived => $query->where('status', $state),
            AnnouncementStatus::Published => $query->live(),
            AnnouncementStatus::Scheduled => $query->where('status', AnnouncementStatus::Published)->where('publish_at', '>', now()),
            AnnouncementStatus::Expired => $query->where('status', AnnouncementStatus::Published)->where('expires_at', '<=', now()),
        };
    }

    /**
     * Announcements whose audience includes the employee (any status).
     *
     * @param  Builder<static>  $query
     */
    public function scopeForAudienceOf(Builder $query, Employee $employee): void
    {
        $audience = app(AnnouncementAudience::class);
        $departments = $audience->departmentWithAncestors($employee->department_id);
        $groups = $employee->groups()->withoutGlobalScopes()->pluck('employee_groups.id')->all();

        $query->where(fn (Builder $query) => $query
            ->where('audience', self::AUDIENCE_ALL)
            ->orWhereHas('targets', fn (Builder $targets) => $targets->where(fn (Builder $target) => $target
                ->where(fn (Builder $row) => $row->where('target_type', AnnouncementTargetType::Branch)->where('target_id', $employee->branch_id ?? 0))
                ->orWhere(fn (Builder $row) => $row->where('target_type', AnnouncementTargetType::Department)->whereIn('target_id', $departments ?: [0]))
                ->orWhere(fn (Builder $row) => $row->where('target_type', AnnouncementTargetType::EmployeeGroup)->whereIn('target_id', $groups ?: [0]))
                ->orWhere(fn (Builder $row) => $row->where('target_type', AnnouncementTargetType::EmploymentType)->where('target_id', $employee->employment_type_id ?? 0)))));
    }

    /**
     * What the employee may read: live and addressed to them.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, Employee $employee): void
    {
        $query->live()->forAudienceOf($employee);
    }

    /**
     * What an admin may see in the admin area. Unrestricted admins see everything; an admin with data
     * scopes sees company-wide announcements, the ones they wrote and the ones with a target inside
     * their scope (branches, departments with sub-departments, employee groups).
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleToAdmin(Builder $query, User $admin): void
    {
        if (! $admin->hasDataRestrictions()) {
            return;
        }

        $scopes = app(DataScopeManager::class)->effectiveScopeIds($admin);

        $query->where(fn (Builder $query) => $query
            ->where('audience', self::AUDIENCE_ALL)
            ->orWhere('created_by', $admin->id)
            ->orWhereHas('targets', fn (Builder $targets) => $targets->where(function (Builder $target) use ($scopes): void {
                $target->whereRaw('1 = 0');

                foreach ($scopes as $type => $ids) {
                    $target->orWhere(fn (Builder $row) => $row->where('target_type', $type)->whereIn('target_id', $ids ?: [0]));
                }
            })));
    }

    /**
     * Pinned first, then newest publication.
     *
     * @param  Builder<static>  $query
     */
    public function scopeFeedOrder(Builder $query): void
    {
        $query->orderByDesc('is_pinned')
            ->orderByRaw('COALESCE(publish_at, published_at, created_at) DESC')
            ->orderByDesc('id');
    }
}
