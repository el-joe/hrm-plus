<?php

namespace App\Models;

use App\Enums\FaceEnrolmentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDataScope;
use Database\Factories\FaceEnrolmentFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A face enrolment. The biometric material is never in this table: the
 * provider-side reference is encrypted, the template / image (when the
 * provider returns one) are encrypted files on the private biometric disk.
 * A deleted enrolment keeps only the fact, dates and reason.
 */
#[Hidden(['provider_reference', 'template_path', 'image_path'])]
class FaceEnrolment extends Model
{
    /** @use HasFactory<FaceEnrolmentFactory> */
    use Auditable, HasDataScope, HasFactory;

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @var list<string> */
    protected array $auditRedact = ['provider_reference', 'template_path', 'image_path'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FaceEnrolmentStatus::class,
            'is_simulated' => 'boolean',
            'provider_reference' => 'encrypted',
            'quality_score' => 'decimal:4',
            'enrolled_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function dataScopeMap(): array
    {
        return [
            'branch' => 'employee.branch_id',
            'department' => 'employee.department_id',
            'employee_group' => 'employee.groups.id',
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
     * @return BelongsTo<FaceConsent, $this>
     */
    public function consent(): BelongsTo
    {
        return $this->belongsTo(FaceConsent::class, 'face_consent_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function isActive(): bool
    {
        return $this->status === FaceEnrolmentStatus::Active;
    }

    public function providerLabel(): string
    {
        $key = 'face.providers.'.$this->provider;

        return trans()->has($key) ? __($key) : $this->provider;
    }
}
