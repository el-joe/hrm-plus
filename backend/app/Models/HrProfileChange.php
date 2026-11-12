<?php

namespace App\Models;

use App\Enums\HrProfileChangeStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\HrProfileChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One field of a profile change request: the value when requested and the value asked for, both
 * encrypted at rest (identity numbers, bank details). Applied by App\Actions\HrRequests\DecideProfileChanges
 * through the employee actions. Audited with the values redacted.
 */
#[Fillable(['hr_request_id', 'field', 'old_value', 'new_value', 'status', 'decided_at', 'decided_by'])]
#[Hidden(['old_value', 'new_value'])]
class HrProfileChange extends Model
{
    /** @use HasFactory<HrProfileChangeFactory> */
    use Auditable, HasFactory;

    /**
     * @var list<string>
     */
    protected array $auditRedact = ['old_value', 'new_value'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_value' => 'encrypted',
            'new_value' => 'encrypted',
            'status' => HrProfileChangeStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<HrRequest, $this>
     */
    public function hrRequest(): BelongsTo
    {
        return $this->belongsTo(HrRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by')->withTrashed();
    }
}
