<?php

namespace App\Services\Payroll;

use App\Enums\SalaryStructureScope;
use App\Models\Employee;
use App\Models\SalaryComponent;
use App\Models\SalaryStructureAssignment;
use App\Models\SalaryStructureLine;
use App\Services\Payroll\Data\ComponentRule;
use App\Services\Payroll\Data\StructureSnapshot;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The salary structure that applies to an employee on a date.
 *
 * Precedence, most specific first: employee > job grade > department
 * (the employee's, then each parent up the tree) > branch > company. Within
 * a scope the latest effective_from wins. Only active structures with a
 * version in force on the date count; the placement (grade, department,
 * branch) is the one in force on the date.
 */
class SalaryStructureResolver
{
    public function __construct(private readonly PayrollPlacement $placement) {}

    public function for(Employee $employee, CarbonInterface|string $date): ?StructureSnapshot
    {
        $day = CarbonImmutable::parse($date)->format('Y-m-d');
        $assignment = $this->assignmentFor($employee, $day);

        if ($assignment === null) {
            return null;
        }

        $structure = $assignment->structure;
        $version = $structure->versionOn($day);

        if ($version === null) {
            return null;
        }

        $lines = $version->lines()->with('component')->get();
        $codes = SalaryComponent::query()->pluck('code', 'id')->all();

        return new StructureSnapshot(
            structureId: $structure->id,
            code: $structure->code,
            name: $structure->name,
            versionId: $version->id,
            version: $version->version,
            versionFrom: $version->effective_from->toDateString(),
            assignmentId: $assignment->id,
            scope: $assignment->scope_type,
            lines: $lines->map(fn (SalaryStructureLine $line): ComponentRule => ComponentRule::fromComponent($line->component, $line, $codes))
                ->sortBy([fn (ComponentRule $a, ComponentRule $b): int => [$a->displayOrder, $a->code] <=> [$b->displayOrder, $b->code]])
                ->values()->all(),
        );
    }

    /**
     * The winning assignment row (null when nothing applies).
     */
    public function assignmentFor(Employee $employee, string $day): ?SalaryStructureAssignment
    {
        $placement = $this->placement->on($employee, $day);
        $departmentId = $placement?->department_id ?? $employee->department_id;

        $candidates = [[SalaryStructureScope::Employee, $employee->id]];
        $grade = $placement?->job_grade_id ?? $employee->job_grade_id;

        if ($grade !== null) {
            $candidates[] = [SalaryStructureScope::JobGrade, $grade];
        }

        foreach ($this->placement->departmentChain($departmentId) as $department) {
            $candidates[] = [SalaryStructureScope::Department, $department];
        }

        $candidates[] = [SalaryStructureScope::Branch, $placement?->branch_id ?? $employee->branch_id];
        $candidates[] = [SalaryStructureScope::Company, null];

        $rows = SalaryStructureAssignment::query()->effectiveOn($day)
            ->whereHas('structure', fn ($query) => $query->where('is_active', true))
            ->with('structure')
            ->orderByDesc('effective_from')->orderByDesc('id')
            ->get();

        foreach ($candidates as [$scope, $id]) {
            $match = $rows->first(fn (SalaryStructureAssignment $row): bool => $row->scope_type === $scope
                && ($scope === SalaryStructureScope::Company || (int) $row->scope_id === (int) $id));

            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }
}
