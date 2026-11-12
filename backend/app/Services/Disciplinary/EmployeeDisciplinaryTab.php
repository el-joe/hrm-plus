<?php

namespace App\Services\Disciplinary;

use App\Models\DisciplinaryCase;
use App\Models\Employee;
use App\Models\User;
use App\Services\Employees\Contracts\ProfileTab;
use App\Services\Employees\SensitiveDataAccessLog;

/**
 * Employee profile tab "Disciplinary" (restricted): the employee's cases and
 * active warnings, for disciplinary.view holders only (never about yourself).
 * Opening the tab is logged.
 */
class EmployeeDisciplinaryTab implements ProfileTab
{
    public function __construct(private readonly WarningEscalation $escalation, private readonly SensitiveDataAccessLog $accessLog) {}

    public function visibleTo(User $user, Employee $employee): bool
    {
        return $user->can('disciplinary.view') && $user->employee_id !== $employee->id;
    }

    public function data(User $user, Employee $employee): array
    {
        $this->accessLog->record(SensitiveDataAccessLog::DISCIPLINARY, $employee, ['cases', 'active_warnings'], ['tab' => 'disciplinary'], $user);

        return [
            'disciplinaryCases' => DisciplinaryCase::query()->where('employee_id', $employee->id)
                ->with(['violationType', 'latestAction'])->latest('incident_date')->latest('id')->get(),
            'activeWarnings' => $this->escalation->activeWarnings($employee),
        ];
    }
}
