<?php

namespace App\Models;

use App\Enums\ScheduleType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use App\Models\Contracts\GuardsDeletion;
use Carbon\CarbonInterface;
use Database\Factories\WorkScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A reusable work pattern: weekly (day 0 = Sunday), rotating (cycle of N days
 * from cycle_start_date) or monthly (day of the month). A day with no shifts
 * is a rest day. Assigned through schedule_assignments.
 */
#[Fillable(['code', 'name_ar', 'name_en', 'type', 'cycle_length_days', 'cycle_start_date', 'description', 'is_active'])]
class WorkSchedule extends Model implements GuardsDeletion
{
    /** @use HasFactory<WorkScheduleFactory> */
    use Auditable, HasActiveFlag, HasFactory, HasLocalizedName;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ScheduleType::class,
            'cycle_length_days' => 'integer',
            'cycle_start_date' => 'date',
        ];
    }

    /**
     * @return HasMany<WorkScheduleDay, $this>
     */
    public function days(): HasMany
    {
        return $this->hasMany(WorkScheduleDay::class)->orderBy('day_index')->orderBy('id');
    }

    /**
     * @return HasMany<ScheduleAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ScheduleAssignment::class);
    }

    /**
     * Number of pattern days (7, the cycle length, or 31).
     */
    public function patternLength(): int
    {
        return match ($this->type) {
            ScheduleType::Weekly => 7,
            ScheduleType::Rotating => max(1, (int) $this->cycle_length_days),
            ScheduleType::Monthly => 31,
        };
    }

    /**
     * Pattern day index of a date, or null when the date is before a rotation starts.
     */
    public function dayIndexFor(CarbonInterface $date): ?int
    {
        return match ($this->type) {
            ScheduleType::Weekly => $date->dayOfWeek,
            ScheduleType::Monthly => $date->day,
            ScheduleType::Rotating => $this->cycle_start_date === null || $date->lt($this->cycle_start_date)
                ? null
                : (int) $this->cycle_start_date->diffInDays($date->copy()->startOfDay()) % $this->patternLength(),
        };
    }

    /**
     * Shift ids of each pattern day, keyed by day index.
     *
     * @return Collection<int, list<int>>
     */
    public function pattern(): Collection
    {
        return $this->days->groupBy('day_index')->map(fn (Collection $days): array => $days->pluck('shift_id')->map(fn ($id): int => (int) $id)->all());
    }

    /**
     * @return array<string, int>
     */
    public function usageCounts(): array
    {
        return ['schedule_assignments' => $this->assignments()->count()];
    }
}
