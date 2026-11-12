<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\AttendancePeriodLockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A locked attendance period (all branches or one). While active, the days
 * inside it are frozen; later changes become attendance_adjustments rows.
 */
#[Fillable(['start_date', 'end_date', 'branch_id', 'note', 'locked_by', 'locked_at'])]
class AttendancePeriodLock extends Model
{
    /** @use HasFactory<AttendancePeriodLockFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'locked_at' => 'datetime',
            'unlocked_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('unlocked_at');
    }

    /**
     * Active locks covering the date for the branch (or all branches).
     *
     * @param  Builder<static>  $query
     */
    public function scopeCovering(Builder $query, string $date, ?int $branchId): void
    {
        $query->active()->where('start_date', '<=', $date)->where('end_date', '>=', $date)
            ->where(fn (Builder $query) => $query->whereNull('branch_id')->when($branchId, fn (Builder $query) => $query->orWhere('branch_id', $branchId)));
    }

    public function isActive(): bool
    {
        return $this->unlocked_at === null;
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * @return HasMany<AttendanceDay, $this>
     */
    public function days(): HasMany
    {
        return $this->hasMany(AttendanceDay::class);
    }
}
