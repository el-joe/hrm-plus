<?php

use App\Enums\DataScopeType;
use App\Enums\EmployeeStatus;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeGroup;
use App\Models\EmploymentType;
use App\Models\Position;
use App\Models\Team;
use App\Models\User;
use App\Services\Authorization\DataScopeManager;

beforeEach(function () {
    $this->cairo = Branch::factory()->create(['code' => 'CAI']);
    $this->alex = Branch::factory()->create(['code' => 'ALX']);
    $this->it = Department::factory()->create(['code' => 'IT', 'branch_id' => $this->cairo->id]);
    $this->backend = Department::factory()->create(['code' => 'IT-BE', 'branch_id' => $this->cairo->id, 'parent_id' => $this->it->id]);
    $this->apis = Department::factory()->create(['code' => 'IT-API', 'branch_id' => $this->cairo->id, 'parent_id' => $this->backend->id]);
    $this->sales = Department::factory()->create(['code' => 'SAL', 'branch_id' => $this->alex->id]);

    $this->itPerson = Employee::factory()->inDepartment($this->it)->create(['name_en' => 'It Person']);
    $this->apiPerson = Employee::factory()->inDepartment($this->apis)->create(['name_en' => 'Api Person']);
    $this->salesPerson = Employee::factory()->inDepartment($this->sales)->create(['name_en' => 'Sales Person']);
});

function scopedAdmin(DataScopeType $type, int $id, string ...$permissions): User
{
    $admin = adminWithPermissions(...($permissions ?: ['employees.view', 'employees.update', 'departments.view', 'branches.view']));
    $admin->dataScopes()->create(['scope_type' => $type, 'scope_id' => $id]);

    return $admin->fresh();
}

function visibleNames(User $admin): array
{
    return Employee::query()->visibleTo($admin)->orderBy('name_en')->pluck('name_en')->all();
}

it('includes every sub-department in a department restriction, at any depth', function () {
    $admin = scopedAdmin(DataScopeType::Department, $this->it->id);

    expect(visibleNames($admin))->toBe(['Api Person', 'It Person'])
        ->and(app(DataScopeManager::class)->effectiveScopeIds($admin)['department'])->toBe([$this->it->id, $this->backend->id, $this->apis->id]);

    $this->actingAs($admin, 'admin')->get("/admin/departments/{$this->apis->id}")->assertOk();
    $this->actingAs($admin, 'admin')->get("/admin/employees/{$this->apiPerson->id}")->assertOk();
    $this->actingAs($admin, 'admin')->get("/admin/employees/{$this->salesPerson->id}")->assertNotFound();
});

it('restricts by branch and answers 404 for employees outside it', function () {
    $admin = scopedAdmin(DataScopeType::Branch, $this->alex->id);

    expect(visibleNames($admin))->toBe(['Sales Person']);
    $this->actingAs($admin, 'admin')->get("/admin/employees/{$this->itPerson->id}/edit")->assertNotFound();
    $this->actingAs($admin, 'admin')->patch("/admin/employees/{$this->itPerson->id}/status", ['status' => 'inactive'])->assertNotFound();
    expect($this->itPerson->fresh()->status)->toBe(EmployeeStatus::Active);
});

it('makes an employee-group restriction actually filter employees', function () {
    $group = EmployeeGroup::factory()->create();
    $this->salesPerson->groups()->attach($group, ['created_at' => now()]);
    $admin = scopedAdmin(DataScopeType::EmployeeGroup, $group->id);

    expect(visibleNames($admin))->toBe(['Sales Person']);
    $this->actingAs($admin, 'admin')->get('/admin/employees')->assertOk()
        ->assertSee(route('admin.employees.show', $this->salesPerson))
        ->assertDontSee(route('admin.employees.show', $this->itPerson).'"', false);
});

it('offers employee groups and employee profiles as pickers on the admin user form', function () {
    $group = EmployeeGroup::factory()->create(['code' => 'NIGHT']);

    $this->actingAs(superAdmin(), 'admin')->get('/admin/admin-users/create')->assertOk()
        ->assertSee('name="scopes[employee_group][]"', false)
        ->assertSee('NIGHT')
        ->assertSee('<select id="employee_id" name="employee_id"', false)
        ->assertSee($this->itPerson->employee_number);

    expect(app(DataScopeManager::class)->describe(['employee_group' => [$group->id]])['employee_group'][0])->toStartWith('NIGHT · ');
});

it('does not let a restricted admin place an employee outside their scope', function () {
    $type = EmploymentType::factory()->create();
    $admin = scopedAdmin(DataScopeType::Branch, $this->cairo->id, 'employees.view', 'employees.create', 'branches.view', 'departments.view');

    $this->actingAs($admin, 'admin')->post('/admin/employees', employeePayload(
        ['branch' => $this->alex, 'department' => $this->sales, 'type' => $type],
    ))->assertSessionHasErrors(['branch_id', 'department_id']);

    $this->actingAs($admin, 'admin')->post('/admin/employees', employeePayload(
        ['branch' => $this->cairo, 'department' => $this->apis, 'type' => $type],
    ))->assertSessionHasNoErrors();
});

