<?php

namespace App\Models;

use App\Enums\DataScopeType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use App\Models\Contracts\GuardsDeletion;
use Database\Factories\EmployeeGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A free grouping of employees across branches and departments (e.g. "Field
 * staff", "Night shift pool"). Admin accounts can be restricted to groups.
 */
#[Fillable(['code', 'name_ar', 'name_en', 'description', 'is_active'])]
class EmployeeGroup extends Model implements GuardsDeletion
{
    /** @use HasFactory<EmployeeGroupFactory> */
    use Auditable, HasActiveFlag, HasFactory, HasLocalizedName;

    /**
     * @return BelongsToMany<Employee, $this>
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_group_members');
    }

    /**
     * @return array<string, int>
     */
    public function usageCounts(): array
    {
        return [
            'employees' => $this->employees()->withoutGlobalScopes()->count(),
            'admin_scopes' => AdminDataScope::query()
                ->where('scope_type', DataScopeType::EmployeeGroup)->where('scope_id', $this->getKey())->count(),
        ];
    }
}
