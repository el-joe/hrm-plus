<?php

namespace App\Models;

use App\Enums\DisciplinaryActionStatus;
use App\Enums\DisciplinaryActionType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasApprovals;
use App\Models\Concerns\ScopedThroughEmployee;
use Database\Factories\DisciplinaryActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The proposed / decided outcome of a disciplinary case, approved through the
 * "disciplinary_action" workflow (App\Actions\Disciplinary\ManageDisciplinaryAction).
 * Once approved it is issued to the employee (portal "Disciplinary notices"),
 * who may acknowledge it and respond. A salary deduction creates a pending
 * deduction pay entry (pay_entries.source = this action, pay_entry_id here).
 *
 * Active warning = approved, warning_level set, expires_on >= today
 * (App\Services\Disciplinary\WarningEscalation). Narrative and amount fields
 * are audited with their values redacted.
 */
#[Fillable([
    'disciplinary_case_id', 'employee_id', 'type', 'warning_level', 'suggested_level', 'details', 'effective_date', 'expires_on',
    'suspension_start', 'suspension_end', 'suspension_paid', 'deduction_amount', 'proposed_by',
])]
class DisciplinaryAction extends Model
{
    /** @use HasFactory<DisciplinaryActionFactory> */
    use Auditable, HasApprovals, HasFactory, ScopedThroughEmployee;

    public const WORKFLOW = 'disciplinary_action';

    /**
     * @var list<string>
     */
    protected array $auditRedact = ['details', 'employee_response', 'deduction_amount', 'rejection_reason'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'pending_approval'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DisciplinaryActionType::class,
            'status' => DisciplinaryActionStatus::class,
            'warning_level' => 'integer',
            'suggested_level' => 'integer',
            'effective_date' => 'date',
            'expires_on' => 'date',
            'suspension_start' => 'date',
            'suspension_end' => 'date',
            'suspension_paid' => 'boolean',
            'deduction_amount' => 'decimal:2',
            'decided_at' => 'datetime',
            'issued_at' => 'datetime',
            'response_due_on' => 'date',
            'responded_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DisciplinaryCase, $this>
     */
    public function disciplinaryCase(): BelongsTo
    {
        return $this->belongsTo(DisciplinaryCase::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<PayEntry, $this>
     */
    public function payEntry(): BelongsTo
    {
        return $this->belongsTo(PayEntry::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by')->withTrashed();
    }

    /**
     * Approved warnings still counting for escalation on the given day.
     *
     * @param  Builder<DisciplinaryAction>  $query
     */
    public function scopeActiveWarnings(Builder $query, ?string $onDate = null): void
    {
        $query->where('status', DisciplinaryActionStatus::Approved)
            ->whereNotNull('warning_level')
            ->whereDate('expires_on', '>=', $onDate ?? now()->toDateString());
    }

    public function isPending(): bool
    {
        return $this->status === DisciplinaryActionStatus::PendingApproval;
    }

    public function isIssued(): bool
    {
        return $this->status === DisciplinaryActionStatus::Approved && $this->issued_at !== null;
    }

    public function isActiveWarning(): bool
    {
        return $this->status === DisciplinaryActionStatus::Approved && $this->warning_level !== null
            && $this->expires_on !== null && $this->expires_on->toDateString() >= now()->toDateString();
    }

    public function hasResponded(): bool
    {
        return $this->responded_at !== null;
    }
}
