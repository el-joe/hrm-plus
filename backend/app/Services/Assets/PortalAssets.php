<?php

namespace App\Services\Assets;

use App\Models\AssetAssignment;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;

/**
 * The employee's own custody for the portal and the mobile API: always the
 * signed-in employee's assignments, admin data scopes ignored.
 */
class PortalAssets
{
    /**
     * @return Builder<AssetAssignment>
     */
    public function assignments(Employee $employee): Builder
    {
        return AssetAssignment::query()->withoutGlobalScopes()->where('employee_id', $employee->id)
            ->with(['asset' => fn ($query) => $query->withoutGlobalScopes()->with('category')])
            ->orderByRaw('case when status = ? then 0 else 1 end', ['active'])
            ->orderByDesc('assigned_on')->orderByDesc('id');
    }

    public function find(Employee $employee, int $id): ?AssetAssignment
    {
        return $this->assignments($employee)->find($id);
    }
}
