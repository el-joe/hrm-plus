<?php

namespace App\Models;

use App\Enums\BreakType;
use App\Enums\ShiftType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use App\Models\Contracts\GuardsDeletion;
use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shift definition. Times are wall-clock times in the employee's branch
 * timezone; an end time at or before the start time means the shift ends the
 * next day (cross-midnight). Flexible shifts use start/end as the window in
 * which required_minutes may be worked, optionally with core hours.
 */
#[Fillable([
    'code', 'name_ar', 'name_en', 'type', 'start_time', 'end_time', 'required_minutes', 'core_start_time', 'core_end_time',
    'checkin_opens_before_minutes', 'checkout_closes_after_minutes', 'late_grace_minutes', 'early_leave_grace_minutes',
    'is_night', 'color', 'description', 'is_active',
])]
class Shift extends Model implements GuardsDeletion
{
    /** @use HasFactory<ShiftFactory> */
    use Auditable, HasActiveFlag, HasFactory, HasLocalizedName;

    /**
     * Calendar colour tokens a shift may use.
     *
     * @var list<string>
     */
    public const COLORS = ['chart-1', 'chart-2', 'chart-3', 'chart-4', 'chart-5', 'chart-6'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ShiftType::class,
            'required_minutes' => 'integer',
            'checkin_opens_before_minutes' => 'integer',
            'checkout_closes_after_minutes' => 'integer',
            'late_grace_minutes' => 'integer',
            'early_leave_grace_minutes' => 'integer',
            'is_night' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ShiftBreak, $this>
     */
    public function breaks(): HasMany
    {
        return $this->hasMany(ShiftBreak::class)->orderBy('sort_order')->orderBy('id');
    }

    public function isFlexible(): bool
    {
        return $this->type === ShiftType::Flexible;
    }

    public function crossesMidnight(): bool
    {
        return self::minuteOfDay($this->end_time) <= self::minuteOfDay($this->start_time);
    }

    /**
     * Length from start to end in minutes (handles cross-midnight).
     */
    public function spanMinutes(): int
    {
        $span = self::minuteOfDay($this->end_time) - self::minuteOfDay($this->start_time);

        return $span <= 0 ? $span + 1440 : $span;
    }

    public function unpaidBreakMinutes(): int
    {
        return (int) $this->breaks->where('is_paid', false)->sum('duration_minutes');
    }

    public function paidBreakMinutes(): int
    {
        return (int) $this->breaks->where('is_paid', true)->sum('duration_minutes');
    }

    /**
     * Minutes the employee is expected to work: required_minutes for a
     * flexible shift, otherwise the span minus unpaid breaks.
     */
    public function expectedMinutes(): int
    {
        return $this->isFlexible() ? (int) $this->required_minutes : max(0, $this->spanMinutes() - $this->unpaidBreakMinutes());
    }

    /**
     * "08:00–16:00" (times shown without seconds).
     */
    public function timeRange(): string
    {
        return substr((string) $this->start_time, 0, 5).'–'.substr((string) $this->end_time, 0, 5);
    }

    /**
     * Minute of the day of an "HH:MM[:SS]" time.
     */
    public static function minuteOfDay(?string $time): int
    {
        if ($time === null || $time === '') {
            return 0;
        }

        [$hours, $minutes] = array_map('intval', explode(':', $time) + [0, 0]);

        return $hours * 60 + $minutes;
    }

    /**
     * @return array<string, int>
     */
    public function usageCounts(): array
    {
        return [
            'work_schedules' => WorkScheduleDay::query()->where('shift_id', $this->getKey())->distinct()->count('work_schedule_id'),
            'attendance_days' => AttendanceDay::query()->withoutGlobalScopes()->where('shift_id', $this->getKey())->count(),
            'schedule_overrides' => ScheduleOverride::query()->where('shift_id', $this->getKey())->count(),
        ];
    }

    /**
     * Fixed-time breaks only.
     *
     * @return list<ShiftBreak>
     */
    public function fixedBreaks(): array
    {
        return $this->breaks->filter(fn (ShiftBreak $break): bool => $break->type === BreakType::Fixed)->values()->all();
    }
}
