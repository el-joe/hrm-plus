<?php

namespace App\Models;

use App\Enums\AssetAssignmentStatus;
use App\Enums\AssetCondition;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\ScopedThroughEmployee;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One custody period of an asset by an employee: handover (date, condition,
 * notes), the employee's acknowledgement and the return with its condition
 * check. Open assignments are offboarding clearance items
 * (offboarding_items.itemable). Written only by App\Actions\Assets\*.
 */
#[Fillable([
    'asset_id', 'employee_id', 'assigned_on', 'expected_return_on', 'condition_on_issue', 'handover_notes', 'issued_by', 'onboarding_task_id',
])]
class AssetAssignment extends Model
{
    use Auditable, ScopedThroughEmployee;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_on' => 'date',
            'expected_return_on' => 'date',
            'condition_on_issue' => AssetCondition::class,
            'condition_on_return' => AssetCondition::class,
            'returned_on' => 'date',
            'acknowledged_at' => 'datetime',
            'status' => AssetAssignmentStatus::class,
        ];
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), AssetAssignmentStatus::Active);
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return BelongsTo<OnboardingTask, $this>
     */
    public function onboardingTask(): BelongsTo
    {
        return $this->belongsTo(OnboardingTask::class);
    }

    /**
     * Offboarding clearance items that point at this assignment.
     *
     * @return MorphMany<OffboardingItem, $this>
     */
    public function offboardingItems(): MorphMany
    {
        return $this->morphMany(OffboardingItem::class, 'itemable');
    }

    public function isActive(): bool
    {
        return $this->status === AssetAssignmentStatus::Active;
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }

    /**
     * Active and past its expected return date.
     */
    public function isOverdue(): bool
    {
        return $this->isActive() && $this->expected_return_on !== null && $this->expected_return_on->lt(today());
    }
}
