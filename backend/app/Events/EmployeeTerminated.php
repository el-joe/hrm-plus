<?php

namespace App\Events;

use App\Models\Employee;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a termination takes effect (today, back-dated, or on its
 * scheduled date), after the linked logins were switched off. Modules
 * listen for it to release what the employee no longer needs (e.g. biometric data).
 */
class EmployeeTerminated
{
    use Dispatchable;

    public function __construct(public Employee $employee) {}
}
