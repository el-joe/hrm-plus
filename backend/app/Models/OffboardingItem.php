<?php

namespace App\Models;

use App\Enums\HandoverCategory;
use App\Enums\TaskAssignee;
use App\Enums\TaskStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A handover / clearance item of an offboarding case. `itemable` points at
 * the source record when a handover provider created it (assets later).
 */
#[Fillable(['offboarding_case_id', 'category', 'title', 'description', 'assignee', 'itemable_type', 'itemable_id', 'status', 'note', 'sort_order'])]
class OffboardingItem extends Model
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => HandoverCategory::class,
            'assignee' => TaskAssignee::class,
            'status' => TaskStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OffboardingCase, $this>
     */
    public function offboardingCase(): BelongsTo
    {
        return $this->belongsTo(OffboardingCase::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
