<?php

namespace App\Models;

use App\Enums\WorkLocationScope;
use App\Models\Concerns\Auditable;
use Database\Factories\AttendanceWorkLocationRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where an employee (or every employee of a department) may check in from
 * the mobile app, replacing the default (the active locations of their
 * branch): a specific location, or anywhere (remote work), optionally for a
 * date range. Read by App\Services\Attendance\Mobile\WorkLocationResolver.
 */
#[Fillable(['scope_type', 'scope_id', 'branch_location_id', 'allow_anywhere', 'effective_from', 'effective_to', 'note', 'created_by'])]
class AttendanceWorkLocationRule extends Model
{
    /** @use HasFactory<AttendanceWorkLocationRuleFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope_type' => WorkLocationScope::class,
            'scope_id' => 'integer',
            'allow_anywhere' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * @return BelongsTo<BranchLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(BranchLocation::class, 'branch_location_id')->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'scope_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'scope_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeEffectiveOn(Builder $query, string $date): void
    {
        $query->where(fn (Builder $query) => $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date))
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date));
    }

    public function scopeLabel(): string
    {
        return match ($this->scope_type) {
            WorkLocationScope::Employee => $this->employee()->withoutGlobalScopes()->first()?->label() ?? '#'.$this->scope_id,
            WorkLocationScope::Department => $this->department()->withoutGlobalScopes()->first()?->name ?? '#'.$this->scope_id,
        };
    }
}
