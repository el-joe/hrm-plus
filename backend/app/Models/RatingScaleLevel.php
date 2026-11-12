<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\RatingScaleLevelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rating_scale_id', 'value', 'label_ar', 'label_en', 'description', 'min_score', 'color'])]
class RatingScaleLevel extends Model
{
    /** @use HasFactory<RatingScaleLevelFactory> */
    use Auditable, HasFactory;

    public const COLORS = ['chart-1', 'chart-2', 'chart-3', 'chart-4', 'chart-5', 'chart-6'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'decimal:2', 'min_score' => 'decimal:2'];
    }

    /**
     * The label in the current locale (falls back to the other language).
     */
    protected function label(): Attribute
    {
        return Attribute::get(fn (): ?string => app()->getLocale() === 'en' ? ($this->label_en ?: $this->label_ar) : ($this->label_ar ?: $this->label_en));
    }

    public function localizedLabel(?string $locale): ?string
    {
        return $locale === 'en' ? ($this->label_en ?: $this->label_ar) : ($this->label_ar ?: $this->label_en);
    }

    /**
     * @return BelongsTo<RatingScale, $this>
     */
    public function scale(): BelongsTo
    {
        return $this->belongsTo(RatingScale::class, 'rating_scale_id');
    }
}
