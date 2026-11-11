<?php

use App\Actions\Employees\TerminateEmployee;
use App\Enums\EmployeeStatus;
use App\Enums\OffboardingSource;
use App\Enums\OffboardingStatus;
use App\Enums\TerminationType;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Models\User;
use App\Notifications\Lifecycle\OffboardingCaseOpened;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedRecruitment();
    $this->org = hrStructure();
    $this->employee = Employee::factory()->inPosition($this->org['position'])->create([
        'employment_type_id' => $this->org['type']->id, 'hire_date' => '2023-01-01', 'original_hire_date' => '2023-01-01',
    ]);
    $this->employeeAccount = User::factory()->employee()->create(['employee_id' => $this->employee->id]);
    // An employee profile has at most one login; this one is an admin who is also an employee.
    $this->adminEmployee = Employee::factory()->create(['hire_date' => '2023-01-01', 'original_hire_date' => '2023-01-01']);
    $this->adminAccount = User::factory()->admin()->linkedToEmployee($this->adminEmployee->id)->create();
    $this->hr = adminWithRoles('HR Manager');
});

function openCase(object $test, array $overrides = [], ?Employee $employee = null): OffboardingCase
{
    $test->actingAs($test->hr, 'admin')->post('/admin/offboarding', [
        'employee_id' => ($employee ?? $test->employee)->id, 'type' => 'resignation', 'last_working_date' => today()->toDateString(), 'reason' => 'Relocation', 'eligible_for_rehire' => '1', ...$overrides,
    ])->assertSessionHasNoErrors();

    return OffboardingCase::query()->latest('id')->firstOrFail();
}

it('opens a case with the generic handover checklist and only one open case per employee', function () {
    $case = openCase($this);

    expect($case->items()->count())->toBe(count(config('lifecycle.generic_handover')))
        ->and($case->items()->pluck('category')->map->value->unique()->values()->all())->toContain('asset', 'document', 'access', 'finance');

    $this->actingAs($this->hr, 'admin')->post('/admin/offboarding', ['employee_id' => $this->employee->id, 'type' => 'dismissal', 'last_working_date' => today()->toDateString()])
        ->assertSessionHasErrors('employee_id');
});

