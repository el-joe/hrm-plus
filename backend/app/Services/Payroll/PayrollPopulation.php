<?php

namespace App\Services\Payroll;

use App\Enums\EmployeeStatus;
use App\Enums\PayrollRunType;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeGroup;
use App\Models\PayrollAdjustment;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Models\Team;
use App\Services\Authorization\DataScopeManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Settlement scoping (spec module 9 F): who a payroll run pays.
 *
 * A scope is the whole company ("all") or a combination of filters —
 * branches, departments (with their sub-departments), teams, positions and
 * employee groups. Different dimensions combine with AND (Branch A + Sales →
 * the Sales staff of Branch A), values inside a dimension with OR; individual
 * employees can be added on top (include list) and any employee can be left
 * out (exclude list).
 *
 * Placement is read at the period END (the same placement PayrollInputs uses),
 * so an employee who moved branch mid-period belongs to exactly one branch's
 * settlement: the new one. The preview lists them as "moved in" there and as
 * "moved out — paid by the period-end branch" in the old branch's preview.
 *
 * Candidates are employees employed for at least one day of the period
 * (hired on or before its end, not terminated before its start). Inside the
 * admin area the global data scope limits them to the admin's scope.
 *
 * Double pay: for regular runs and final settlements an employee who already
 * has a live pay slot in the period (another run) is excluded with the run's
 * reference; the unique index on payroll_items is the final guarantee.
 * Correction runs only take employees with pending adjustments.
 */
class PayrollPopulation
{
    public const DIMENSIONS = ['branch_ids', 'department_ids', 'team_ids', 'position_ids', 'employee_group_ids'];

    public function __construct(private readonly DataScopeManager $scopes) {}

    /**
     * Normalise a scope array (ids as unique sorted ints).
     *
     * @param  array<string, mixed>  $scope
     * @return array{all: bool, branch_ids: list<int>, department_ids: list<int>, team_ids: list<int>, position_ids: list<int>, employee_group_ids: list<int>, employee_ids: list<int>, exclude_employee_ids: list<int>}
     */
    public function normalise(array $scope): array
    {
        $ids = function (mixed $values): array {
            $list = array_values(array_unique(array_map('intval', array_filter((array) ($values ?? []), fn ($value): bool => filled($value)))));
            sort($list);

            return $list;
        };

        $normalised = ['all' => (bool) ($scope['all'] ?? false)];

        foreach ([...self::DIMENSIONS, 'employee_ids', 'exclude_employee_ids'] as $key) {
            $normalised[$key] = $ids($scope[$key] ?? []);
        }

        if ($normalised['all']) {
            foreach (self::DIMENSIONS as $key) {
                $normalised[$key] = [];
            }
        }

        return $normalised;
    }

    public function isEmpty(array $scope): bool
    {
        $scope = $this->normalise($scope);

        return ! $scope['all'] && $scope['employee_ids'] === [] && collect(self::DIMENSIONS)->every(fn (string $key): bool => $scope[$key] === []);
    }

