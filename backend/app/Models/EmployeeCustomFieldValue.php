<?php

namespace App\Models;

use App\Enums\CustomFieldType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_id', 'custom_field_id', 'value_text', 'value_number', 'value_date', 'value_boolean'])]
class EmployeeCustomFieldValue extends Model
{
    use Auditable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_number' => 'decimal:4',
            'value_date' => 'date',
            'value_boolean' => 'boolean',
        ];
    }

    /**
     * The stored value in the column of the field's type.
     */
    public function value(): mixed
    {
        return $this->getAttribute(($this->field?->type ?? CustomFieldType::Text)->valueColumn());
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<EmployeeCustomField, $this>
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(EmployeeCustomField::class, 'custom_field_id');
    }
}
