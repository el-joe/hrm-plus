<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use App\Models\Contracts\GuardsDeletion;
use Database\Factories\EmploymentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Full-time, part-time, fixed-term contract, internship, ... Phase 3b links
 * employees (and Phase 6 leave policies) to it.
 */
#[Fillable(['code', 'name_ar', 'name_en', 'is_fixed_term', 'is_active'])]
class EmploymentType extends Model implements GuardsDeletion
{
    /** @use HasFactory<EmploymentTypeFactory> */
    use Auditable, HasActiveFlag, HasFactory, HasLocalizedName;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_fixed_term' => 'boolean',
        ];
    }

    /**
     * @return array<string, int>
     */
    public function usageCounts(): array
    {
        return [
            'employees' => Employee::query()->withoutGlobalScopes()->where('employment_type_id', $this->getKey())->count(),
        ];
    }
}
