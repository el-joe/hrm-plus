<?php

namespace App\Models;

use App\Enums\ContractType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDataScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * An employment contract. A renewal is a new row pointing at the contract it
 * renews (renewal_of_id); the old contract is never changed. The signed copy
 * is kept on the private disk and only served by an authorised controller.
 */
#[Fillable([
    'employee_id', 'reference', 'contract_type', 'start_date', 'end_date', 'renewal_of_id', 'terminated_on', 'notes',
    'file_path', 'file_name', 'file_mime', 'file_size', 'created_by',
])]
class EmployeeContract extends Model
{
    use Auditable, HasDataScope;

    /**
     * @var list<string>
     */
    protected array $auditExclude = ['expiry_reminded_for'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contract_type' => ContractType::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'terminated_on' => 'date',
            'expiry_reminded_for' => 'date',
            'file_size' => 'integer',
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
     * The date the contract stops (termination wins over the planned end).
     */
    public function effectiveEndDate(): ?Carbon
    {
        return $this->terminated_on ?? $this->end_date;
    }

    public function isCurrent(?Carbon $on = null): bool
    {
        $on ??= today();
        $end = $this->effectiveEndDate();

        return $this->start_date->lte($on) && ($end === null || $end->gte($on));
    }

    /**
     * active | upcoming | expired | terminated, for the status badge.
     */
    public function state(): string
    {
        return match (true) {
            $this->terminated_on !== null && $this->terminated_on->lt(today()) => 'terminated',
            $this->start_date->isFuture() => 'upcoming',
            $this->isCurrent() => 'active',
            default => 'expired',
        };
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<EmployeeContract, $this>
     */
    public function renewalOf(): BelongsTo
    {
        return $this->belongsTo(EmployeeContract::class, 'renewal_of_id');
    }

    /**
     * @return HasOne<EmployeeContract, $this>
     */
    public function renewedBy(): HasOne
    {
        return $this->hasOne(EmployeeContract::class, 'renewal_of_id');
    }
}
