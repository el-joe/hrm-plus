<?php

namespace App\Services\Employees;

use App\Enums\AccountType;
use App\Enums\AssignmentChangeType;
use App\Enums\EmployeeStatus;
use App\Enums\SalaryChangeType;
use App\Enums\UserStatus;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeSalary;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of an employee's job placement and basic salary.
 *
 * Every change (hire, transfer, promotion, manager change, status change,
 * termination, rehire, salary change) is a NEW effective-dated row; nothing
 * is overwritten. The employee's current columns are then re-derived from the
 * latest applied row (by effective date), so a back-dated change never
 * replaces a newer one.
 *
 * Rows dated in the future are stored as pending and applied on their date by
 * `php artisan employees:apply-scheduled-changes` (scheduled daily). Pending
 * rows can be cancelled; applied rows are permanent.
 */
class EmploymentHistory
{
    /**
     * The opening "hire" row of a new employee: their initial placement, applied at once.
     */
    public function recordHire(Employee $employee, ?string $reason = null): EmployeeAssignment
    {
        return $employee->assignments()->create([
            ...$this->currentPlacement($employee),
            'change_type' => AssignmentChangeType::Hire,
            'effective_date' => $employee->hire_date->toDateString(),
            'reason' => $reason,
            'applied_at' => now(),
            'created_by' => $this->actorId(),
        ]);
    }

    /**
     * Record a placement change.
     *
     * @param  array<string, mixed>  $changes  placement columns (Employee::PLACEMENT_COLUMNS) that change; others are carried over
     * @param  array{termination_type?: string|null, eligible_for_rehire?: bool|null}  $termination
     */
    public function recordAssignment(
        Employee $employee,
        AssignmentChangeType $type,
        array $changes,
        CarbonInterface $effectiveDate,
        ?string $reason = null,
        array $termination = [],
    ): EmployeeAssignment {
        return DB::transaction(function () use ($employee, $type, $changes, $effectiveDate, $reason, $termination): EmployeeAssignment {
            $placement = array_merge(
                $this->currentPlacement($employee),
                array_intersect_key($changes, array_flip(Employee::PLACEMENT_COLUMNS)),
            );

            $assignment = $employee->assignments()->create([
                ...$placement,
                'status' => $placement['status'] instanceof EmployeeStatus ? $placement['status']->value : $placement['status'],
                'change_type' => $type,
                'effective_date' => $effectiveDate->toDateString(),
                'reason' => $reason,
                'termination_type' => $termination['termination_type'] ?? null,
                'eligible_for_rehire' => $termination['eligible_for_rehire'] ?? null,
                'created_by' => $this->actorId(),
            ]);

            if ($this->isDue($effectiveDate)) {
                $this->applyAssignment($assignment);
                $employee->refresh();
            }

            return $assignment;
        });
    }

    /**
     * Record a basic salary change (DECIMAL string, bcmath compared).
     */
    public function recordSalary(
        Employee $employee,
        string $amount,
        SalaryChangeType $type,
        CarbonInterface $effectiveDate,
        ?string $reason = null,
        bool $applyNow = false,
    ): EmployeeSalary {
        return DB::transaction(function () use ($employee, $amount, $type, $effectiveDate, $reason, $applyNow): EmployeeSalary {
            $salary = $employee->salaries()->create([
                'change_type' => $type,
                'effective_date' => $effectiveDate->toDateString(),
                'basic_salary' => bcadd($amount, '0', 2),
                'previous_salary' => $employee->basic_salary,
                'currency' => (string) setting('localisation.currency_code'),
                'reason' => $reason,
                'created_by' => $this->actorId(),
            ]);

            if ($applyNow || $this->isDue($effectiveDate)) {
                $this->applySalary($salary);
                $employee->refresh();
            }

            return $salary;
        });
    }

    public function applyAssignment(EmployeeAssignment $assignment): void
    {
        DB::transaction(function () use ($assignment): void {
            $assignment->update(['applied_at' => now()]);
            $this->syncCurrentPlacement($assignment->employee()->withoutGlobalScopes()->firstOrFail(), $assignment);
        });
    }

    public function applySalary(EmployeeSalary $salary): void
    {
        DB::transaction(function () use ($salary): void {
            $salary->update(['applied_at' => now()]);
            $this->syncCurrentSalary($salary->employee()->withoutGlobalScopes()->firstOrFail());
        });
    }

