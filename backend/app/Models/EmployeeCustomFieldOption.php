<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One choice of a "select" custom field.
 */
#[Fillable(['custom_field_id', 'value', 'label_ar', 'label_en', 'sort_order'])]
class EmployeeCustomFieldOption extends Model
{
    public $timestamps = false;

    public function label(): string
    {
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';

        return $this->{'label_'.$locale} ?: $this->label_ar ?: $this->label_en;
    }

    /**
     * @return BelongsTo<EmployeeCustomField, $this>
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(EmployeeCustomField::class, 'custom_field_id');
    }
}
