<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use Database\Factories\HrLetterTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A bilingual letter (salary certificate, experience letter, …) issued as a PDF on HR requests of a
 * "letter" category. Plain text with {{placeholders}} from App\Services\HrRequests\HrLetterDocument::PLACEHOLDERS.
 */
#[Fillable(['code', 'name_ar', 'name_en', 'body_ar', 'body_en', 'is_active'])]
class HrLetterTemplate extends Model
{
    /** @use HasFactory<HrLetterTemplateFactory> */
    use Auditable, HasActiveFlag, HasFactory, HasLocalizedName;

    /**
     * @return HasMany<HrRequestCategory, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(HrRequestCategory::class);
    }

    public function body(string $locale): string
    {
        return (string) ($locale === 'en' ? ($this->body_en ?: $this->body_ar) : ($this->body_ar ?: $this->body_en));
    }
}
