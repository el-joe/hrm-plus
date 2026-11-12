<?php

namespace App\Models;

use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\ScopedThroughEmployee;
use Database\Factories\PerformanceGoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A goal or KPI of an employee, usually inside a cycle. `progress` (0–100) is the
 * latest progress update; for KPIs it follows current_value / target_value.
 * Ratings (self / manager) are on the cycle's scale and feed the review score.
 * Weights of the counted (not cancelled) goals of a review must total 100.
 */
#[Fillable([
    'employee_id', 'performance_cycle_id', 'type', 'title', 'description', 'unit', 'target_value', 'current_value', 'weight',
    'progress', 'start_date', 'due_date', 'status', 'created_by',
])]
class PerformanceGoal extends Model
{
    /** @use HasFactory<PerformanceGoalFactory> */
    use Auditable, HasFactory, ScopedThroughEmployee;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'active', 'type' => 'goal', 'progress' => '0.00'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => GoalType::class,
            'status' => GoalStatus::class,
            'target_value' => 'decimal:2',
            'current_value' => 'decimal:2',
            'weight' => 'decimal:2',
            'progress' => 'decimal:2',
            'self_rating' => 'decimal:2',
            'manager_rating' => 'decimal:2',
            'start_date' => 'date',
            'due_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<PerformanceCycle, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycle::class, 'performance_cycle_id');
    }

    /**
     * Progress history, newest first.
     *
     * @return HasMany<PerformanceGoalUpdate, $this>
     */
    public function updates(): HasMany
    {
        return $this->hasMany(PerformanceGoalUpdate::class)->latest('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<PerformanceGoal>  $query
     */
    public function scopeCounted(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), '!=', GoalStatus::Cancelled->value);
    }

    public function isKpi(): bool
    {
        return $this->type === GoalType::Kpi;
    }

    public function isOverdue(): bool
    {
        return $this->status === GoalStatus::Active && $this->due_date !== null && $this->due_date->isPast() && ! $this->due_date->isToday();
    }
}
