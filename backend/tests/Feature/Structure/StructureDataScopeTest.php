<?php

use App\Models\Branch;
use App\Models\BranchLocation;
use App\Models\Department;
use App\Models\Position;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->cairo = Branch::factory()->create(['name_en' => 'Cairo branch']);
    $this->alex = Branch::factory()->create(['name_en' => 'Alex branch']);
    $this->cairoSales = Department::factory()->create(['branch_id' => $this->cairo->id, 'name_en' => 'Cairo sales']);
    $this->alexSales = Department::factory()->create(['branch_id' => $this->alex->id, 'name_en' => 'Alex sales']);
    $this->companyWide = Department::factory()->create(['branch_id' => null, 'name_en' => 'Group audit']);
});

function restrictedTo(string $type, int $id, string $role = 'HR Manager'): User
{
    $admin = adminWithRoles($role);
    $admin->update(['locale' => 'en']);
    $admin->dataScopes()->create(['scope_type' => $type, 'scope_id' => $id]);

    return $admin->fresh();
}

it('shows a branch-restricted admin only their branches, departments, teams and positions', function () {
    Team::factory()->for($this->alexSales)->create(['name_en' => 'Alex retail']);
    Position::factory()->for($this->alexSales)->create(['name_en' => 'Alex rep']);
    Team::factory()->for($this->cairoSales)->create(['name_en' => 'Cairo retail']);
    $admin = restrictedTo('branch', $this->cairo->id);

    $this->actingAs($admin, 'admin')->get('/admin/branches')->assertSee('Cairo branch')->assertDontSee('Alex branch');
    $this->actingAs($admin, 'admin')->get('/admin/departments')->assertSee('Cairo sales')->assertDontSee('Alex sales')->assertDontSee('Group audit');
    $this->actingAs($admin, 'admin')->get('/admin/teams')->assertSee('Cairo retail')->assertDontSee('Alex retail');
    $this->actingAs($admin, 'admin')->get('/admin/positions')->assertDontSee('Alex rep');
});

it('answers 404 for records outside the admin\'s scope', function () {
    $admin = restrictedTo('branch', $this->cairo->id);
    $location = BranchLocation::factory()->for($this->alex)->create();

    $this->actingAs($admin, 'admin')->get("/admin/branches/{$this->alex->id}")->assertNotFound();
    $this->actingAs($admin, 'admin')->get("/admin/departments/{$this->alexSales->id}/edit")->assertNotFound();
    $this->actingAs($admin, 'admin')->patch("/admin/departments/{$this->alexSales->id}/status", ['is_active' => '0'])->assertNotFound();
    $this->actingAs($admin, 'admin')->get("/admin/branches/{$this->alex->id}/locations/{$location->id}/edit")->assertNotFound();

    expect($this->alexSales->fresh()->is_active)->toBeTrue();
});

it('does not let a restricted admin link records to anything outside their scope', function () {
    $admin = restrictedTo('branch', $this->cairo->id);

    $this->actingAs($admin, 'admin')
        ->post('/admin/teams', ['code' => 'T1', 'name_ar' => 'ف', 'name_en' => 'T', 'department_id' => $this->alexSales->id])
        ->assertSessionHasErrors('department_id');

    $this->actingAs($admin, 'admin')
        ->put("/admin/departments/{$this->cairoSales->id}", [
            ...$this->cairoSales->only(['code', 'name_ar', 'name_en']), 'branch_id' => $this->alex->id,
        ])
        ->assertSessionHasErrors('branch_id');

    // Moving it out to "company-wide" would also take it out of the admin's scope.
    $this->actingAs($admin, 'admin')
        ->put("/admin/departments/{$this->cairoSales->id}", [...$this->cairoSales->only(['code', 'name_ar', 'name_en']), 'branch_id' => null])
        ->assertSessionHasErrors('branch_id');

    $this->actingAs($admin, 'admin')
        ->post('/admin/teams', ['code' => 'T2', 'name_ar' => 'ف', 'name_en' => 'T', 'department_id' => $this->cairoSales->id])
        ->assertSessionHasNoErrors();
});

it('lets only unrestricted admins add branches and departments', function () {
    $admin = restrictedTo('branch', $this->cairo->id);

    $this->actingAs($admin, 'admin')->get('/admin/branches/create')->assertForbidden();
    $this->actingAs($admin, 'admin')->post('/admin/departments', ['code' => 'D9', 'name_ar' => 'د', 'name_en' => 'D', 'branch_id' => $this->cairo->id])
        ->assertForbidden();
});

it('shows a department-restricted admin their departments and the branches they belong to', function () {
    $admin = restrictedTo('department', $this->alexSales->id, 'Department Manager');

    $this->actingAs($admin, 'admin')->get('/admin/departments')->assertSee('Alex sales')->assertDontSee('Cairo sales');
    $this->actingAs($admin, 'admin')->get('/admin/org-chart')->assertSee('Alex sales')->assertDontSee('Cairo sales');
});

it('offers only in-scope branches and departments in the admin user restriction picker', function () {
    $admin = restrictedTo('branch', $this->cairo->id, 'Super Admin');
    $restricted = adminWithPermissions('admin_users.view', 'admin_users.create');
    $restricted->update(['locale' => 'en']);
    $restricted->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => $this->cairo->id]);

    $this->actingAs($restricted->fresh(), 'admin')->get('/admin/admin-users/create')
        ->assertOk()
        ->assertSee('Cairo branch')
        ->assertDontSee('Alex branch');

    // A Super Admin is never restricted and sees every option.
    $this->actingAs($admin, 'admin')->get('/admin/admin-users/create')->assertSee(['Cairo branch', 'Alex branch']);
});

it('saves restrictions picked from real branches and shows their names', function () {
    $super = superAdmin();
    $super->update(['locale' => 'en']);

    $this->actingAs($super, 'admin')->post('/admin/admin-users', [
        'name' => 'Picker test', 'email' => 'picker@example.com', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
        'status' => 'active', 'roles' => [Role::findByName('Branch Manager', 'admin')->id],
        'scopes' => ['branch' => ['', (string) $this->alex->id], 'department' => ['']],
    ])->assertSessionHasNoErrors();

    $created = User::query()->where('email', 'picker@example.com')->sole();
    expect($created->dataScopeIds())->toBe(['branch' => [$this->alex->id]]);

    $this->actingAs($super, 'admin')->get("/admin/admin-users/{$created->id}")->assertSee($this->alex->code.' · Alex branch');

    $this->actingAs($super, 'admin')->post('/admin/admin-users', [
        'name' => 'Ghost', 'email' => 'ghost@example.com', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
        'status' => 'active', 'roles' => [Role::findByName('Branch Manager', 'admin')->id],
        'scopes' => ['branch' => ['999999']],
    ])->assertSessionHasErrors('scopes.branch.0');
});
