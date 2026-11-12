<?php

namespace App\Models;

use Database\Factories\HrRequestFieldValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The answer to a category's extra field on one request (label copied at submission).
 */
#[Fillable(['hr_request_id', 'hr_request_category_field_id', 'label_ar', 'label_en', 'value'])]
class HrRequestFieldValue extends Model
{
    /** @use HasFactory<HrRequestFieldValueFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<HrRequest, $this>
     */
    public function hrRequest(): BelongsTo
    {
        return $this->belongsTo(HrRequest::class);
    }

    /**
     * @return BelongsTo<HrRequestCategoryField, $this>
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(HrRequestCategoryField::class, 'hr_request_category_field_id');
    }

    public function label(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return (string) ($locale === 'en' ? ($this->label_en ?: $this->label_ar) : ($this->label_ar ?: $this->label_en));
    }
}
