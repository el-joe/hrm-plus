<?php

namespace App\Models;

use App\Enums\DisciplinaryActionStatus;
use App\Enums\DisciplinaryCaseStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\ScopedThroughEmployee;
use Database\Factories\DisciplinaryCaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A disciplinary incident about an employee: the report, the investigation
 * (notes, evidence, findings) and its outcome (disciplinary_actions).
 * Created and moved only by App\Actions\Disciplinary\*.
 *
 * Restricted: full access needs disciplinary.view inside the data scope, and
 * every view is logged (SensitiveDataAccessLog::DISCIPLINARY). Data scope is
 * the employee's CURRENT placement (ScopedThroughEmployee); branch_id /
 * department_id keep the placement at the time of the report for reports.
 * Narrative fields are audited with their values redacted.
 */
#[Fillable([
    'reference', 'employee_id', 'violation_type_id', 'branch_id', 'department_id', 'incident_date', 'location', 'description',
    'status', 'reported_by', 'investigator_id', 'findings',
])]
class DisciplinaryCase extends Model
{
    /** @use HasFactory<DisciplinaryCaseFactory> */
    use Auditable, HasFactory, ScopedThroughEmployee;

    /**
     * @var list<string>
     */
    protected array $auditRedact = ['description', 'findings', 'closure_note', 'location'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'reported'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DisciplinaryCaseStatus::class,
            'incident_date' => 'date',
            'closed_at' => 'datetime',
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
     * @return BelongsTo<ViolationType, $this>
     */
    public function violationType(): BelongsTo
    {
        return $this->belongsTo(ViolationType::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function investigator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investigator_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by')->withTrashed();
    }

    /**
     * @return HasMany<DisciplinaryCaseNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(DisciplinaryCaseNote::class)->latest('id');
    }

    /**
     * @return HasMany<DisciplinaryEvidence, $this>
     */
    public function evidence(): HasMany
    {
        return $this->hasMany(DisciplinaryEvidence::class)->latest('id');
    }

    /**
     * @return HasMany<DisciplinaryAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(DisciplinaryAction::class)->latest('id');
    }

    /**
     * @return HasOne<DisciplinaryAction, $this>
     */
    public function latestAction(): HasOne
    {
        return $this->hasOne(DisciplinaryAction::class)->latestOfMany();
    }

    /**
     * The approved (issued) action of the case, if any.
     */
    public function issuedAction(): ?DisciplinaryAction
    {
        return $this->actions()->where('status', DisciplinaryActionStatus::Approved)->first();
    }

    public function pendingAction(): ?DisciplinaryAction
    {
        return $this->actions()->where('status', DisciplinaryActionStatus::PendingApproval)->first();
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }
}
