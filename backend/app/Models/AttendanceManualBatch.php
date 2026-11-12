<?php

namespace App\Models;

use App\Enums\ManualBatchStatus;
use App\Enums\RawEventSource;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasApprovals;
use App\Models\Concerns\HasDataScope;
use Database\Factories\AttendanceManualBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A bulk "mark all present except" batch or an Excel import. Created as a
 * draft, confirmed on the confirmation screen, then applied (or sent for
 * approval first, workflow "attendance_manual_batch"). Applying writes one
 * raw event per punch / day mark, source manual or import.
 */
#[Fillable(['source', 'work_date', 'filters', 'summary', 'file_name', 'note', 'created_by'])]
class AttendanceManualBatch extends Model
{
    /** @use HasFactory<AttendanceManualBatchFactory> */
    use Auditable, HasApprovals, HasDataScope, HasFactory;

    public const WORKFLOW = 'attendance_manual_batch';

    /**
     * Column defaults, so a new instance knows its status before it is reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'draft'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => RawEventSource::class,
            'status' => ManualBatchStatus::class,
            'work_date' => 'date',
            'filters' => 'array',
            'summary' => 'array',
            'submitted_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    /**
     * Visible when any of its employees is in the admin's scope.
     *
     * @return array<string, string>
     */
    public function dataScopeMap(): array
    {
        return [
            'branch' => 'entries.employee.branch_id',
            'department' => 'entries.employee.department_id',
            'employee_group' => 'entries.employee.groups.id',
        ];
    }

    /**
     * @return HasMany<AttendanceManualEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(AttendanceManualEntry::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === ManualBatchStatus::Draft;
    }

    public function reference(): string
    {
        return ($this->source === RawEventSource::Import ? 'IMP-' : 'BLK-').str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT);
    }
}