it('runs bulk operations as individual history entries, skipping what does not fit', function () {
    $group = EmployeeGroup::factory()->create();
    $terminated = Employee::factory()->inDepartment($this->it)->terminated()->create();
    $ids = [$this->itPerson->id, $this->apiPerson->id, $terminated->id];

    $this->actingAs(superAdmin(), 'admin')->post('/admin/employees/bulk', ['action' => 'assign_department', 'department_id' => $this->backend->id, 'ids' => $ids])
        ->assertSessionHas('status')->assertSessionHas('bulkSkipped');

    expect($this->itPerson->fresh()->department_id)->toBe($this->backend->id)
        ->and($this->itPerson->assignments()->latest('id')->first()->change_type->value)->toBe('transfer')
        ->and($terminated->fresh()->department_id)->toBe($this->it->id);

    $this->actingAs(superAdmin(), 'admin')->post('/admin/employees/bulk', ['action' => 'add_to_group', 'group_id' => $group->id, 'ids' => $ids]);
    expect($group->employees()->count())->toBe(3);

    $this->actingAs(superAdmin(), 'admin')->post('/admin/employees/bulk', ['action' => 'deactivate', 'ids' => [$this->itPerson->id]]);
    expect($this->itPerson->fresh()->status)->toBe(EmployeeStatus::Inactive);

    $this->actingAs(superAdmin(), 'admin')->post('/admin/employees/bulk', ['action' => 'assign_manager', 'manager_id' => $this->apiPerson->id, 'ids' => [$this->salesPerson->id, $this->apiPerson->id]]);
    expect($this->salesPerson->fresh()->manager_id)->toBe($this->apiPerson->id)
        ->and($this->apiPerson->fresh()->manager_id)->toBeNull();
});

it('only touches employees in the admin\'s scope in bulk operations', function () {
    $admin = scopedAdmin(DataScopeType::Branch, $this->cairo->id, 'employees.view', 'employees.update', 'departments.view');

    $this->actingAs($admin, 'admin')->post('/admin/employees/bulk', ['action' => 'deactivate', 'ids' => [$this->itPerson->id, $this->salesPerson->id]]);

    expect($this->itPerson->fresh()->status)->toBe(EmployeeStatus::Inactive)
        ->and($this->salesPerson->fresh()->status)->toBe(EmployeeStatus::Active);

    $this->actingAs(adminWithPermissions('employees.view'), 'admin')->post('/admin/employees/bulk', ['action' => 'activate', 'ids' => [$this->itPerson->id]])->assertForbidden();
});

it('treats structure records used by employees as in use', function () {
    $team = Team::factory()->create(['department_id' => $this->sales->id]);
    $this->salesPerson->forceFill(['team_id' => $team->id])->save();

    foreach (["/admin/departments/{$this->sales->id}", "/admin/teams/{$team->id}", "/admin/employment-types/{$this->salesPerson->employment_type_id}"] as $url) {
        $this->actingAs(superAdmin(), 'admin')->delete($url)->assertSessionHasErrors('record');
    }

    expect($this->sales->fresh())->not->toBeNull()
        ->and($this->sales->usageCounts()['employees'])->toBe(1);
});

it('shows position holders and department heads on the organisation chart, and a manager tree', function () {
    $head = Position::factory()->create(['department_id' => $this->it->id, 'headcount' => 1]);
    $this->it->update(['head_position_id' => $head->id]);
    $boss = Employee::factory()->inPosition($head)->create(['name_en' => 'Head Of It']);
    $this->apiPerson->forceFill(['manager_id' => $boss->id])->save();
    $admin = superAdmin();
    $admin->update(['locale' => 'en']);

    $this->actingAs($admin, 'admin')->get('/admin/org-chart')->assertOk()->assertSee('Head Of It');
    $this->actingAs($admin, 'admin')->get('/admin/org-chart?view=positions')->assertOk()->assertSee('Head Of It')->assertSee('1 / 1');
    $this->actingAs($admin, 'admin')->get('/admin/org-chart?view=people')->assertOk()->assertSeeInOrder(['Head Of It', 'Api Person']);
});

it('sets a team leader from the employees in scope', function () {
    $team = Team::factory()->create(['department_id' => $this->it->id]);

    $this->actingAs(superAdmin(), 'admin')->put("/admin/teams/{$team->id}", [
        ...$team->only(['code', 'name_ar', 'name_en', 'department_id']), 'leader_employee_id' => $this->itPerson->id,
    ])->assertSessionHasNoErrors();

    expect($team->fresh()->leader->is($this->itPerson))->toBeTrue();

    $admin = scopedAdmin(DataScopeType::Branch, $this->cairo->id, 'teams.view', 'teams.update', 'departments.view');
    $this->actingAs($admin, 'admin')->put("/admin/teams/{$team->id}", [
        ...$team->only(['code', 'name_ar', 'name_en', 'department_id']), 'leader_employee_id' => $this->salesPerson->id,
    ])->assertSessionHasErrors('leader_employee_id');
});