    /**
     * Who is in and who is out, with the reason.
     *
     * @param  array<string, mixed>  $scope
     * @return array{
     *     included: list<array<string, mixed>>,
     *     excluded: list<array<string, mixed>>,
     *     summary: array{included: int, excluded: int, moved: int, joiners: int, leavers: int, no_salary: int, branches: int}
     * }
     */
    public function preview(PayrollPeriod $period, PayrollRunType $type, array $scope, ?int $ignoreRunId = null): array
    {
        $scope = $this->normalise($scope);
        $start = $period->start_date->toDateString();
        $end = $period->end_date->toDateString();
        $candidates = $this->candidates($start, $end);
        $placements = $this->placements($candidates->pluck('id')->all(), $start, $end);
        $departmentIds = $scope['department_ids'] === [] ? [] : $this->scopes->withSubDepartments($scope['department_ids']);
        $taken = $type->holdsPaySlot() ? $this->takenSlots($period, $candidates->pluck('id')->all(), $ignoreRunId) : collect();
        $withAdjustments = $type === PayrollRunType::Correction
            ? PayrollAdjustment::query()->withoutGlobalScopes()->pending()->whereNull('payroll_item_id')->whereIn('employee_id', $candidates->pluck('id'))->pluck('employee_id')->unique()->flip()
            : collect();
        $withSalary = $this->withSalary($candidates->pluck('id')->all(), $end);

        $included = [];
        $excluded = [];

        foreach ($candidates as $employee) {
            $placement = $placements[$employee->id];
            $row = $this->row($employee, $placement, $start, $end, $withSalary);
            $matchesEnd = $this->matches($scope, $placement['end'], $employee, $departmentIds);
            $individual = in_array($employee->id, $scope['employee_ids'], true);

            if (! $matchesEnd && ! $individual) {
                $movedOut = collect($placement['segments'])->contains(fn (array $segment): bool => $this->matches($scope, $segment, $employee, $departmentIds, placementOnly: true));

                if ($movedOut && ! $scope['all']) {
                    $excluded[] = [...$row, 'reason' => 'moved_out'];
                }

                continue;
            }

            if (in_array($employee->id, $scope['exclude_employee_ids'], true)) {
                $excluded[] = [...$row, 'reason' => 'excluded'];

                continue;
            }

            if ($taken->has($employee->id)) {
                $excluded[] = [...$row, 'reason' => 'already_paid', 'run_reference' => $taken[$employee->id]->run_reference, 'run_id' => $taken[$employee->id]->payroll_run_id];

                continue;
            }

            if ($type === PayrollRunType::Correction && ! $withAdjustments->has($employee->id)) {
                if ($individual) {
                    $excluded[] = [...$row, 'reason' => 'no_pending_corrections'];
                }

                continue;
            }

            $included[] = $row;
        }

        return [
            'included' => $included,
            'excluded' => $excluded,
            'summary' => [
                'included' => count($included),
                'excluded' => count($excluded),
                'moved' => count(array_filter($included, fn (array $row): bool => $row['moved'])),
                'joiners' => count(array_filter($included, fn (array $row): bool => $row['joined_in_period'])),
                'leavers' => count(array_filter($included, fn (array $row): bool => $row['leaving_in_period'])),
                'no_salary' => count(array_filter($included, fn (array $row): bool => ! $row['has_salary'])),
                'branches' => count(array_unique(array_column($included, 'branch_id'))),
            ],
        ];
    }

    /**
     * Readable one-line summary of a scope ("Branch: CAI · Department: SAL-CAI · +2 employees · −1").
     *
     * @param  array<string, mixed>  $scope
     */
    public function describe(array $scope): string
    {
        $scope = $this->normalise($scope);

        if ($scope['all']) {
            $parts = [__('payroll_runs.scope.whole_company')];
        } else {
            $parts = [];
            $models = [
                'branch_ids' => Branch::class, 'department_ids' => Department::class, 'team_ids' => Team::class,
                'position_ids' => Position::class, 'employee_group_ids' => EmployeeGroup::class,
            ];

            foreach ($models as $key => $model) {
                if ($scope[$key] !== []) {
                    $names = $model::query()->withoutGlobalScopes()->whereIn('id', $scope[$key])->get()->map(fn ($record): string => $record->code ?? $record->name)->implode(', ');
                    $parts[] = __('payroll_runs.scope.dimensions.'.$key).': '.$names;
                }
            }
        }

        if ($scope['employee_ids'] !== []) {
            $parts[] = __('payroll_runs.scope.plus_employees', ['count' => count($scope['employee_ids'])]);
        }

        if ($scope['exclude_employee_ids'] !== []) {
            $parts[] = __('payroll_runs.scope.minus_employees', ['count' => count($scope['exclude_employee_ids'])]);
        }

        return implode(' · ', $parts);
    }

    /**
     * Employees employed for at least one day of the period (inside the admin's data scope).
     *
     * @return Collection<int, Employee>
     */
    public function candidates(string $start, string $end): Collection
    {
        return Employee::query()
            ->with('groups:id')
            ->where('hire_date', '<=', $end)
            ->where(fn ($query) => $query->whereNull('termination_date')->orWhere('termination_date', '>=', $start))
            ->where(fn ($query) => $query->where('status', '!=', EmployeeStatus::Terminated)->orWhere('termination_date', '>=', $start))
            ->orderBy('employee_number')
            ->get();
    }

