<?php

namespace App\Services\Recruitment;

use App\Models\User;
use App\Services\Employees\SensitiveDataAccessLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Salary data in recruitment (vacancy salary range, a candidate's expected
 * and current salary, the offered salary) is shown and accepted only for
 * holders of employee_financials.view. Each time it is shown, the
 * sensitive-data access log records who saw which fields of which record.
 */
class SalaryVisibility
{
    public function __construct(private readonly SensitiveDataAccessLog $log) {}

    public function allows(?User $user): bool
    {
        return $user !== null && $user->isAdmin() && $user->can('employee_financials.view');
    }

    /**
     * Whether the user may see the salary fields; logs the access when yes.
     *
     * @param  list<string>  $fields
     */
    public function reveal(?User $user, Model $record, array $fields): bool
    {
        if (! $this->allows($user)) {
            return false;
        }

        $this->log->record(SensitiveDataAccessLog::RECRUITMENT_SALARY, $record, $fields, viewer: $user);

        return true;
    }
}
