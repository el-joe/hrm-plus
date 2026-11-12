<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\EmployeeEmergencyContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_id', 'name', 'relationship', 'phone', 'alternate_phone', 'sort_order'])]
class EmployeeEmergencyContact extends Model
{
    /** @use HasFactory<EmployeeEmergencyContactFactory> */
    use Auditable, HasFactory;

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
