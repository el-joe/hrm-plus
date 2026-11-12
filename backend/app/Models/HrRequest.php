<?php

namespace App\Models;

use App\Enums\HrRequestMessageType;
use App\Enums\HrRequestPriority;
use App\Enums\HrRequestSource;
use App\Enums\HrRequestStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasApprovals;
use App\Models\Concerns\ScopedThroughEmployee;
use Database\Factories\HrRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An employee's request to HR (Phase 8b, spec module 13). Created and moved only by
 * App\Actions\HrRequests\*; every transition is in hr_request_events and the audit log.
 *
 * Data scope: the employee's current placement (ScopedThroughEmployee). Which requests an admin
 * handles is App\Services\HrRequests\HrRequestVisibility.
 */
#[Fillable([
    'reference', 'employee_id', 'hr_request_category_id', 'branch_id', 'department_id', 'subject', 'description', 'priority',
    'status', 'assigned_to', 'submitted_by', 'source', 'letter_language', 'opened_at', 'due_at',
])]
class HrRequest extends Model
{
    /** @use HasFactory<HrRequestFactory> */
    use Auditable, HasApprovals, HasFactory, ScopedThroughEmployee;

    public const WORKFLOW = 'hr_request';

    /**
     * @var list<string>
     */
    protected array $auditRedact = ['description', 'resolution_note'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'open', 'priority' => 'normal', 'escalation_level' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => HrRequestStatus::class,
            'priority' => HrRequestPriority::class,
            'source' => HrRequestSource::class,
            'opened_at' => 'datetime',
            'due_at' => 'datetime',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_escalated_at' => 'datetime',
            'escalation_level' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<HrRequestCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(HrRequestCategory::class, 'hr_request_category_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by')->withTrashed();
    }

    /**
     * @return HasMany<HrRequestMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(HrRequestMessage::class)->orderBy('id');
    }

    /**
     * Thread entries the employee may read (never internal notes).
     *
     * @return HasMany<HrRequestMessage, $this>
     */
    public function employeeMessages(): HasMany
    {
        return $this->messages()->where('type', '!=', HrRequestMessageType::InternalNote->value);
    }

    /**
     * @return HasMany<HrRequestAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(HrRequestAttachment::class)->orderBy('id');
    }

    /**
     * Files the employee may download (not HR-only ones).
     *
     * @return HasMany<HrRequestAttachment, $this>
     */
    public function employeeAttachments(): HasMany
    {
        return $this->attachments()->where('is_internal', false);
    }

    /**
     * @return HasMany<HrRequestFieldValue, $this>
     */
    public function fieldValues(): HasMany
    {
        return $this->hasMany(HrRequestFieldValue::class)->orderBy('id');
    }

    /**
     * @return HasMany<HrRequestEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(HrRequestEvent::class)->orderBy('id');
    }

    /**
     * @return HasMany<HrProfileChange, $this>
     */
    public function profileChanges(): HasMany
    {
        return $this->hasMany(HrProfileChange::class)->orderBy('id');
    }

    /**
     * Requests still open (not resolved, rejected or cancelled).
     *
     * @param  Builder<HrRequest>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn($query->qualifyColumn('status'), array_map(fn (HrRequestStatus $status): string => $status->value, HrRequestStatus::openStatuses()));
    }

    /**
     * Open requests past their due time (the employee is not the one holding them).
     *
     * @param  Builder<HrRequest>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->whereIn($query->qualifyColumn('status'), [HrRequestStatus::Open->value, HrRequestStatus::InProgress->value])
            ->whereNotNull($query->qualifyColumn('due_at'))
            ->where($query->qualifyColumn('due_at'), '<', now());
    }

    public function isOverdue(): bool
    {
        return $this->status->isEscalatable() && $this->due_at !== null && $this->due_at->isPast();
    }

    public function isClosed(): bool
    {
        return $this->status->isClosed();
    }

    /**
     * A letter already issued on the request (latest).
     */
    public function letter(): ?HrRequestAttachment
    {
        return $this->attachments()->where('is_letter', true)->latest('id')->first();
    }
}
