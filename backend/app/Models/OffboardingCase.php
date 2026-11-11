<?php

namespace App\Models;

use App\Enums\OffboardingSource;
use App\Enums\OffboardingStatus;
use App\Enums\TerminationType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDataScope;
use App\Models\Scopes\DataScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An employee's exit: type (resignation, dismissal, contract end, ...), the
 * exit interview and the handover checklist. Completing it terminates the
 * employee on last_working_date (App\Actions\Offboarding\CompleteOffboarding).
 */
#[Fillable([
    'employee_id', 'type', 'source', 'employee_contract_id', 'notice_date', 'last_working_date', 'reason',
    'eligible_for_rehire', 'status', 'created_by',
])]
class OffboardingCase extends Model
{
    use Auditable, HasDataScope;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TerminationType::class,
            'source' => OffboardingSource::class,
            'status' => OffboardingStatus::class,
            'notice_date' => 'date',
            'last_working_date' => 'date',
            'eligible_for_rehire' => 'boolean',
            'exit_interview_at' => 'datetime',
            'exit_interview_answers' => 'array',
            'completed_at' => 'datetime',
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

    public function isOpen(): bool
    {
        return $this->status === OffboardingStatus::Open;
    }

    public function progress(): int
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        if ($items->isEmpty()) {
            return 100;
        }

        return intdiv($items->filter(fn (OffboardingItem $item): bool => $item->status->isFinished())->count() * 100, $items->count());
    }

    public function hasPendingItems(): bool
    {
        return $this->items()->where('status', 'pending')->exists();
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withoutGlobalScope(DataScope::class);
    }

    /**
     * @return BelongsTo<EmployeeContract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(EmployeeContract::class, 'employee_contract_id');
    }

    /**
     * @return HasMany<OffboardingItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OffboardingItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function exitInterviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exit_interviewed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
