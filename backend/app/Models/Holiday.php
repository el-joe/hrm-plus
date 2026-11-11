<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A public holiday (one day or a range) for the whole company or one branch.
 * Recurring holidays repeat every year on the same month/day range, from the
 * year of start_date on. Read through App\Services\Attendance\HolidayCalendar.
 */
#[Fillable(['name_ar', 'name_en', 'start_date', 'end_date', 'is_recurring', 'is_paid', 'branch_id', 'description', 'is_active'])]
class Holiday extends Model
{
    use Auditable, HasActiveFlag, HasLocalizedName;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_recurring' => 'boolean',
            'is_paid' => 'boolean',
        ];
    }

    protected function hasCodeColumn(): bool
    {
        return false;
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Company-wide holidays plus those of the given branch.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForBranch(Builder $query, ?int $branchId): void
    {
        $query->where(fn (Builder $query) => $query->whereNull('branch_id')->when($branchId, fn (Builder $query) => $query->orWhere('branch_id', $branchId)));
    }

    /**
     * Whether the holiday falls on the date (recurring ones by month/day).
     */
    public function covers(CarbonInterface $date): bool
    {
        if (! $this->is_recurring) {
            return $date->betweenIncluded($this->start_date->copy()->startOfDay(), $this->end_date->copy()->startOfDay());
        }

        if ($date->year < $this->start_date->year) {
            return false;
        }

        $length = (int) $this->start_date->diffInDays($this->end_date);

        // The occurrence starting this year or the one starting last year (a range over New Year).
        foreach ([$date->year, $date->year - 1] as $year) {
            $start = $this->start_date->copy()->setYear($year)->startOfDay();

            if ($year >= $this->start_date->year && $date->betweenIncluded($start, $start->copy()->addDays($length))) {
                return true;
            }
        }

        return false;
    }
}
