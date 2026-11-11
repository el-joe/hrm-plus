<?php

use App\Actions\Payroll\ManagePayrollRun;
use App\Enums\AssignmentChangeType;
use App\Enums\PayrollRunType;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeGroup;
use App\Models\PayrollItem;
use App\Models\Position;
use App\Models\Team;
use App\Services\Employees\EmploymentHistory;
use App\Services\Payroll\PayrollPopulation;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
| Settlement scoping — spec module 9 F (client requirement): runs by branch,
| department (with sub-departments), team, position, employee group,
| individual employees or the whole company; combinations (AND across
| filters, OR inside one) with include / exclude lists; the preview; one pay
| per employee and period (population check + unique index); employees who
| moved branch mid-period belong to their period-end branch only.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00:00'));
    payrollSetup();
    $this->period = payrollPeriod('2026-10');
    $this->accountant = adminWithRoles('Payroll Accountant');

    $this->cairo = Branch::factory()->create(['code' => 'CAI', 'timezone' => 'Africa/Cairo']);
    $this->alex = Branch::factory()->create(['code' => 'ALX', 'timezone' => 'Africa/Cairo']);
    $this->sales = Department::factory()->create(['branch_id' => $this->cairo->id, 'code' => 'SAL']);
    $this->salesNorth = Department::factory()->create(['branch_id' => $this->cairo->id, 'code' => 'SAL-N', 'parent_id' => $this->sales->id]);
    $this->it = Department::factory()->create(['branch_id' => $this->cairo->id, 'code' => 'IT']);
    $this->ops = Department::factory()->create(['branch_id' => $this->alex->id, 'code' => 'OPS']);

    $hire = function (Branch $branch, Department $department, array $extra = []): Employee {
        $employee = paidEmployee('6000.00', attributes: ['branch_id' => $branch->id, 'department_id' => $department->id, ...$extra]);
        app(EmploymentHistory::class)->recordAssignment($employee, AssignmentChangeType::Hire,
            ['branch_id' => $branch->id, 'department_id' => $department->id, ...$extra], CarbonImmutable::parse('2024-01-01'));

        return $employee->refresh();
    };

    $this->team = Team::factory()->create(['department_id' => $this->sales->id]);
    $this->position = Position::factory()->create(['department_id' => $this->it->id]);
    $this->salesRep = $hire($this->cairo, $this->sales, ['team_id' => $this->team->id]);
    $this->northRep = $hire($this->cairo, $this->salesNorth);
    $this->developer = $hire($this->cairo, $this->it, ['position_id' => $this->position->id]);
    $this->operator = $hire($this->alex, $this->ops);
    $this->group = EmployeeGroup::factory()->create();
    $this->group->employees()->attach([$this->developer->id, $this->operator->id]);

    $this->preview = fn (array $scope, PayrollRunType $type = PayrollRunType::Regular): array => app(PayrollPopulation::class)->preview($this->period, $type, $scope);
    $this->ids = fn (array $preview, string $list = 'included'): array => collect($preview[$list])->pluck('employee_id')->sort()->values()->all();
});

it('scopes by each dimension', function (string $key, Closure $values, Closure $expected) {
    $preview = ($this->preview)([$key => $values->call($this)]);

    expect(($this->ids)($preview))->toBe(collect($expected->call($this))->pluck('id')->sort()->values()->all());
})->with([
    'branch' => ['branch_ids', fn () => [$this->alex->id], fn () => [$this->operator]],
    'department with sub-departments' => ['department_ids', fn () => [$this->sales->id], fn () => [$this->salesRep, $this->northRep]],
    'team' => ['team_ids', fn () => [$this->team->id], fn () => [$this->salesRep]],
    'position' => ['position_ids', fn () => [$this->position->id], fn () => [$this->developer]],
    'employee group' => ['employee_group_ids', fn () => [$this->group->id], fn () => [$this->developer, $this->operator]],
    'individual employees' => ['employee_ids', fn () => [$this->northRep->id], fn () => [$this->northRep]],
]);

it('pays the whole company in a consolidated run', function () {
    expect(($this->ids)(($this->preview)(['all' => true])))->toHaveCount(4);
});

it('combines filters with AND across dimensions and OR inside one, plus include and exclude lists', function () {
    // Cairo AND (Sales or IT) = three employees; minus the developer; plus the operator individually.
    $preview = ($this->preview)([
        'branch_ids' => [$this->cairo->id], 'department_ids' => [$this->sales->id, $this->it->id],
        'exclude_employee_ids' => [$this->developer->id], 'employee_ids' => [$this->operator->id],
    ]);

    expect(($this->ids)($preview))->toBe(collect([$this->salesRep, $this->northRep, $this->operator])->pluck('id')->sort()->values()->all())
        ->and(collect($preview['excluded'])->firstWhere('employee_id', $this->developer->id)['reason'])->toBe('excluded');

    // Branch AND group: only the IT developer of Cairo is in the group.
    expect(($this->ids)(($this->preview)(['branch_ids' => [$this->cairo->id], 'employee_group_ids' => [$this->group->id]])))->toBe([$this->developer->id]);
});

it('previews summary figures (joiners, leavers, missing salary)', function () {
    $joiner = paidEmployee('5000.00', attributes: ['branch_id' => $this->alex->id, 'department_id' => $this->ops->id, 'hire_date' => '2026-10-20', 'original_hire_date' => '2026-10-20'], from: '2026-10-20');
    $noSalary = attendanceEmployee(['branch_id' => $this->alex->id, 'department_id' => $this->ops->id, 'hire_date' => '2024-01-01', 'original_hire_date' => '2024-01-01']);

    $preview = ($this->preview)(['branch_ids' => [$this->alex->id]]);

    expect($preview['summary'])->toMatchArray(['included' => 3, 'joiners' => 1, 'no_salary' => 1, 'branches' => 1])
        ->and(collect($preview['included'])->firstWhere('employee_id', $joiner->id)['joined_in_period'])->toBeTrue()
        ->and(collect($preview['included'])->firstWhere('employee_id', $noSalary->id)['has_salary'])->toBeFalse();
});

