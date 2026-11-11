<?php

use App\Enums\AssignmentChangeType;
use App\Enums\EmployeeStatus;
use App\Enums\SalaryChangeType;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\JobGrade;
use App\Models\Position;
use App\Models\User;
use App\Services\Employees\EmploymentHistory;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->org = hrStructure();
    $this->employee = Employee::factory()->inPosition($this->org['position'])->create([
        'employment_type_id' => $this->org['type']->id, 'hire_date' => '2022-01-10', 'original_hire_date' => '2022-01-10',
    ]);
    $this->sales = Department::factory()->create(['branch_id' => $this->org['branch']->id]);
});

/**
 * @return array<string, mixed>
 */
function placementOf(Employee $employee, array $changes = []): array
{
    return [...$employee->only(['branch_id', 'department_id', 'team_id', 'position_id', 'job_title_id', 'job_grade_id', 'employment_type_id', 'cost_center_id', 'manager_id', 'work_location_id']), ...$changes];
}

it('records a transfer as a new row and keeps the previous placement', function () {
    $hire = $this->employee->assignments()->sole();

    $this->actingAs(superAdmin(), 'admin')->post("/admin/employees/{$this->employee->id}/assignments", [
        ...placementOf($this->employee, ['department_id' => $this->sales->id, 'position_id' => null]),
        'change_type' => 'transfer', 'effective_date' => today()->toDateString(), 'reason' => 'Business need',
    ])->assertSessionHasNoErrors();

    $this->employee->refresh();
    expect($this->employee->department_id)->toBe($this->sales->id)
        ->and($this->employee->position_id)->toBeNull()
        ->and($this->employee->assignments()->count())->toBe(2)
        ->and($hire->fresh()->department_id)->toBe($this->org['department']->id)
        ->and($hire->fresh()->position_id)->toBe($this->org['position']->id);

    $this->actingAs(superAdmin(), 'admin')->get("/admin/employees/{$this->employee->id}/tab/history")
        ->assertOk()->assertSee(__('employees.enums.change_types.transfer'))->assertSee('Business need');
});

it('records a promotion with the new grade', function () {
    $grade = JobGrade::factory()->create();

    $this->actingAs(superAdmin(), 'admin')->post("/admin/employees/{$this->employee->id}/assignments", [
        ...placementOf($this->employee, ['job_grade_id' => $grade->id]),
        'change_type' => 'promotion', 'effective_date' => today()->toDateString(),
    ])->assertSessionHasNoErrors();

    expect($this->employee->fresh()->job_grade_id)->toBe($grade->id)
        ->and($this->employee->assignments()->latest('id')->first()->change_type)->toBe(AssignmentChangeType::Promotion);
});

it('refuses a change that changes nothing, and a manager who reports to the employee', function () {
    $report = Employee::factory()->inDepartment($this->org['department'])->create(['manager_id' => $this->employee->id]);

    $this->actingAs(superAdmin(), 'admin')->post("/admin/employees/{$this->employee->id}/assignments", [
        ...placementOf($this->employee), 'change_type' => 'transfer', 'effective_date' => today()->toDateString(),
    ])->assertSessionHasErrors('change_type');

    $this->actingAs(superAdmin(), 'admin')->post("/admin/employees/{$this->employee->id}/assignments", [
        ...placementOf($this->employee, ['manager_id' => $report->id]), 'change_type' => 'manager_change', 'effective_date' => today()->toDateString(),
    ])->assertSessionHasErrors(['manager_id' => __('employees.validation.manager_cycle')]);
});

it('schedules a future change and applies it on its date', function () {
    $date = today()->addDays(10);

    $this->actingAs(superAdmin(), 'admin')->post("/admin/employees/{$this->employee->id}/assignments", [
        ...placementOf($this->employee, ['department_id' => $this->sales->id, 'position_id' => null]),
        'change_type' => 'transfer', 'effective_date' => $date->toDateString(),
    ])->assertSessionHasNoErrors();

    expect($this->employee->fresh()->department_id)->toBe($this->org['department']->id)
        ->and(EmployeeAssignment::query()->pending()->count())->toBe(1);

    $this->artisan('employees:apply-scheduled-changes')->assertSuccessful();
    expect($this->employee->fresh()->department_id)->toBe($this->org['department']->id);

    Carbon::setTestNow($date->copy()->setTime(1, 0));
    $this->artisan('employees:apply-scheduled-changes')->assertSuccessful();
    Carbon::setTestNow();

    expect($this->employee->fresh()->department_id)->toBe($this->sales->id)
        ->and(EmployeeAssignment::query()->pending()->count())->toBe(0);
});

