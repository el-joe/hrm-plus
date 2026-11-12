<?php

namespace App\Services\Disciplinary;

use App\Models\DisciplinaryAction;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;

/**
 * The employee's own disciplinary notices (portal): only actions that were
 * approved and issued to them — never pending proposals, investigation notes,
 * evidence or who reported the incident. Admin data scopes are ignored.
 */
class PortalDisciplinary
{
    /**
     * @return Builder<DisciplinaryAction>
     */
    public function notices(Employee $employee): Builder
    {
        return DisciplinaryAction::query()->withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereNotNull('issued_at')
            ->with(['disciplinaryCase' => fn ($query) => $query->withoutGlobalScopes()->with('violationType')])
            ->latest('issued_at')->latest('id');
    }

    public function find(Employee $employee, int $id): ?DisciplinaryAction
    {
        return $this->notices($employee)->find($id);
    }

    public function awaitingResponse(Employee $employee): int
    {
        return $this->notices($employee)->whereNull('responded_at')->count();
    }
}
