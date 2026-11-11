<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasDataScope;
use App\Models\Concerns\HasLocalizedName;
use App\Models\Contracts\GuardsDeletion;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A team inside a department. Scoped through its department.
 */
#[Fillable(['code', 'name_ar', 'name_en', 'department_id', 'leader_employee_id', 'description', 'is_active'])]
class Team extends Model implements GuardsDeletion
{
    /** @use HasFactory<TeamFactory> */
    use Auditable, HasActiveFlag, HasDataScope, HasFactory, HasLocalizedName;

    /**
     * @return array<string, string>
     */
    public function dataScopeMap(): array
    {
        return [
            'branch' => 'department.branch_id',
            'department' => 'department_id',
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The employee leading the team (optional).
     *
     * @return BelongsTo<Employee, $this>
     */
    public function leader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'leader_employee_id');
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * @return array<string, int>
     */
    public function usageCounts(): array
    {
        return [
            'employees' => Employee::query()->withoutGlobalScopes()->where('team_id', $this->getKey())->count(),
        ];
    }
}
