<?php

namespace App\Models;

use App\Enums\SalaryChangeType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One effective-dated basic salary (DECIMAL, bcmath only). Sensitive: shown
 * with employee_financials.view only, and every view is logged.
 */
#[Fillable(['employee_id', 'change_type', 'effective_date', 'basic_salary', 'previous_salary', 'currency', 'reason', 'applied_at', 'cancelled_at', 'created_by'])]
class EmployeeSalary extends Model
{
    use Auditable;

    /**
     * @var list<string>
     */
    protected array $auditRedact = ['basic_salary', 'previous_salary'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'change_type' => SalaryChangeType::class,
            'effective_date' => 'date',
            'basic_salary' => 'decimal:2',
            'previous_salary' => 'decimal:2',
            'applied_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('applied_at')->whereNull('cancelled_at');
    }

    public function isPending(): bool
    {
        return $this->applied_at === null && $this->cancelled_at === null;
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