it('cancels a scheduled change without deleting it', function () {
    $pending = app(EmploymentHistory::class)->recordAssignment($this->employee, AssignmentChangeType::Transfer,
        ['department_id' => $this->sales->id, 'position_id' => null], today()->addMonth());

    $this->actingAs(superAdmin(), 'admin')->delete("/admin/employees/{$this->employee->id}/assignments/{$pending->id}")->assertRedirect();

    expect($pending->fresh()->cancelled_at)->not->toBeNull();
    $this->artisan('employees:apply-scheduled-changes');
    Carbon::setTestNow(today()->addMonths(2));
    app(EmploymentHistory::class)->applyDue();
    Carbon::setTestNow();
    expect($this->employee->fresh()->department_id)->toBe($this->org['department']->id);
});

it('keeps the newer placement when a back-dated change is recorded', function () {
    $history = app(EmploymentHistory::class);
    $history->recordAssignment($this->employee, AssignmentChangeType::Transfer, ['department_id' => $this->sales->id, 'position_id' => null], today()->subDays(5));
    $history->recordAssignment($this->employee, AssignmentChangeType::ManagerChange, ['manager_id' => null, 'department_id' => $this->org['department']->id], today()->subYear());

    expect($this->employee->fresh()->department_id)->toBe($this->sales->id)
        ->and($this->employee->assignments()->count())->toBe(3);
});

it('terminates: status, account, open contract and history, nothing deleted', function () {
    $account = User::factory()->employee()->create(['employee_id' => $this->employee->id]);
    $contract = $this->employee->contracts()->create(['contract_type' => 'permanent', 'start_date' => '2022-01-10']);
    $this->employee->documents()->create(['category' => 'cv', 'title' => 'CV', 'file_path' => 'x', 'file_name' => 'cv.pdf', 'file_mime' => 'application/pdf', 'file_size' => 1]);

    $this->actingAs(adminWithPermissions('employees.view', 'employees.update'), 'admin')
        ->post("/admin/employees/{$this->employee->id}/terminate", ['termination_date' => today()->toDateString(), 'termination_type' => 'resignation', 'reason' => 'Moved'])
        ->assertForbidden();

    $this->actingAs(superAdmin(), 'admin')->post("/admin/employees/{$this->employee->id}/terminate", [
        'termination_date' => today()->toDateString(), 'termination_type' => 'resignation', 'reason' => 'Moved abroad', 'eligible_for_rehire' => '1',
    ])->assertSessionHasNoErrors();

    $employee = $this->employee->fresh();
    expect($employee->status)->toBe(EmployeeStatus::Terminated)
        ->and($employee->termination_reason)->toBe('Moved abroad')
        ->and($employee->eligible_for_rehire)->toBeTrue()
        ->and($account->fresh()->status)->toBe(UserStatus::Inactive)
        ->and($contract->fresh()->terminated_on->isToday())->toBeTrue()
        ->and($employee->documents()->count())->toBe(1)
        ->and($employee->assignments()->pluck('change_type')->map->value->all())->toBe(['hire', 'termination']);
});

