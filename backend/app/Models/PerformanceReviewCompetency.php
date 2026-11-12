<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A competency rated in one review (weight copied from the cycle at launch).
 */
#[Fillable(['performance_review_id', 'competency_id', 'weight', 'sort_order'])]
class PerformanceReviewCompetency extends Model
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['weight' => 'decimal:2', 'self_rating' => 'decimal:2', 'manager_rating' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<PerformanceReview, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(PerformanceReview::class, 'performance_review_id');
    }

    /**
     * @return BelongsTo<Competency, $this>
     */
    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }
}
