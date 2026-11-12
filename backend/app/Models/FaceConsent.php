<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\FaceConsentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An employee's consent to biometric face verification, for one version of
 * the consent text. Withdrawing deletes the biometric data
 * (App\Services\Attendance\Face\FaceEnrolmentService).
 */
class FaceConsent extends Model
{
    /** @use HasFactory<FaceConsentFactory> */
    use Auditable, HasFactory;

    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['given_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isActive(): bool
    {
        return $this->withdrawn_at === null;
    }
}
