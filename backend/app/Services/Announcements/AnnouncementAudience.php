<?php

namespace App\Services\Announcements;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\Department;
use App\Models\Employee;
use App\Services\Authorization\DataScopeManager;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who an announcement reaches: every active employee for audience "all", otherwise the active employees
 * matching ANY target — branch, department or one of its sub-departments, employee group, employment type.
 * Data scopes are never applied here (the audience is the same for every admin looking at it).
 */
class AnnouncementAudience
{
    public function __construct(private readonly DataScopeManager $scopes) {}

    /**
     * Active employees in the audience.
     *
     * @return Builder<Employee>
     */
    public function employees(Announcement $announcement): Builder
    {
        $query = Employee::query()->withoutGlobalScopes()->active();

        if (! $announcement->isTargeted()) {
            return $query;
        }

        $targets = $announcement->targets()->get()->groupBy(fn ($target) => $target->target_type->value)
            ->map(fn ($rows) => $rows->pluck('target_id')->map(fn ($id): int => (int) $id)->all());

        if ($targets->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        $branches = $targets->get(AnnouncementTargetType::Branch->value, []);
        $departments = $this->scopes->withSubDepartments($targets->get(AnnouncementTargetType::Department->value, []));
        $groups = $targets->get(AnnouncementTargetType::EmployeeGroup->value, []);
        $types = $targets->get(AnnouncementTargetType::EmploymentType->value, []);

        return $query->where(fn (Builder $query) => $query
            ->when($branches !== [], fn (Builder $query) => $query->orWhereIn('employees.branch_id', $branches))
            ->when($departments !== [], fn (Builder $query) => $query->orWhereIn('employees.department_id', $departments))
            ->when($types !== [], fn (Builder $query) => $query->orWhereIn('employees.employment_type_id', $types))
            ->when($groups !== [], fn (Builder $query) => $query->orWhereHas('groups', fn (Builder $group) => $group->withoutGlobalScopes()->whereIn('employee_groups.id', $groups))));
    }

    public function count(Announcement $announcement): int
    {
        return $this->employees($announcement)->count();
    }

    /**
     * The department and every department above it (a target on a parent covers its children).
     *
     * @return list<int>
     */
    public function departmentWithAncestors(?int $departmentId): array
    {
        $ids = [];
        $current = $departmentId;

        while ($current !== null && ! in_array($current, $ids, true)) {
            $ids[] = $current;
            $parent = Department::query()->withoutGlobalScopes()->whereKey($current)->value('parent_id');
            $current = $parent === null ? null : (int) $parent;
        }

        return $ids;
    }
}
