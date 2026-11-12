<?php

namespace App\Models;

use App\Enums\HrRequestCategoryType;
use App\Enums\HrRequestPriority;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use Database\Factories\HrRequestCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What an employee may ask HR for (Phase 8b): SLA, default priority / assignee / escalation target,
 * optional approval (workflow "hr_request"), attachment rule and extra fields.
 */
#[Fillable([
    'code', 'name_ar', 'name_en', 'description_ar', 'description_en', 'type', 'hr_letter_template_id', 'sla_hours',
    'default_priority', 'default_assignee_id', 'escalation_user_id', 'requires_approval', 'requires_attachment', 'is_active', 'sort_order',
])]
class HrRequestCategory extends Model
{
    /** @use HasFactory<HrRequestCategoryFactory> */
    use Auditable, HasActiveFlag, HasFactory, HasLocalizedName;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => HrRequestCategoryType::class,
            'default_priority' => HrRequestPriority::class,
            'requires_approval' => 'boolean',
            'requires_attachment' => 'boolean',
            'sla_hours' => 'integer',
        ];
    }

    /**
     * @return HasMany<HrRequestCategoryField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(HrRequestCategoryField::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<HrLetterTemplate, $this>
     */
    public function letterTemplate(): BelongsTo
    {
        return $this->belongsTo(HrLetterTemplate::class, 'hr_letter_template_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function defaultAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_assignee_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function escalationUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalation_user_id')->withTrashed();
    }

    /**
     * @return HasMany<HrRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(HrRequest::class);
    }

    public function slaHours(): int
    {
        return $this->sla_hours ?? (int) setting('hr_requests.default_sla_hours');
    }

    public function isLetter(): bool
    {
        return $this->type === HrRequestCategoryType::Letter;
    }

    public function isProfileChange(): bool
    {
        return $this->type === HrRequestCategoryType::ProfileChange;
    }

    public function description(?string $locale = null): ?string
    {
        return $this->localized('description', $locale);
    }
}