it('never pays an employee twice for the same period: the second run leaves them out', function () {
    $runs = app(ManagePayrollRun::class);
    $branchRun = $runs->create($this->period, PayrollRunType::Regular, ['branch_ids' => [$this->alex->id]], $this->accountant);

    $preview = ($this->preview)(['all' => true]);
    expect(($this->ids)($preview))->not->toContain($this->operator->id)
        ->and(collect($preview['excluded'])->firstWhere('employee_id', $this->operator->id))->toMatchArray(['reason' => 'already_paid', 'run_id' => $branchRun->id]);

    $consolidated = $runs->create($this->period, PayrollRunType::Regular, ['all' => true], $this->accountant);
    expect($consolidated->employee_count)->toBe(3)
        ->and($branchRun->branch_id)->toBe($this->alex->id)
        ->and($consolidated->branch_id)->toBe($this->cairo->id);

    // Nobody left: a third run is refused.
    expect(fn () => $runs->create($this->period, PayrollRunType::Regular, ['all' => true], $this->accountant))->toThrow(ValidationException::class);
});

it('enforces one pay slot per employee and period at the database level', function () {
    $run = app(ManagePayrollRun::class)->create($this->period, PayrollRunType::Regular, ['employee_ids' => [$this->operator->id]], $this->accountant);
    $item = PayrollItem::query()->withoutGlobalScopes()->where('payroll_run_id', $run->id)->firstOrFail();

    $other = app(ManagePayrollRun::class)->create($this->period, PayrollRunType::Regular, ['employee_ids' => [$this->developer->id]], $this->accountant);
    expect(fn () => DB::table('payroll_items')->insert([
        ...$item->only(['payroll_period_id', 'branch_id', 'department_id', 'currency']), 'payroll_run_id' => $other->id,
        'employee_id' => $this->operator->id, 'pay_slot' => PayrollItem::PAY_SLOT, 'status' => 'pending',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('frees the pay slots of a cancelled run', function () {
    $runs = app(ManagePayrollRun::class);
    $run = $runs->create($this->period, PayrollRunType::Regular, ['branch_ids' => [$this->alex->id]], $this->accountant);
    $runs->cancel($run, $this->accountant, 'Wrong scope');

    expect(($this->ids)(($this->preview)(['branch_ids' => [$this->alex->id]])))->toBe([$this->operator->id]);
});

it('pays an employee who moved branch mid-period only through the period-end branch', function () {
    app(EmploymentHistory::class)->recordAssignment($this->salesRep, AssignmentChangeType::Transfer,
        ['branch_id' => $this->alex->id, 'department_id' => $this->ops->id], CarbonImmutable::parse('2026-10-16'), 'Transfer');

    $cairo = ($this->preview)(['branch_ids' => [$this->cairo->id]]);
    $alex = ($this->preview)(['branch_ids' => [$this->alex->id]]);
    $moved = collect($alex['included'])->firstWhere('employee_id', $this->salesRep->id);

    expect(($this->ids)($cairo))->not->toContain($this->salesRep->id)
        ->and(collect($cairo['excluded'])->firstWhere('employee_id', $this->salesRep->id)['reason'])->toBe('moved_out')
        ->and($moved)->toMatchArray(['moved' => true, 'from_branch_id' => $this->cairo->id, 'moved_on' => '2026-10-16']);

    $run = calculatedRun($this->period, ['branch_ids' => [$this->alex->id]], $this->accountant);
    $item = payItem($run, $this->salesRep);

    expect($item->moved_in_period)->toBeTrue()
        ->and($item->branch_id)->toBe($this->alex->id)
        ->and(payLine($run, $this->salesRep, 'BASIC')->amount)->toBe('6000.00')   // paid in full, once
        ->and(collect($item->quantities['placement'])->pluck('branch_id')->all())->toBe([$this->cairo->id, $this->alex->id])
        ->and(collect($item->warnings)->pluck('code'))->toContain('moved_in_period');
});

it('limits the population of a data-scoped admin to their branches', function () {
    $scoped = adminWithPermissions('payroll.view', 'payroll.create', 'payroll.update');
    $scoped->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => $this->alex->id]);

    $this->actingAs($scoped, 'admin')
        ->get(route('admin.payroll-runs.preview', ['payroll_period_id' => $this->period->id, 'type' => 'regular', 'scope' => ['all' => 1]]))
        ->assertOk()
        ->assertSee($this->operator->employee_number)
        ->assertDontSee($this->salesRep->employee_number);
});

it('shows the population preview before creating the run and creates it from the wizard', function () {
    $this->actingAs($this->accountant, 'admin')
        ->get(route('admin.payroll-runs.preview', ['payroll_period_id' => $this->period->id, 'type' => 'regular', 'scope' => ['branch_ids' => [$this->cairo->id]]]))
        ->assertOk()->assertSee($this->salesRep->employee_number)->assertDontSee($this->operator->employee_number);

    $this->actingAs($this->accountant, 'admin')
        ->post(route('admin.payroll-runs.store'), ['payroll_period_id' => $this->period->id, 'type' => 'regular', 'scope' => ['branch_ids' => [$this->cairo->id]]])
        ->assertRedirect();

    expect(PayrollItem::query()->withoutGlobalScopes()->pluck('employee_id')->sort()->values()->all())
        ->toBe(collect([$this->salesRep, $this->northRep, $this->developer])->pluck('id')->sort()->values()->all());
});
