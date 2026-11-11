<?php

use App\Enums\AssignmentChangeType;
use App\Enums\SalaryChangeType;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeCustomField;
use App\Models\EmployeeGroup;
use App\Models\Position;
use App\Models\Team;
use App\Services\Settings\SettingsService;

beforeEach(function () {
    $this->org = hrStructure();
});

it('creates an employee with a generated number, the opening history row and defaults from the position', function () {
    $manager = Employee::factory()->inPosition($boss = Position::factory()->create(['department_id' => $this->org['department']->id, 'headcount' => 1]))->create();
    $this->org['position']->update(['reports_to_position_id' => $boss->id]);
    $team = Team::factory()->create(['department_id' => $this->org['department']->id]);

    $this->actingAs(superAdmin(), 'admin')
        ->post('/admin/employees', employeePayload($this->org, [
            'position_id' => $this->org['position']->id,
            'team_id' => $team->id,
            'basic_salary' => '15250.50',
            'emergency_contacts' => [['name' => 'Ali Ahmed', 'relationship' => 'Father', 'phone' => '+201000000000']],
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $employee = Employee::query()->where('email', 'sara.ahmed@example.test')->sole();

    expect($employee->employee_number)->toBe('EMP-00001')
        ->and($employee->job_title_id)->toBe($this->org['position']->job_title_id)
        ->and($employee->manager_id)->toBe($manager->id)
        ->and($employee->hire_date->toDateString())->toBe('2024-03-01')
        ->and($employee->basic_salary)->toBe('15250.50')
        ->and($employee->emergencyContacts)->toHaveCount(1);

    $hire = $employee->assignments()->sole();
    expect($hire->change_type)->toBe(AssignmentChangeType::Hire)
        ->and($hire->position_id)->toBe($this->org['position']->id)
        ->and($hire->applied_at)->not->toBeNull()
        ->and($employee->salaries()->sole()->change_type)->toBe(SalaryChangeType::Initial);

    expect(AuditLog::query()->where('auditable_type', 'employee')->where('event', 'created')->exists())->toBeTrue();
});

it('validates the required fields and the placement combination', function () {
    $otherBranchDepartment = Department::factory()->create(['branch_id' => Branch::factory()->create()->id]);

    $this->actingAs(superAdmin(), 'admin')
        ->post('/admin/employees', [])
        ->assertSessionHasErrors(['name_ar', 'name_en', 'hire_date', 'branch_id', 'department_id', 'employment_type_id']);

    $this->actingAs(superAdmin(), 'admin')
        ->post('/admin/employees', employeePayload($this->org, ['department_id' => $otherBranchDepartment->id]))
        ->assertSessionHasErrors(['department_id' => __('employees.validation.department_not_in_branch')]);

    expect(Employee::query()->count())->toBe(0);
});

it('refuses a duplicate national id, compared through the keyed hash', function () {
    Employee::factory()->create(['national_id' => '29001011234567']);

    $this->actingAs(superAdmin(), 'admin')
        ->post('/admin/employees', employeePayload($this->org, ['national_id' => '2900 1011 234567']))
        ->assertSessionHasErrors('national_id');
});

it('ignores the salary of the create form without employee_financials.update', function () {
    $admin = adminWithPermissions('employees.view', 'employees.create');

    $this->actingAs($admin, 'admin')
        ->post('/admin/employees', employeePayload($this->org, ['basic_salary' => '9000']))
        ->assertSessionHasNoErrors();

    $employee = Employee::query()->sole();
    expect($employee->basic_salary)->toBeNull()->and($employee->salaries()->count())->toBe(0);
});

it('edits personal data without touching the placement', function () {
    $employee = Employee::factory()->inDepartment($this->org['department'])->create(['employment_type_id' => $this->org['type']->id]);
    $other = Department::factory()->create(['branch_id' => $this->org['branch']->id]);

    $this->actingAs(superAdmin(), 'admin')
        ->put("/admin/employees/{$employee->id}", [
            'employee_number' => $employee->employee_number,
            'name_ar' => 'اسم جديد',
            'name_en' => 'New name',
            'department_id' => $other->id,
        ])
        ->assertRedirect("/admin/employees/{$employee->id}");

    $employee->refresh();
    expect($employee->name_en)->toBe('New name')
        ->and($employee->department_id)->toBe($this->org['department']->id)
        ->and($employee->assignments()->count())->toBe(1);
});

it('saves typed custom field values and group memberships', function () {
    EmployeeCustomField::query()->create(['code' => 'shirt', 'name_ar' => 'المقاس', 'name_en' => 'Shirt', 'type' => 'select', 'is_active' => true])
        ->options()->create(['value' => 'L', 'label_ar' => 'كبير', 'label_en' => 'Large']);
    EmployeeCustomField::query()->create(['code' => 'licence', 'name_ar' => 'رخصة', 'name_en' => 'Licence', 'type' => 'boolean', 'is_active' => true]);
    $group = EmployeeGroup::factory()->create();

    $this->actingAs(superAdmin(), 'admin')
        ->post('/admin/employees', employeePayload($this->org, ['custom_fields' => ['shirt' => 'XXL']]))
        ->assertSessionHasErrors('custom_fields.shirt');

    $this->actingAs(superAdmin(), 'admin')
        ->post('/admin/employees', employeePayload($this->org, ['custom_fields' => ['shirt' => 'L', 'licence' => '1'], 'groups' => [$group->id]]))
        ->assertSessionHasNoErrors();

    $employee = Employee::query()->sole();
    expect($employee->customFieldValues()->with('field')->get()->mapWithKeys(fn ($value) => [$value->field->code => $value->value()])->all())
        ->toBe(['shirt' => 'L', 'licence' => true])
        ->and($employee->groups->pluck('id')->all())->toBe([$group->id]);
});

it('lists, searches (number, name, national id) and filters employees', function () {
    $sara = Employee::factory()->inDepartment($this->org['department'])->create(['name_en' => 'Sara Adel', 'national_id' => '29001011234567']);
    $omar = Employee::factory()->inDepartment($this->org['department'])->terminated()->create(['name_en' => 'Omar Zaki']);
    $admin = superAdmin();
    $admin->update(['locale' => 'en']);
    // The row link of an employee (pickers elsewhere on the page also list names).
    $row = fn (Employee $employee): string => 'href="'.route('admin.employees.show', $employee).'"';
    $list = fn (string $query = '') => $this->actingAs($admin, 'admin')->get('/admin/employees'.$query)->assertOk();

    $list()->assertSee($row($sara), false)->assertSee($row($omar), false);
    $list('?search='.$sara->employee_number)->assertSee($row($sara), false)->assertDontSee($row($omar), false);
    $list('?search=omar')->assertSee($row($omar), false)->assertDontSee($row($sara), false);
    $list('?search=2900-1011-234567')->assertSee($row($sara), false)->assertDontSee($row($omar), false);
    $list('?search=2900101')->assertDontSee($row($sara), false);
    $list('?status=terminated')->assertSee($row($omar), false)->assertDontSee($row($sara), false);
    $list('?sort=name&direction=desc')->assertSeeInOrder([$row($sara), $row($omar)], false);
});

it('renders every profile tab and the create form', function (string $tab) {
    $employee = Employee::factory()->inDepartment($this->org['department'])->create();

    $this->actingAs(superAdmin(), 'admin')->get("/admin/employees/{$employee->id}/tab/{$tab}")->assertOk()->assertSee($employee->employee_number);
})->with(['personal', 'employment', 'contracts', 'documents', 'financial', 'compensation', 'leave', 'history']);

it('renders the create and edit forms', function () {
    $employee = Employee::factory()->inDepartment($this->org['department'])->create();

    $this->actingAs(superAdmin(), 'admin')->get('/admin/employees/create')->assertOk()->assertSee('name="branch_id"', false);
    $this->actingAs(superAdmin(), 'admin')->get("/admin/employees/{$employee->id}/edit")->assertOk()->assertDontSee('name="branch_id"', false);
});

it('needs the employee permissions', function () {
    $employee = Employee::factory()->create();
    $viewer = adminWithPermissions('employees.view');

    $this->actingAs(adminWithPermissions('dashboard.view'), 'admin')->get('/admin/employees')->assertForbidden();
    $this->actingAs($viewer, 'admin')->get('/admin/employees/create')->assertForbidden();
    $this->actingAs($viewer, 'admin')->post('/admin/employees', employeePayload($this->org))->assertForbidden();
    $this->actingAs($viewer, 'admin')->put("/admin/employees/{$employee->id}", ['name_ar' => 'x', 'name_en' => 'x'])->assertForbidden();
    $this->actingAs($viewer, 'admin')->get("/admin/employees/{$employee->id}")->assertOk();
});

it('generates numbers from the configured pattern', function () {
    app(SettingsService::class)->setMany(['employee_number.pattern' => '{branch}-{year}-{number}', 'employee_number.padding' => 3]);

    $this->actingAs(superAdmin(), 'admin')->post('/admin/employees', employeePayload($this->org))->assertSessionHasNoErrors();

    expect(Employee::query()->sole()->employee_number)->toBe('CAI-2024-001');
});
