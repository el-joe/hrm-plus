<?php

namespace App\Models;

use App\Enums\OnboardingTaskAction;
use App\Enums\TaskAssignee;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['onboarding_template_id', 'title_ar', 'title_en', 'description', 'assignee', 'action', 'due_offset_days', 'sort_order'])]
class OnboardingTemplateTask extends Model
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['assignee' => TaskAssignee::class, 'action' => OnboardingTaskAction::class, 'due_offset_days' => 'integer', 'sort_order' => 'integer'];
    }

    protected function title(): Attribute
    {
        return Attribute::get(fn (): ?string => app()->getLocale() === 'en' ? ($this->title_en ?: $this->title_ar) : ($this->title_ar ?: $this->title_en));
    }

    /**
     * @return BelongsTo<OnboardingTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(OnboardingTemplate::class, 'onboarding_template_id');
    }
}
