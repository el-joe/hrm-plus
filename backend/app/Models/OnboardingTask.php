<?php

namespace App\Models;

use App\Enums\OnboardingTaskAction;
use App\Enums\TaskAssignee;
use App\Enums\TaskStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\OnboardingTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['onboarding_id', 'title_ar', 'title_en', 'description', 'assignee', 'action', 'due_date', 'status', 'note', 'sort_order'])]
class OnboardingTask extends Model
{
    /** @use HasFactory<OnboardingTaskFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assignee' => TaskAssignee::class, 'action' => OnboardingTaskAction::class,
            'status' => TaskStatus::class,
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    protected function title(): Attribute
    {
        return Attribute::get(fn (): ?string => app()->getLocale() === 'en' ? ($this->title_en ?: $this->title_ar) : ($this->title_ar ?: $this->title_en));
    }

    public function isOverdue(): bool
    {
        return $this->status === TaskStatus::Pending && $this->due_date !== null && $this->due_date->isPast() && ! $this->due_date->isToday();
    }

    /**
     * @return BelongsTo<Onboarding, $this>
     */
    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(Onboarding::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
