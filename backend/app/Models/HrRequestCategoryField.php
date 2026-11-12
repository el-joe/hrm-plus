<?php

namespace App\Models;

use App\Enums\HrRequestFieldType;
use Database\Factories\HrRequestCategoryFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An extra question of a request category (e.g. "Addressed to" on a salary certificate).
 */
#[Fillable(['hr_request_category_id', 'key', 'label_ar', 'label_en', 'type', 'options', 'is_required', 'sort_order'])]
class HrRequestCategoryField extends Model
{
    /** @use HasFactory<HrRequestCategoryFieldFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => HrRequestFieldType::class,
            'options' => 'array',
            'is_required' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<HrRequestCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(HrRequestCategory::class, 'hr_request_category_id');
    }

    public function label(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return (string) ($locale === 'en' ? ($this->label_en ?: $this->label_ar) : ($this->label_ar ?: $this->label_en));
    }

    /**
     * @return list<mixed>
     */
    public function rules(): array
    {
        $rules = [$this->is_required ? 'required' : 'nullable', ...$this->type->rules()];

        if ($this->type === HrRequestFieldType::Select) {
            $rules[] = 'in:'.implode(',', array_map(fn ($option): string => str_replace(',', '', (string) $option), $this->options ?? []));
        }

        return $rules;
    }
}
