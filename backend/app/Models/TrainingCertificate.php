<?php

namespace App\Models;

use App\Enums\CertificateType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\ScopedThroughEmployee;
use Database\Factories\TrainingCertificateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A certificate, licence or qualification of an employee — issued by completing a
 * course or recorded / uploaded by HR or the employee. The file lives on the private
 * disk "training_files" (App\Services\Training\TrainingFileStore) and is only ever
 * streamed after an authorisation check. expires_on drives the expiry reminders.
 */
#[Fillable([
    'employee_id', 'training_enrolment_id', 'training_course_id', 'type', 'name', 'issuer', 'certificate_number',
    'issued_on', 'expires_on', 'file_path', 'file_name', 'file_mime', 'file_size', 'uploaded_by',
])]
class TrainingCertificate extends Model
{
    /** @use HasFactory<TrainingCertificateFactory> */
    use Auditable, HasFactory, ScopedThroughEmployee;

    /**
     * @var list<string>
     */
    protected array $auditExclude = ['reminder_sent_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CertificateType::class,
            'issued_on' => 'date',
            'expires_on' => 'date',
            'file_size' => 'integer',
            'reminder_sent_at' => 'datetime',
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
     * @return BelongsTo<TrainingCourse, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'training_course_id');
    }

    /**
     * @return BelongsTo<TrainingEnrolment, $this>
     */
    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(TrainingEnrolment::class, 'training_enrolment_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Valid certificates expiring within the next $days days (today included).
     *
     * @param  Builder<TrainingCertificate>  $query
     */
    public function scopeExpiringWithin(Builder $query, int $days): void
    {
        $query->whereNotNull('expires_on')->whereDate('expires_on', '>=', today())->whereDate('expires_on', '<=', today()->addDays($days));
    }

    /**
     * @param  Builder<TrainingCertificate>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->whereNotNull('expires_on')->whereDate('expires_on', '<', today());
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->lt(today());
    }

    public function isExpiringWithin(int $days): bool
    {
        return $this->expires_on !== null && ! $this->isExpired() && $this->expires_on->lte(today()->addDays($days));
    }

    public function hasFile(): bool
    {
        return $this->file_path !== null;
    }

    /**
     * valid | expiring | expired | no_expiry — for badges.
     */
    public function validityState(?int $reminderDays = null): string
    {
        $reminderDays ??= (int) setting('training.certificate_reminder_days');

        return match (true) {
            $this->expires_on === null => 'no_expiry',
            $this->isExpired() => 'expired',
            $this->isExpiringWithin($reminderDays) => 'expiring',
            default => 'valid',
        };
    }
}