    /**
     * Placement at the period end and the segments inside the period, for many employees at once.
     *
     * @param  list<int>  $employeeIds
     * @return array<int, array{end: array<string, ?int>, segments: list<array<string, mixed>>}>
     */
    public function placements(array $employeeIds, string $start, string $end): array
    {
        $rows = EmployeeAssignment::query()->withoutGlobalScopes()
            ->whereIn('employee_id', $employeeIds)
            ->whereNotNull('applied_at')->whereNull('cancelled_at')
            ->where('effective_date', '<=', $end)
            ->orderBy('effective_date')->orderBy('id')
            ->get()->groupBy('employee_id');
        $employees = Employee::query()->withoutGlobalScopes()->whereIn('id', $employeeIds)->get()->keyBy('id');
        $result = [];

        foreach ($employeeIds as $id) {
            $history = $rows->get($id, collect())->values();
            $fields = fn ($source): array => [
                'branch_id' => $source?->branch_id, 'department_id' => $source?->department_id, 'team_id' => $source?->team_id,
                'position_id' => $source?->position_id, 'cost_center_id' => $source?->cost_center_id,
            ];
            $last = $history->last();
            $endPlacement = $fields($last ?? $employees->get($id));
            $atStart = $history->filter(fn ($row): bool => $row->effective_date->toDateString() <= $start)->last();
            $inside = $history->filter(fn ($row): bool => $row->effective_date->toDateString() > $start);
            $segments = [];

            foreach (collect([$atStart])->filter()->concat($inside)->values() as $row) {
                $segments[] = [...$fields($row), 'from' => max($start, $row->effective_date->toDateString())];
            }

            $result[$id] = ['end' => $endPlacement, 'segments' => $segments === [] ? [[...$endPlacement, 'from' => $start]] : $segments];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $scope
     * @param  array<string, mixed>  $placement
     * @param  list<int>  $departmentIds  selected departments with their sub-departments
     */
    private function matches(array $scope, array $placement, Employee $employee, array $departmentIds, bool $placementOnly = false): bool
    {
        if ($scope['all']) {
            return true;
        }

        $checks = [
            'branch_ids' => fn (): bool => in_array((int) $placement['branch_id'], $scope['branch_ids'], true),
            'department_ids' => fn (): bool => in_array((int) $placement['department_id'], $departmentIds, true),
            'team_ids' => fn (): bool => in_array((int) $placement['team_id'], $scope['team_ids'], true),
            'position_ids' => fn (): bool => in_array((int) $placement['position_id'], $scope['position_ids'], true),
            'employee_group_ids' => fn (): bool => $employee->groups->pluck('id')->intersect($scope['employee_group_ids'])->isNotEmpty(),
        ];
        $active = array_filter(self::DIMENSIONS, fn (string $key): bool => $scope[$key] !== [] && (! $placementOnly || in_array($key, ['branch_ids', 'department_ids'], true)));

        if ($active === []) {
            return false;
        }

        foreach ($active as $key) {
            if (! $checks[$key]()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{end: array<string, ?int>, segments: list<array<string, mixed>>}  $placement
     * @param  Collection<int, mixed>  $withSalary
     * @return array<string, mixed>
     */
    private function row(Employee $employee, array $placement, string $start, string $end, Collection $withSalary): array
    {
        $segments = $placement['segments'];
        $moved = collect($segments)->map(fn (array $row): string => $row['branch_id'].'-'.$row['department_id'])->unique()->count() > 1;

        return [
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'name' => $employee->name,
            'status' => $employee->status->value,
            ...$placement['end'],
            'moved' => $moved,
            'from_branch_id' => $moved ? $segments[0]['branch_id'] : null,
            'from_department_id' => $moved ? $segments[0]['department_id'] : null,
            'moved_on' => $moved ? $segments[array_key_last($segments)]['from'] : null,
            'joined_in_period' => $employee->hire_date->toDateString() > $start,
            'leaving_in_period' => $employee->termination_date !== null && $employee->termination_date->toDateString() <= $end,
            'has_salary' => $withSalary->has($employee->id),
        ];
    }

    /**
     * Live pay slots already held in the period by other runs.
     *
     * @param  list<int>  $employeeIds
     * @return Collection<int, object>
     */
    private function takenSlots(PayrollPeriod $period, array $employeeIds, ?int $ignoreRunId): Collection
    {
        return PayrollItem::query()->withoutGlobalScopes()
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payroll_items.payroll_run_id')
            ->where('payroll_items.payroll_period_id', $period->id)
            ->where('payroll_items.pay_slot', PayrollItem::PAY_SLOT)
            ->whereIn('payroll_items.employee_id', $employeeIds)
            ->when($ignoreRunId, fn ($query, int $id) => $query->where('payroll_items.payroll_run_id', '!=', $id))
            ->get(['payroll_items.employee_id', 'payroll_items.payroll_run_id', 'payroll_runs.reference as run_reference'])
            ->keyBy('employee_id');
    }

    /**
     * @param  list<int>  $employeeIds
     * @return Collection<int, mixed>
     */
    private function withSalary(array $employeeIds, string $end): Collection
    {
        return collect(DB::table('employee_salaries')->whereIn('employee_id', $employeeIds)->whereNull('cancelled_at')
            ->where('effective_date', '<=', $end)->distinct()->pluck('employee_id'))->flip();
    }
}
