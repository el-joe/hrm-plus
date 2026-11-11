<?php

namespace App\Services\Structure;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds the organisational chart as a tree of plain arrays that
 * resources/views/admin/structure/org-chart/_node.blade.php renders.
 *
 * Two views:
 * - departments: the department tree (parent_id), each node with its branch,
 *   head position, positions and approved headcount;
 * - positions: the reporting hierarchy (reports_to_position_id).
 *
 * Queries go through Eloquent, so a data-restricted admin only gets the part
 * of the tree inside their scope; a node whose parent is outside it becomes
 * a root.
 *
 * Every node has a `people` list (['name', 'subtitle', 'url', 'avatar']):
 * the department head on department nodes, the position holders on position
 * nodes. A third view, people(), is the manager tree (employees by direct
 * manager). Links to profiles are only given to admins who may open them.
 *
 * @phpstan-type OrgNode array{
 *     key: string, title: string, code: string, subtitle: ?string, url: ?string,
 *     active: bool, lead: ?string, meta: list<array{label: string, value: string}>,
 *     people: list<array{name: string, subtitle: ?string, url: ?string, avatar: ?string}>,
 *     children: list<array<string, mixed>>
 * }
 */
class OrgChartBuilder
{
    /**
     * @return list<OrgNode>
     */
    public function departments(User $viewer): array
    {
        $departments = Department::query()
            ->with(['branch', 'headPosition.holders'])
            ->withCount(['positions', 'teams', 'employees' => fn ($query) => $query->employed()])
            ->withSum('positions', 'headcount')
            ->orderBy('code')
            ->get();

        $canOpen = $viewer->can('departments.view');
        $canViewEmployees = $viewer->can('employees.view');

        return $this->tree($departments, 'parent_id', fn (Department $department): array => [
            'key' => 'department-'.$department->id,
            'title' => $department->name,
            'code' => $department->code,
            'subtitle' => $department->branch?->name ?? __('structure.departments.company_wide'),
            'url' => $canOpen ? route('admin.departments.show', $department) : null,
            'active' => $department->is_active,
            'lead' => $department->headPosition?->name,
            'meta' => [
                ['label' => __('structure.org_chart.positions'), 'value' => (string) $department->positions_count],
                ['label' => __('structure.org_chart.headcount'), 'value' => (string) (int) $department->positions_sum_headcount],
                ['label' => __('structure.org_chart.teams'), 'value' => (string) $department->teams_count],
                ['label' => __('structure.org_chart.employees'), 'value' => (string) $department->employees_count],
            ],
            'people' => $department->headPosition
                ? $this->peopleList($department->headPosition->holders, $canViewEmployees, $department->headPosition->name)
                : [],
            'children' => [],
        ]);
    }

    /**
     * @return list<OrgNode>
     */
    public function positions(User $viewer): array
    {
        $positions = Position::query()
            ->with(['department', 'jobGrade', 'holders'])
            ->orderBy('code')
            ->get();

        $canEdit = $viewer->can('positions.update');
        $canViewEmployees = $viewer->can('employees.view');

        return $this->tree($positions, 'reports_to_position_id', fn (Position $position): array => [
            'key' => 'position-'.$position->id,
            'title' => $position->name,
            'code' => $position->code,
            'subtitle' => $position->department?->name,
            'url' => $canEdit ? route('admin.positions.edit', $position) : null,
            'active' => $position->is_active,
            'lead' => null,
            'meta' => array_values(array_filter([
                ['label' => __('structure.org_chart.headcount'), 'value' => $position->holders->count().' / '.$position->headcount],
                $position->jobGrade ? ['label' => __('structure.org_chart.grade'), 'value' => $position->jobGrade->name] : null,
            ])),
            'people' => $this->peopleList($position->holders, $canViewEmployees),
            'children' => [],
        ]);
    }

    /**
     * The manager tree: employed employees under their direct manager.
     *
     * @return list<OrgNode>
     */
    public function managers(User $viewer): array
    {
        $employees = Employee::query()
            ->employed()
            ->with(['jobTitle', 'department'])
            ->withCount(['directReports' => fn ($query) => $query->employed()])
            ->orderBy('employee_number')
            ->get();

        $canOpen = $viewer->can('employees.view');

        return $this->tree($employees, 'manager_id', fn (Employee $employee): array => [
            'key' => 'employee-'.$employee->id,
            'title' => $employee->name,
            'code' => $employee->employee_number,
            'subtitle' => $employee->jobTitle?->name ?? $employee->department?->name,
            'url' => $canOpen ? route('admin.employees.show', $employee) : null,
            'active' => $employee->isActive(),
            'lead' => null,
            'meta' => array_values(array_filter([
                $employee->department ? ['label' => __('structure.fields.department_id'), 'value' => $employee->department->name] : null,
                $employee->direct_reports_count > 0 ? ['label' => __('structure.org_chart.direct_reports'), 'value' => (string) $employee->direct_reports_count] : null,
            ])),
            'people' => [],
            'children' => [],
        ]);
    }

    /**
     * @param  iterable<Employee>  $employees
     * @return list<array{name: string, subtitle: ?string, url: ?string, avatar: ?string}>
     */
    private function peopleList(iterable $employees, bool $linkProfiles, ?string $subtitle = null): array
    {
        $people = [];

        foreach ($employees as $employee) {
            $people[] = [
                'name' => $employee->name,
                'subtitle' => $subtitle ?? $employee->employee_number,
                'url' => $linkProfiles ? route('admin.employees.show', $employee) : null,
                'avatar' => $employee->photo_path && $linkProfiles ? route('admin.employees.photo', $employee) : null,
            ];
        }

        return $people;
    }

    /**
     * Totals of a built tree, for the summary above the chart.
     *
     * @param  list<OrgNode>  $nodes
     * @return array{nodes: int, roots: int, depth: int}
     */
    public function summary(array $nodes): array
    {
        $count = 0;
        $depth = 0;
        $walk = function (array $nodes, int $level) use (&$walk, &$count, &$depth): void {
            foreach ($nodes as $node) {
                $count++;
                $depth = max($depth, $level);
                $walk($node['children'], $level + 1);
            }
        };
        $walk($nodes, 1);

        return ['nodes' => $count, 'roots' => count($nodes), 'depth' => $depth];
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<int, TModel>  $records
     * @param  callable(TModel): array<string, mixed>  $toNode
     * @return list<OrgNode>
     */
    private function tree(Collection $records, string $parentColumn, callable $toNode): array
    {
        $ids = $records->modelKeys();
        $childrenOf = $records->groupBy(
            fn ($record): string => in_array($record->{$parentColumn}, $ids, false) ? (string) $record->{$parentColumn} : 'root',
        );

        $build = function (string $parentKey) use (&$build, $childrenOf, $toNode): array {
            return $childrenOf->get($parentKey, collect())
                ->map(function ($record) use (&$build, $toNode): array {
                    $node = $toNode($record);
                    $node['children'] = $build((string) $record->getKey());

                    return $node;
                })
                ->values()
                ->all();
        };

        return $build('root');
    }
}
