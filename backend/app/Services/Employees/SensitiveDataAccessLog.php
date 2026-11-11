<?php

namespace App\Services\Employees;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * The sensitive-data access log: every time an admin (or the API) is shown
 * protected employee data, a "sensitive_access.*" audit event is written with
 * who, when, which record and which fields — never the values themselves.
 * Filter the audit log by these events to review access.
 */
class SensitiveDataAccessLog
{
    public const IDENTITY = 'sensitive_access.employee_identity';

    public const FINANCIAL = 'sensitive_access.employee_financial';

    public const DOCUMENT = 'sensitive_access.employee_document';

    public const CONTRACT = 'sensitive_access.employee_contract';

    public const EXPORT = 'sensitive_access.employee_export';

    /** Recruitment salaries: vacancy range, candidate expected / current, offered salary. */
    public const RECRUITMENT_SALARY = 'sensitive_access.recruitment_salary';

    public const CANDIDATE_FILE = 'sensitive_access.candidate_file';

    /** Payslips (Phase 7b): every view / PDF download by an admin, the employee or the mobile app. */
    public const PAYSLIP = 'sensitive_access.payslip';

    /** Payroll run employee breakdowns and payroll exports (Phase 7b). */
    public const PAYROLL = 'sensitive_access.payroll';

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<string>  $fields  names of the protected fields that were shown
     * @param  array<string, mixed>  $meta
     */
    public function record(string $event, ?Model $subject, array $fields = [], array $meta = [], ?User $viewer = null): void
    {
        $this->audit->log($event, $subject, meta: array_filter(['fields' => $fields, ...$meta], fn ($value) => $value !== [] && $value !== null), actor: $viewer);
    }
}
