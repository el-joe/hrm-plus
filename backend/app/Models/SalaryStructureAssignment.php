<?php

namespace App\Models;

use App\Enums\SalaryStructureScope;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who a salary structure applies to (employee, job grade, department,
 * branch or company) and when. A new assignment for the same scope closes
 * the open one the day before; rows are never deleted once in force.
 */
#[Fillable(['salary_structure_id', 'scope_type', 'scope_id', 'effective_from', 'effective_to', 'reason', 'created_by'])]
class SalaryStructureAssignment extends Model
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope_type' => SalaryStructureScope::class,
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * @return BelongsTo<SalaryStructure, $this>
     */
    public function structure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeEffectiveOn(Builder $query, string $date): void
    {
        $query->where('effective_from', '<=', $date)
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }

    /**
     * The targeted record (null for the company), outside data scopes.
     */
    public function scopeRecord(): ?Model
    {
        $class = match ($this->scope_type) {
            SalaryStructureScope::Employee => Employee::class,
            SalaryStructureScope::JobGrade => JobGrade::class,
            SalaryStructureScope::Department => Department::class,
            SalaryStructureScope::Branch => Branch::class,
            SalaryStructureScope::Company => null,
        };

        return $class === null || $this->scope_id === null ? null : $class::query()->withoutGlobalScopes()->find($this->scope_id);
    }

    /**
     * "Job grade: G3 · Senior", "Company".
     */
    public function scopeLabel(): string
    {
        $record = $this->scopeRecord();

        if ($record === null) {
            return $this->scope_type->label();
        }

        $name = $record instanceof Employee ? $record->label() : trim(($record->code ?? '').' · '.$record->name, ' ·');

        return $this->scope_type->label().': '.$name;
    }
}
