<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\TrainingBudgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Training budget of a year for a department, or company-wide (department_id null).
 * Spent figures are computed by App\Services\Training\TrainingBudgets from the
 * enrolment cost snapshots; nothing is stored.
 */
#[Fillable(['year', 'department_id', 'amount', 'notes', 'created_by'])]
class TrainingBudget extends Model
{
    /** @use HasFactory<TrainingBudgetFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['year' => 'integer', 'amount' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function isCompanyWide(): bool
    {
        return $this->department_id === null;
    }
}
