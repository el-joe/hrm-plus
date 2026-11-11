<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\EmploymentType;
use App\Models\JobGrade;
use App\Models\JobTitle;
use App\Models\Position;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/*
| Create / list / update / activate / delete for every organisation-structure
| record, driven by one dataset: [model, URL segment, permission module, valid payload].
*/

dataset('structure records', [
    'job title' => [JobTitle::class, 'job-titles', 'job_titles', fn () => ['code' => 'acc-1', 'name_ar' => 'محاسب', 'name_en' => 'Accountant', 'description' => 'Books']],
    'job grade' => [JobGrade::class, 'job-grades', 'job_grades', fn () => ['code' => 'G9', 'name_ar' => 'درجة', 'name_en' => 'Grade nine', 'level' => 9, 'min_salary' => '1000.50', 'max_salary' => '2000.00']],
    'employment type' => [EmploymentType::class, 'employment-types', 'employment_types', fn () => ['code' => 'FT', 'name_ar' => 'دوام كامل', 'name_en' => 'Full-time', 'is_fixed_term' => '1']],
    'cost centre' => [CostCenter::class, 'cost-centers', 'cost_centers', fn () => ['code' => 'CC-9', 'name_ar' => 'مركز', 'name_en' => 'Centre', 'parent_id' => CostCenter::factory()->create()->id]],
    'team' => [Team::class, 'teams', 'teams', fn () => ['code' => 'TM-1', 'name_ar' => 'فريق', 'name_en' => 'Team', 'department_id' => Department::factory()->create()->id]],
    'position' => [Position::class, 'positions', 'positions', fn () => [
        'code' => 'POS-1', 'name_ar' => 'وظيفة', 'name_en' => 'Position', 'headcount' => 3,
        'department_id' => Department::factory()->create()->id, 'job_title_id' => JobTitle::factory()->create()->id,
    ]],
    'department' => [Department::class, 'departments', 'departments', fn () => ['code' => 'DEP-1', 'name_ar' => 'إدارة', 'name_en' => 'Department', 'branch_id' => Branch::factory()->create()->id]],
    'branch' => [Branch::class, 'branches', 'branches', fn () => ['code' => 'BR-1', 'name_ar' => 'فرع', 'name_en' => 'Branch', 'country_code' => 'eg', 'timezone' => 'Africa/Cairo']],
]);

/**
 * @param  class-string<Model>  $model
 */
function structureAdmin(string $module, array $actions = ['view', 'create', 'update', 'delete']): User
{
    return adminWithPermissions(...array_map(fn (string $action): string => "{$module}.{$action}", $actions));
}

it('lists, creates and updates a record and audits both changes', function (string $model, string $segment, string $module, Closure $payload) {
    $admin = structureAdmin($module);
    $data = $payload();

    $this->actingAs($admin, 'admin')->get("/admin/{$segment}")->assertOk();
    $this->actingAs($admin, 'admin')->get("/admin/{$segment}/create")->assertOk()->assertSee('name="code"', false);

    $this->actingAs($admin, 'admin')->post("/admin/{$segment}", $data)->assertSessionHasNoErrors()->assertRedirect();

    $record = $model::query()->where('code', strtoupper($data['code']))->sole();
    expect($record->name_en)->toBe($data['name_en'])->and($record->is_active)->toBeTrue();

    $this->actingAs($admin, 'admin')->get("/admin/{$segment}")->assertSee($record->name);
    $this->actingAs($admin, 'admin')->get("/admin/{$segment}/{$record->id}/edit")->assertOk()->assertSee($record->name_ar);

    $this->actingAs($admin, 'admin')
        ->put("/admin/{$segment}/{$record->id}", [...$data, 'name_en' => 'Renamed', 'is_active' => '0'])
        ->assertSessionHasNoErrors();

    expect($record->fresh())->name_en->toBe('Renamed')->is_active->toBeFalse();

    $logs = AuditLog::query()->where('auditable_type', $record->getMorphClass())->where('auditable_id', $record->id)->orderBy('id')->get();
    expect($logs->pluck('event')->all())->toBe(['created', 'updated'])
        ->and($logs[1]->old_values)->toMatchArray(['name_en' => $data['name_en'], 'is_active' => 1])
        ->and($logs[1]->new_values)->toMatchArray(['name_en' => 'Renamed', 'is_active' => false])
        ->and($logs[1]->user_id)->toBe($admin->id);
})->with('structure records');

it('rejects a duplicate code and missing names', function (string $model, string $segment, string $module, Closure $payload) {
    $admin = structureAdmin($module);
    $data = $payload();
    $this->actingAs($admin, 'admin')->post("/admin/{$segment}", $data);

    $this->actingAs($admin, 'admin')
        ->post("/admin/{$segment}", [...$data, 'code' => strtolower($data['code']), 'name_ar' => '', 'name_en' => ''])
        ->assertSessionHasErrors(['code', 'name_ar', 'name_en']);

    expect($model::query()->where('code', strtoupper($data['code']))->count())->toBe(1);
})->with('structure records');

it('refuses every action without the module permission', function (string $model, string $segment, string $module, Closure $payload) {
    $record = $model::factory()->create();
    $viewer = structureAdmin($module, ['view']);
    $nobody = adminWithPermissions('dashboard.view');

    $this->actingAs($nobody, 'admin')->get("/admin/{$segment}")->assertForbidden();
    $this->actingAs($viewer, 'admin')->get("/admin/{$segment}")->assertOk()->assertDontSee(route("admin.{$segment}.create"));
    $this->actingAs($viewer, 'admin')->get("/admin/{$segment}/create")->assertForbidden();
    $this->actingAs($viewer, 'admin')->post("/admin/{$segment}", $payload())->assertForbidden();
    $this->actingAs($viewer, 'admin')->get("/admin/{$segment}/{$record->id}/edit")->assertForbidden();
    $this->actingAs($viewer, 'admin')->put("/admin/{$segment}/{$record->id}", $payload())->assertForbidden();
    $this->actingAs($viewer, 'admin')->patch("/admin/{$segment}/{$record->id}/status", ['is_active' => '0'])->assertForbidden();
    $this->actingAs($viewer, 'admin')->delete("/admin/{$segment}/{$record->id}")->assertForbidden();

    expect($record->fresh()->is_active)->toBeTrue();
})->with('structure records');

