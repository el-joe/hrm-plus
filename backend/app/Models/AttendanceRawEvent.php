<?php

namespace App\Models;

use App\Enums\PunchDirection;
use App\Enums\RawEventKind;
use App\Enums\RawEventSource;
use App\Enums\RawEventStatus;
use App\Services\Authorization\DataScopeManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An attendance input exactly as received, from any source. Immutable: it
 * is only ever voided (voided_at), never edited or deleted. Created ONLY
 * through App\Services\Attendance\RawEventRecorder, which enforces the
 * idempotency key and triggers processing.
 *
 * Unmatched events (employee_id null) are visible to admins whatever their
 * data scope restricts, because they belong to nobody yet; scoped admins see
 * matched events of employees in their scope.
 */
class AttendanceRawEvent extends Model
{
    /**
     * Mass assignment is closed: RawEventRecorder sets attributes explicitly.
     *
     * @var list<string>
     */
    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::addGlobalScope('data_scope', function (Builder $query): void {
            $manager = app(DataScopeManager::class);

            if (($admin = $manager->activeAdmin()) && $admin->hasDataRestrictions()) {
                $query->where(fn (Builder $query) => $query->whereNull($query->qualifyColumn('employee_id'))
                    ->orWhereHas('employee'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => RawEventKind::class,
            'source' => RawEventSource::class,
            'status' => RawEventStatus::class,
            'direction' => PunchDirection::class,
            'occurred_at' => 'datetime',
            'work_date' => 'date',
            'payload' => 'array',
            'processed_at' => 'datetime',
            'voided_at' => 'datetime',
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
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @return BelongsTo<AttendanceStatus, $this>
     */
    public function attendanceStatus(): BelongsTo
    {
        return $this->belongsTo(AttendanceStatus::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function origin(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasOne<AttendancePunch, $this>
     */
    public function punch(): HasOne
    {
        return $this->hasOne(AttendancePunch::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeNotVoided(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('voided_at'));
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function isManual(): bool
    {
        return in_array($this->source, [RawEventSource::Manual, RawEventSource::Import], true);
    }
}