it('cannot be completed while handover items are open, then terminates the employee and switches off every linked login', function () {
    $case = openCase($this);
    $this->employeeAccount->createToken('phone');

    $this->actingAs($this->hr, 'admin')->post("/admin/offboarding/{$case->id}/exit-interview", [
        'answers' => ['primary_reason' => 'relocation', 'rating_management' => 4, 'would_recommend' => '1'], 'notes' => 'Good experience',
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->hr, 'admin')->post("/admin/offboarding/{$case->id}/complete")->assertSessionHasErrors('items');
    expect($this->employee->fresh()->status)->toBe(EmployeeStatus::Active);

    foreach ($case->items as $item) {
        $this->actingAs($this->hr, 'admin')->patch("/admin/offboarding/{$case->id}/items/{$item->id}", ['status' => 'done'])->assertSessionHasNoErrors();
    }
    $this->actingAs($this->hr, 'admin')->post("/admin/offboarding/{$case->id}/complete")->assertSessionHasNoErrors();

    $employee = $this->employee->fresh();
    expect($case->fresh()->status)->toBe(OffboardingStatus::Completed)
        ->and($case->fresh()->exit_interview_answers['primary_reason'])->toBe('relocation')
        ->and($employee->status)->toBe(EmployeeStatus::Terminated)
        ->and($employee->termination_type)->toBe(TerminationType::Resignation)
        ->and($employee->eligible_for_rehire)->toBeTrue()
        ->and($this->employeeAccount->fresh()->status)->toBe(UserStatus::Inactive)
        ->and($this->employeeAccount->tokens()->count())->toBe(0)
        ->and($employee->assignments()->count())->toBe(2);
});

it('needs offboarding.approve and employees.manage to complete a case', function () {
    $case = openCase($this);
    $case->items()->update(['status' => 'done']);

    $this->actingAs(adminWithRoles('HR Specialist'), 'admin')->post("/admin/offboarding/{$case->id}/complete")->assertForbidden();
    expect($this->employee->fresh()->isTerminated())->toBeFalse();
});

it('respects a future last working day: the linked admin login stays on until the scheduled termination applies', function () {
    $case = openCase($this, ['last_working_date' => today()->addDays(10)->toDateString()], $this->adminEmployee);
    $case->items()->update(['status' => 'skipped']);

    $this->actingAs($this->hr, 'admin')->post("/admin/offboarding/{$case->id}/complete")->assertSessionHasNoErrors();

    expect($this->adminEmployee->fresh()->status)->toBe(EmployeeStatus::Active)
        ->and($this->adminAccount->fresh()->status)->toBe(UserStatus::Active);

    $this->travel(11)->days();
    $this->artisan('employees:apply-scheduled-changes')->assertSuccessful();

    expect($this->adminEmployee->fresh()->status)->toBe(EmployeeStatus::Terminated)
        ->and($this->adminAccount->fresh()->status)->toBe(UserStatus::Inactive)
        ->and($this->employeeAccount->fresh()->status)->toBe(UserStatus::Active);
});

it('disables a linked admin account when an employee is terminated directly, and does not re-enable it on rehire', function () {
    $this->adminAccount->createToken('cli');
    app(TerminateEmployee::class)->handle($this->adminEmployee, ['termination_date' => today()->subDays(5)->toDateString(), 'termination_type' => 'dismissal', 'reason' => 'Misconduct']);

    expect($this->adminAccount->fresh()->status)->toBe(UserStatus::Inactive)
        ->and($this->adminAccount->tokens()->count())->toBe(0)
        ->and($this->employeeAccount->fresh()->status)->toBe(UserStatus::Active);

    $this->actingAs(superAdmin(), 'admin')->post("/admin/employees/{$this->adminEmployee->id}/rehire", [
        ...$this->adminEmployee->fresh()->only(['branch_id', 'department_id', 'position_id', 'employment_type_id']),
        'rehire_date' => today()->toDateString(), 'reason' => 'Returned',
    ])->assertSessionHasNoErrors();

    expect($this->adminAccount->fresh()->status)->toBe(UserStatus::Inactive);
});

it('opens one contract-end case per expiring contract and notifies HR in scope', function () {
    Notification::fake();
    $contract = $this->employee->contracts()->create(['contract_type' => 'fixed_term', 'start_date' => '2025-01-01', 'end_date' => today()->addDays(20)->toDateString()]);
    $later = Employee::factory()->create();
    $later->contracts()->create(['contract_type' => 'fixed_term', 'start_date' => '2025-01-01', 'end_date' => today()->addDays(90)->toDateString()]);

    $this->artisan('offboarding:detect-contract-expiries')->assertSuccessful();
    $this->artisan('offboarding:detect-contract-expiries')->assertSuccessful();

    $case = OffboardingCase::query()->sole();
    expect($case->employee_id)->toBe($this->employee->id)
        ->and($case->source)->toBe(OffboardingSource::ContractExpiry)
        ->and($case->type)->toBe(TerminationType::ContractEnd)
        ->and($case->employee_contract_id)->toBe($contract->id)
        ->and($case->last_working_date->toDateString())->toBe(today()->addDays(20)->toDateString());
    Notification::assertSentTo($this->hr, OffboardingCaseOpened::class);
    Notification::assertNotSentTo(adminWithRoles('Recruiter'), OffboardingCaseOpened::class);
});

it('scopes offboarding cases through the employee', function () {
    $case = openCase($this);
    $outsider = adminWithRoles('HR Manager');
    $outsider->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => Branch::factory()->create()->id]);

    $this->actingAs($outsider, 'admin')->get("/admin/offboarding/{$case->id}")->assertNotFound();
    $this->actingAs($this->hr, 'admin')->get("/admin/offboarding/{$case->id}")->assertOk()->assertSee($this->employee->name);
});