it('deactivates and reactivates a record with an audit entry', function (string $model, string $segment, string $module, Closure $payload) {
    $record = $model::factory()->create();
    $admin = structureAdmin($module);

    $this->actingAs($admin, 'admin')->from("/admin/{$segment}")
        ->patch("/admin/{$segment}/{$record->id}/status", ['is_active' => '0'])
        ->assertRedirect("/admin/{$segment}")
        ->assertSessionHas('status', __('structure.messages.deactivated', ['name' => $record->name]));

    expect($record->fresh()->is_active)->toBeFalse();
    expect(AuditLog::query()->where('auditable_id', $record->id)->where('auditable_type', $record->getMorphClass())->where('event', 'updated')->sole()->new_values)
        ->toBe(['is_active' => false]);

    $this->actingAs($admin, 'admin')->patch("/admin/{$segment}/{$record->id}/status", ['is_active' => '1']);
    expect($record->fresh()->is_active)->toBeTrue();
})->with('structure records');

it('deletes an unused record and audits its last values', function (string $model, string $segment, string $module, Closure $payload) {
    $record = $model::factory()->create();

    $this->actingAs(structureAdmin($module), 'admin')
        ->delete("/admin/{$segment}/{$record->id}")
        ->assertRedirect(route("admin.{$segment}.index"));

    expect($model::query()->find($record->id))->toBeNull()
        ->and(AuditLog::query()->where('event', 'deleted')->where('auditable_id', $record->id)->sole()->old_values)
        ->toMatchArray(['code' => $record->code]);
})->with('structure records');

it('sorts lists by whitelisted columns only', function () {
    JobTitle::factory()->create(['code' => 'B-2', 'name_en' => 'Alpha']);
    JobTitle::factory()->create(['code' => 'A-1', 'name_en' => 'Zulu']);
    $admin = structureAdmin('job_titles', ['view']);
    $admin->update(['locale' => 'en']);

    $this->actingAs($admin, 'admin')->get('/admin/job-titles?sort=name&direction=desc')->assertSeeInOrder(['Zulu', 'Alpha']);
    $this->actingAs($admin, 'admin')->get('/admin/job-titles?sort=code')->assertSeeInOrder(['A-1', 'B-2']);
    $this->actingAs($admin, 'admin')->get('/admin/job-titles?sort=password&direction=sideways')->assertOk()->assertSeeInOrder(['A-1', 'B-2']);
});

it('filters lists by search text, status and parent record', function () {
    $sales = Department::factory()->create(['name_en' => 'Sales']);
    Team::factory()->for($sales)->create(['name_en' => 'Retail desk']);
    Team::factory()->inactive()->create(['name_en' => 'Old desk']);
    $admin = structureAdmin('teams', ['view']);
    $admin->update(['locale' => 'en']);

    $this->actingAs($admin, 'admin')->get('/admin/teams?search=retail')->assertSee('Retail desk')->assertDontSee('Old desk');
    $this->actingAs($admin, 'admin')->get('/admin/teams?status=inactive')->assertSee('Old desk')->assertDontSee('Retail desk');
    $this->actingAs($admin, 'admin')->get("/admin/teams?department_id={$sales->id}")->assertSee('Retail desk')->assertDontSee('Old desk');
});

it('keeps decimal salary bands exact and requires the maximum to cover the minimum', function () {
    // Salary bands are financial data (project lead decision, Phase 3b).
    $admin = adminWithPermissions('job_grades.view', 'job_grades.create', 'job_grades.update', 'employee_financials.view');

    $this->actingAs($admin, 'admin')
        ->post('/admin/job-grades', ['code' => 'G1', 'name_ar' => 'أ', 'name_en' => 'A', 'level' => 1, 'min_salary' => '9000', 'max_salary' => '100'])
        ->assertSessionHasErrors('max_salary');

    $this->actingAs($admin, 'admin')
        ->post('/admin/job-grades', ['code' => 'G1', 'name_ar' => 'أ', 'name_en' => 'A', 'level' => 1, 'min_salary' => '1234567.89', 'max_salary' => '2345678.91'])
        ->assertSessionHasNoErrors();

    expect(JobGrade::query()->sole())->min_salary->toBe('1234567.89')->max_salary->toBe('2345678.91');
});

it('only offers and accepts active related records for new links', function () {
    $inactive = Department::factory()->inactive()->create(['name_en' => 'Closed department']);
    $admin = structureAdmin('teams');
    $admin->update(['locale' => 'en']);

    $this->actingAs($admin, 'admin')->get('/admin/teams/create')->assertDontSee('Closed department');
    $this->actingAs($admin, 'admin')
        ->post('/admin/teams', ['code' => 'T1', 'name_ar' => 'ف', 'name_en' => 'T', 'department_id' => $inactive->id])
        ->assertSessionHasErrors('department_id');

    // A record that already points at it may keep it.
    $team = Team::factory()->for($inactive)->create();
    $this->actingAs($admin, 'admin')
        ->put("/admin/teams/{$team->id}", ['code' => $team->code, 'name_ar' => 'ف', 'name_en' => 'Kept', 'department_id' => $inactive->id])
        ->assertSessionHasNoErrors();
});
