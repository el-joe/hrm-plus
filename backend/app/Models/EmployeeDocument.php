<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDataScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A file attached to an employee (ID copy, certificate, CV, ...). Stored on
 * the private disk (config('employees.disk')), never under public/; served
 * only by the authorised download controllers, each download logged.
 * Deleting is a soft delete: the file is kept for the record.
 */
#[Fillable([
    'employee_id', 'category', 'title', 'issue_date', 'expiry_date', 'visible_to_employee', 'notes',
    'file_path', 'file_name', 'file_mime', 'file_size', 'uploaded_by',
])]
class EmployeeDocument extends Model
{
    use Auditable, HasDataScope, SoftDeletes;

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
            'category' => DocumentCategory::class,
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'expiry_reminded_for' => 'date',
            'visible_to_employee' => 'boolean',
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
     * @param  Builder<static>  $query
     */
    public function scopeVisibleToEmployee(Builder $query): void
    {
        $query->where('visible_to_employee', true);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->lt(today());
    }

    public function expiresWithin(int $days): bool
    {
        return $this->expiry_date !== null && ! $this->isExpired() && $this->expiry_date->lte(today()->addDays($days));
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
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
