<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\PerformanceCycleCompetencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A competency assessed in a cycle, with its weight (the weights of a cycle add up to 100).
 */
#[Fillable(['performance_cycle_id', 'competency_id', 'weight', 'sort_order'])]
class PerformanceCycleCompetency extends Model
{
    /** @use HasFactory<PerformanceCycleCompetencyFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['weight' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<PerformanceCycle, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycle::class, 'performance_cycle_id');
    }

    /**
     * @return BelongsTo<Competency, $this>
     */
    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }
}
