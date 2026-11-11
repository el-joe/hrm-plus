<?php

namespace App\Models;

use App\Enums\CustomFieldType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use App\Models\Contracts\GuardsDeletion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Definition of an admin-defined employee attribute (typed). Values live in
 * employee_custom_field_values, one typed column per value kind.
 */
#[Fillable(['code', 'name_ar', 'name_en', 'type', 'is_required', 'visible_to_employee', 'sort_order', 'is_active'])]
class EmployeeCustomField extends Model implements GuardsDeletion
{
    use Auditable, HasActiveFlag, HasLocalizedName;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CustomFieldType::class,
            'is_required' => 'boolean',
            'visible_to_employee' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<EmployeeCustomFieldOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(EmployeeCustomFieldOption::class, 'custom_field_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<EmployeeCustomFieldValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(EmployeeCustomFieldValue::class, 'custom_field_id');
    }

    /**
     * @return array<string, int>
     */
    public function usageCounts(): array
    {
        return ['employees' => $this->values()->count()];
    }
}
