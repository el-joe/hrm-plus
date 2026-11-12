<?php

namespace App\Services\Employees\Contracts;

use App\Models\Employee;
use App\Models\User;

/**
 * A module tab of the admin employee profile (Phase 8a: performance, training,
 * assets, disciplinary). Registered in App\Services\Employees\EmployeeProfile::MODULE_TABS;
 * the view is admin/employees/tabs/{tab}.blade.php and receives what data() returns.
 */
interface ProfileTab
{
    /**
     * Whether the admin may open the tab for this employee (the route aborts with 403 otherwise).
     */
    public function visibleTo(User $user, Employee $employee): bool;

    /**
     * View data of the tab. Log sensitive access here when the module requires it.
     *
     * @return array<string, mixed>
     */
    public function data(User $user, Employee $employee): array;
}