    /**
     * Apply every pending row whose effective date has arrived, oldest first.
     *
     * @return array{assignments: int, salaries: int}
     */
    public function applyDue(?CarbonInterface $on = null): array
    {
        $on ??= today();
        $counts = ['assignments' => 0, 'salaries' => 0];

        EmployeeAssignment::query()->pending()->whereDate('effective_date', '<=', $on)
            ->orderBy('effective_date')->orderBy('id')
            ->each(function (EmployeeAssignment $assignment) use (&$counts): void {
                $this->applyAssignment($assignment);
                $counts['assignments']++;
            });

        EmployeeSalary::query()->pending()->whereDate('effective_date', '<=', $on)
            ->orderBy('effective_date')->orderBy('id')
            ->each(function (EmployeeSalary $salary) use (&$counts): void {
                $this->applySalary($salary);
                $counts['salaries']++;
            });

        return $counts;
    }

    public function cancel(EmployeeAssignment|EmployeeSalary $row): void
    {
        if ($row->isPending()) {
            $row->update(['cancelled_at' => now()]);
        }
    }

    /**
     * The employee's placement as a column => value array.
     *
     * @return array<string, mixed>
     */
    public function currentPlacement(Employee $employee): array
    {
        $placement = $employee->only(Employee::PLACEMENT_COLUMNS);
        $placement['status'] = $employee->status?->value ?? EmployeeStatus::Active->value;

        return $placement;
    }

    /**
     * Copy the latest applied assignment (by effective date) onto the employee
     * and run the side effects of a status change.
     */
    private function syncCurrentPlacement(Employee $employee, EmployeeAssignment $applied): void
    {
        $latest = $employee->assignments()
            ->whereNotNull('applied_at')->whereNull('cancelled_at')
            ->orderByDesc('effective_date')->orderByDesc('id')
            ->first();

        if ($latest === null || ! $latest->is($applied)) {
            // A back-dated row: it is history now, the newer placement stays.
            return;
        }

        $wasTerminated = $employee->isTerminated();
        $employee->forceFill($latest->only(Employee::PLACEMENT_COLUMNS));
        $employee->status = $latest->status;

        if ($latest->change_type === AssignmentChangeType::Termination) {
            $employee->forceFill([
                'termination_date' => $latest->effective_date,
                'termination_type' => $latest->termination_type,
                'termination_reason' => $latest->reason,
                'eligible_for_rehire' => $latest->eligible_for_rehire,
            ]);
        }

        if ($latest->change_type === AssignmentChangeType::Rehire) {
            $employee->forceFill([
                'hire_date' => $latest->effective_date,
                'termination_date' => null,
                'termination_type' => null,
                'termination_reason' => null,
                'eligible_for_rehire' => null,
            ]);
        }

        $employee->updated_by = $this->actorId() ?? $employee->updated_by;
        $employee->save();

        if (! $wasTerminated && $employee->isTerminated()) {
            $this->setEmployeeAccountStatus($employee, UserStatus::Inactive);
        } elseif ($wasTerminated && ! $employee->isTerminated()) {
            $this->setEmployeeAccountStatus($employee, UserStatus::Active);
        }
    }

    private function syncCurrentSalary(Employee $employee): void
    {
        $latest = $employee->salaries()
            ->whereNotNull('applied_at')->whereNull('cancelled_at')
            ->orderByDesc('effective_date')->orderByDesc('id')
            ->first();

        $employee->basic_salary = $latest?->basic_salary;
        $employee->save();
    }

    /**
     * Termination switches the employee's own login off (and rehiring back on).
     * Admin accounts linked to the profile keep their admin access; they are
     * managed under Admin users.
     */
    private function setEmployeeAccountStatus(Employee $employee, UserStatus $status): void
    {
        User::query()->where('employee_id', $employee->getKey())->where('account_type', AccountType::Employee)
            ->get()->each(fn (User $user) => $user->update(['status' => $status]));
    }

    private function isDue(CarbonInterface $effectiveDate): bool
    {
        return Carbon::parse($effectiveDate)->startOfDay()->lte(today());
    }

    private function actorId(): ?int
    {
        $user = Auth::user();

        return $user instanceof User ? $user->getKey() : null;
    }
}
