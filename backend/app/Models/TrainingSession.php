<?php

namespace App\Models;

use App\Enums\TrainingDeliveryMode;
use App\Enums\TrainingEnrolmentStatus;
use App\Enums\TrainingSessionStatus;
use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Database\Factories\TrainingSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A dated run of a course (TRS-2026-00001): dates and times, classroom / online,
 * location or meeting link, capacity, trainer and an optional cost override.
 * Sessions are company catalogue records (not data-scoped); their enrolments are.
 */
#[Fillable([
    'reference', 'training_course_id', 'start_date', 'end_date', 'start_time', 'end_time', 'delivery_mode', 'location',
    'meeting_url', 'capacity', 'trainer', 'cost_per_participant', 'branch_id', 'status', 'notes', 'created_by',
])]
class TrainingSession extends Model
{
    /** @use HasFactory<TrainingSessionFactory> */
    use Auditable, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'scheduled'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'delivery_mode' => TrainingDeliveryMode::class,
            'capacity' => 'integer',
            'cost_per_participant' => 'decimal:2',
            'status' => TrainingSessionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<TrainingCourse, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'training_course_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<TrainingEnrolment, $this>
     */
    public function enrolments(): HasMany
    {
        return $this->hasMany(TrainingEnrolment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<TrainingSession>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where('status', TrainingSessionStatus::Scheduled)->whereDate('start_date', '>=', today());
    }

    /**
     * Cost of one participant: the session override, else the course's cost.
     */
    public function participantCost(): string
    {
        $cost = $this->cost_per_participant ?? $this->course?->cost_per_participant ?? '0';

        return bcadd((string) $cost, '0', 2);
    }

    /**
     * Seats taken (enrolled and finished participants) across the whole company.
     */
    public function seatsTaken(): int
    {
        return TrainingEnrolment::query()->withoutGlobalScopes()->where('training_session_id', $this->id)
            ->whereIn('status', TrainingEnrolmentStatus::seated())->count();
    }

    public function seatsLeft(): int
    {
        return max(0, $this->capacity - $this->seatsTaken());
    }

    public function isScheduled(): bool
    {
        return $this->status === TrainingSessionStatus::Scheduled;
    }

    /**
     * Open for new enrolments: scheduled and not started yet.
     */
    public function acceptsEnrolments(): bool
    {
        return $this->isScheduled() && $this->start_date->gte(today());
    }

    /**
     * Calendar days of the session (attendance is recorded per day).
     *
     * @return list<string> Y-m-d
     */
    public function days(): array
    {
        $days = [];

        foreach (CarbonPeriod::create($this->start_date, $this->end_date) as $day) {
            $days[] = CarbonImmutable::parse($day)->toDateString();
        }

        return $days;
    }

    public function title(): string
    {
        return ($this->course?->name ?? '').' · '.$this->start_date->toDateString();
    }
}
