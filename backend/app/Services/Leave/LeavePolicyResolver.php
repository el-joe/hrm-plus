<?php

namespace App\Services\Leave;

use App\Enums\LeavePolicyScope;
use App\Models\Employee;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Services\Attendance\EmploymentTimeline;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * The leave policy of an employee for a leave type on a date: among the
 * active policies of the type in force on the date, the most specific scope
 * wins (employee group > employment type > company), then the latest
 * effective_from. The employment type is read from the employee's placement
 * on that date (assignment history); groups are the current memberships.
 */
class LeavePolicyResolver
{
    /** @var array<int, Collection<int, LeavePolicy>> */
    private array $policies = [];

    public function __construct(private readonly EmploymentTimeline $timeline) {}

    public function for(Employee $employee, LeaveType $type, CarbonImmutable|string $date): ?LeavePolicy
    {
        $date = CarbonImmutable::parse($date)->startOfDay();
        $day = $date->format('Y-m-d');
        $placement = $this->timeline->placementOn($employee, $date);
        $employmentTypeId = $placement?->employment_type_id ?? $employee->employment_type_id;
        $groupIds = $employee->relationLoaded('groups')
            ? $employee->groups->modelKeys()
            : $employee->groups()->withoutGlobalScopes()->pluck('employee_groups.id')->all();

        return $this->policiesOf($type)
            ->filter(fn (LeavePolicy $policy): bool => $policy->effective_from->format('Y-m-d') <= $day
                && ($policy->effective_to === null || $policy->effective_to->format('Y-m-d') >= $day))
            ->filter(fn (LeavePolicy $policy): bool => match ($policy->scope_type) {
                LeavePolicyScope::Company => true,
                LeavePolicyScope::EmploymentType => $policy->scope_id === $employmentTypeId,
                LeavePolicyScope::EmployeeGroup => in_array($policy->scope_id, $groupIds, true),
            })
            ->sort(fn (LeavePolicy $a, LeavePolicy $b): int => [$b->scope_type->precedence(), $b->effective_from, $b->id]
                <=> [$a->scope_type->precedence(), $a->effective_from, $a->id])
            ->first();
    }

    public function forget(): void
    {
        $this->policies = [];
    }

    /**
     * @return Collection<int, LeavePolicy>
     */
    private function policiesOf(LeaveType $type): Collection
    {
        return $this->policies[$type->id] ??= LeavePolicy::query()->active()->where('leave_type_id', $type->id)->get();
    }
}