it('frees the seat of a terminated employee and rehires under the same number with the history kept', function () {
    $number = $this->employee->employee_number;
    app(EmploymentHistory::class)->recordAssignment($this->employee, AssignmentChangeType::Termination, ['status' => 'terminated'], today()->subMonths(2), 'Left',
        ['termination_type' => 'resignation', 'eligible_for_rehire' => true]);
    $account = User::factory()->employee()->inactive()->create(['employee_id' => $this->employee->id]);

    $this->actingAs(superAdmin(), 'admin')->post("/admin/employees/{$this->employee->id}/rehire", [
        ...placementOf($this->employee->fresh()), 'rehire_date' => today()->toDateString(), 'reason' => 'Returned',
    ])->assertSessionHasNoErrors();

    $employee = $this->employee->fresh();
    expect($employee->status)->toBe(EmployeeStatus::Active)
        ->and($employee->employee_number)->toBe($number)
        ->and($employee->hire_date->isToday())->toBeTrue()
        ->and($employee->original_hire_date->toDateString())->toBe('2022-01-10')
        ->and($employee->termination_date)->toBeNull()
        ->and($account->fresh()->status)->toBe(UserStatus::Active)
        ->and($employee->assignments()->pluck('change_type')->map->value->all())->toBe(['hire', 'termination', 'rehire']);
});

it('activates and deactivates through status history rows', function () {
    $this->actingAs(superAdmin(), 'admin')->patch("/admin/employees/{$this->employee->id}/status", ['status' => 'inactive'])->assertRedirect();
    expect($this->employee->fresh()->status)->toBe(EmployeeStatus::Inactive);

    $this->actingAs(superAdmin(), 'admin')->patch("/admin/employees/{$this->employee->id}/status", ['status' => 'active'])->assertRedirect();
    expect($this->employee->fresh()->status)->toBe(EmployeeStatus::Active)
        ->and($this->employee->assignments()->where('change_type', 'status_change')->count())->toBe(2);
});

it('records salary changes with bcmath, derives the type and never overwrites earlier rows', function () {
    $history = app(EmploymentHistory::class);
    $first = $history->recordSalary($this->employee, '10000.10', SalaryChangeType::Initial, Carbon::parse('2022-01-10'));

    $this->actingAs(superAdmin(), 'admin')->post("/admin/employees/{$this->employee->id}/salaries", [
        'basic_salary' => '9999.99', 'effective_date' => today()->toDateString(), 'reason' => 'Correction',
    ])->assertSessionHasNoErrors();

    $latest = $this->employee->salaries()->latest('id')->first();
    expect($latest->change_type)->toBe(SalaryChangeType::Decrease)
        ->and($latest->previous_salary)->toBe('10000.10')
        ->and($this->employee->fresh()->basic_salary)->toBe('9999.99')
        ->and($first->fresh()->basic_salary)->toBe('10000.10');
});

it('enforces the position headcount on placement and on the position itself', function () {
    $full = Position::factory()->create(['department_id' => $this->org['department']->id, 'headcount' => 1]);
    Employee::factory()->inPosition($full)->create();
    Employee::factory()->inPosition($this->org['position'])->create();

    $this->actingAs(superAdmin(), 'admin')->post('/admin/employees', employeePayload($this->org, ['position_id' => $full->id]))
        ->assertSessionHasErrors('position_id');

    $this->actingAs(superAdmin(), 'admin')->put("/admin/positions/{$this->org['position']->id}", [
        ...$this->org['position']->only(['code', 'name_ar', 'name_en', 'department_id', 'job_title_id']), 'headcount' => 1,
    ])->assertSessionHasErrors('headcount');

    // A terminated holder does not take a seat.
    app(EmploymentHistory::class)->recordAssignment($this->employee, AssignmentChangeType::Termination, ['status' => 'terminated'], today(), 'x');
    $this->actingAs(superAdmin(), 'admin')->put("/admin/positions/{$this->org['position']->id}", [
        ...$this->org['position']->only(['code', 'name_ar', 'name_en', 'department_id', 'job_title_id']), 'headcount' => 1,
    ])->assertSessionHasNoErrors();
});

it('counts scheduled moves into a position as taken seats', function () {
    $single = Position::factory()->create(['department_id' => $this->sales->id, 'headcount' => 1]);
    app(EmploymentHistory::class)->recordAssignment($this->employee, AssignmentChangeType::Transfer,
        ['department_id' => $this->sales->id, 'position_id' => $single->id], today()->addWeek());

    $this->actingAs(superAdmin(), 'admin')->post('/admin/employees', employeePayload($this->org, [
        'department_id' => $this->sales->id, 'position_id' => $single->id,
    ]))->assertSessionHasErrors('position_id');
});
