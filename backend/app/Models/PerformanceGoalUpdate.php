<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One progress update of a goal (history; rows are only added).
 */
#[Fillable(['performance_goal_id', 'progress', 'current_value', 'note', 'created_by'])]
class PerformanceGoalUpdate extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['progress' => 'decimal:2', 'current_value' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<PerformanceGoal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(PerformanceGoal::class, 'performance_goal_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
