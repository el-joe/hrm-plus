<?php

namespace App\Models;

use App\Enums\FaceVerificationResult;
use App\Enums\PunchDirection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The outcome of one face verification (no image, no template): result,
 * scores, liveness, attempt number. Purged after the retention period.
 */
class FaceVerification extends Model
{
    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => PunchDirection::class,
            'result' => FaceVerificationResult::class,
            'is_simulated' => 'boolean',
            'score' => 'decimal:4',
            'liveness_passed' => 'boolean',
            'liveness_score' => 'decimal:4',
            'attempt_number' => 'integer',
            'work_date' => 'date',
            'verified_at' => 'datetime',
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
     * @return BelongsTo<AttendanceMobileAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AttendanceMobileAttempt::class, 'attendance_mobile_attempt_id');
    }
}
