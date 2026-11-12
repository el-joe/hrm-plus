<?php

namespace App\Models;

use App\Enums\TrainingEnrolmentSource;
use App\Enums\TrainingEnrolmentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasApprovals;
use App\Models\Concerns\ScopedThroughEmployee;
use Database\Factories\TrainingEnrolmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An employee's place on a training session. Nominations by HR are enrolled at
 * once; nominations by managers and employees' own requests go through the
 * "training_enrolment" approval workflow. cost / branch_id / department_id are
 * snapshots taken when the enrolment was created (budgets and reports use them).
 * See App\Actions\Training\ManageTrainingEnrolment.
 */
#[Fillable([
    'training_session_id', 'employee_id', 'source', 'status', 'cost', 'branch_id', 'department_id', 'reason', 'requested_by',
])]
class TrainingEnrolment extends Model
{
    /** @use HasFactory<TrainingEnrolmentFactory> */
    use Auditable, HasApprovals, HasFactory, ScopedThroughEmployee;

    public const WORKFLOW = 'training_enrolment';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => TrainingEnrolmentSource::class,
            'status' => TrainingEnrolmentStatus::class,
            'cost' => 'decimal:2',
            'score' => 'decimal:2',
            'decided_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TrainingSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<TrainingAttendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(TrainingAttendance::class)->orderBy('date');
    }

    /**
     * @return HasOne<TrainingCertificate, $this>
     */
    public function certificate(): HasOne
    {
        return $this->hasOne(TrainingCertificate::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === TrainingEnrolmentStatus::Pending;
    }

    public function isEnrolled(): bool
    {
        return $this->status === TrainingEnrolmentStatus::Enrolled;
    }

    /**
     * Attended days over the session's days, as a percentage string (bcmath, 2 decimals).
     */
    public function attendanceRate(?int $sessionDays = null): string
    {
        $sessionDays ??= count($this->session->days());

        if ($sessionDays === 0) {
            return '0.00';
        }

        $attended = $this->attendances->filter(fn (TrainingAttendance $row): bool => $row->status->attended())->count();

        return bcdiv(bcmul((string) $attended, '100', 4), (string) $sessionDays, 2);
    }
}
