<?php

namespace App\Services\Leave;

use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Who may take a leave type: the type is active, the employee's gender
 * matches (when the type is restricted), the minimum service is reached on
 * the first leave day, the "times per employment" limit (e.g. Hajj) is not
 * used up, and — for balance-tracked types — a leave policy covers the
 * employee (company, employment type or group).
 */
class LeaveEligibility
{
    public function __construct(private readonly LeavePolicyResolver $policies) {}

    /**
     * Reasons the employee may not take the type on the date (empty = eligible).
     *
     * @return list<string>
     */
    public function reasons(Employee $employee, LeaveType $type, CarbonImmutable|string $date, ?int $ignoreRequestId = null): array
    {
        $date = CarbonImmutable::parse($date)->startOfDay();
        $reasons = [];

        if (! $type->is_active) {
            $reasons[] = __('leave.validation.type_inactive');
        }

        if ($type->gender !== null && $employee->gender !== $type->gender) {
            $reasons[] = __('leave.validation.gender', ['gender' => $type->gender->label()]);
        }

        if ($type->min_service_months > 0) {
            $hire = $employee->hire_date ? CarbonImmutable::parse($employee->hire_date->format('Y-m-d')) : null;

            if ($hire === null || (int) $hire->diffInMonths($date) < $type->min_service_months) {
                $reasons[] = __('leave.validation.min_service', ['months' => $type->min_service_months]);
            }
        }

        if ($type->max_times_per_service !== null && $this->timesTaken($employee, $type, $ignoreRequestId) >= $type->max_times_per_service) {
            $reasons[] = __('leave.validation.max_times', ['times' => $type->max_times_per_service]);
        }

        if ($type->tracks_balance && $this->policies->for($employee, $type, $date) === null) {
            $reasons[] = __('leave.validation.no_policy');
        }

        return $reasons;
    }

    public function isEligible(Employee $employee, LeaveType $type, CarbonImmutable|string $date): bool
    {
        return $this->reasons($employee, $type, $date) === [];
    }

    /**
     * Active types the employee may request today (portal form, API): gender, minimum service
     * reached, times per employment not used up, covered by a policy.
     *
     * @return Collection<int, LeaveType>
     */
    public function requestableTypes(Employee $employee): Collection
    {
        $today = CarbonImmutable::today();

        return LeaveType::query()->active()->ordered()->get()
            ->filter(fn (LeaveType $type): bool => ($type->gender === null || $type->gender === $employee->gender)
                && ($type->min_service_months === 0 || ($employee->hire_date !== null
                    && (int) CarbonImmutable::parse($employee->hire_date->format('Y-m-d'))->diffInMonths($today) >= $type->min_service_months))
                && ($type->max_times_per_service === null || $this->timesTaken($employee, $type) < $type->max_times_per_service)
                && (! $type->tracks_balance || $this->policies->for($employee, $type, $today) !== null))
            ->values();
    }

    /**
     * Requests of the type taken (or awaiting a decision) since the current hire date.
     */
    public function timesTaken(Employee $employee, LeaveType $type, ?int $ignoreRequestId = null): int
    {
        return LeaveRequest::query()->withoutGlobalScopes()
            ->where('employee_id', $employee->id)->where('leave_type_id', $type->id)
            ->inStatus(LeaveRequestStatus::active())
            ->when($employee->hire_date, fn ($query) => $query->whereDate('start_date', '>=', $employee->hire_date->format('Y-m-d')))
            ->when($ignoreRequestId, fn ($query, int $id) => $query->whereKeyNot($id))
            ->count();
    }
}
