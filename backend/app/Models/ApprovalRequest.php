<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Database\Factories\ApprovalRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One run of an approval workflow for one record. Created and advanced only
 * by App\Services\Approvals\ApprovalService.
 */
#[Fillable(['approval_workflow_id', 'approvable_type', 'approvable_id', 'requested_by', 'status', 'current_step_id', 'completed_at'])]
class ApprovalRequest extends Model
{
    /** @use HasFactory<ApprovalRequestFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ApprovalWorkflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    /**
     * @return BelongsTo<ApprovalWorkflowStep, $this>
     */
    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflowStep::class, 'current_step_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The per-step history, oldest first.
     *
     * @return HasMany<ApprovalAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class)->orderBy('id');
    }

    /**
     * @param  Builder<ApprovalRequest>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', ApprovalStatus::Pending);
    }

    public function isPending(): bool
    {
        return $this->status === ApprovalStatus::Pending;
    }
}
