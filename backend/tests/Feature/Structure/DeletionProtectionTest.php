<?php

use App\Models\Branch;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\JobGrade;
use App\Models\JobTitle;
use App\Models\Position;

it('refuses to delete a record that is still in use and explains why', function (Closure $arrange, string $segment, string $module, string $usage) {
    $record = $arrange();

    $this->actingAs(adminWithPermissions("{$module}.view", "{$module}.delete"), 'admin')
        ->from("/admin/{$segment}")
        ->delete("/admin/{$segment}/{$record->id}")
        ->assertRedirect("/admin/{$segment}")
        ->assertSessionHasErrors('record');

    expect($record->fresh())->not->toBeNull()
        ->and(session('errors')->first('record'))->toContain(trans_choice('structure.usage.'.$usage, 1, ['count' => 1]));
})->with([
    'department with a position' => [fn () => Position::factory()->create()->department, 'departments', 'departments', 'positions'],
    'department with a sub-department' => [fn () => Department::factory()->create(['parent_id' => Department::factory()->create()->id])->parent, 'departments', 'departments', 'child_departments'],
    'job title used by a position' => [fn () => Position::factory()->create()->jobTitle, 'job-titles', 'job_titles', 'positions'],
    'job grade used by a position' => [fn () => Position::factory()->create(['job_grade_id' => JobGrade::factory()->create()->id])->jobGrade, 'job-grades', 'job_grades', 'positions'],
    'branch with a department' => [fn () => Department::factory()->create(['branch_id' => Branch::factory()->create()->id])->branch, 'branches', 'branches', 'departments'],
    'cost centre of a department' => [fn () => Department::factory()->create(['cost_center_id' => CostCenter::factory()->create()->id])->costCenter, 'cost-centers', 'cost_centers', 'departments'],
    'position with a direct report' => [fn () => Position::factory()->create(['reports_to_position_id' => Position::factory()->create()->id])->reportsTo, 'positions', 'positions', 'direct_reports'],
]);

it('treats a branch used in an admin data restriction as in use', function () {
    $branch = Branch::factory()->create();
    adminWithRoles('Branch Manager')->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => $branch->id]);

    $this->actingAs(superAdmin(), 'admin')->delete("/admin/branches/{$branch->id}")->assertSessionHasErrors('record');

    expect($branch->fresh())->not->toBeNull();
});

it('counts references outside the admin\'s data scope too', function () {
    $branch = Branch::factory()->create();
    $parent = Department::factory()->create(['branch_id' => $branch->id]);
    Department::factory()->create(['parent_id' => $parent->id, 'branch_id' => Branch::factory()->create()->id]);
    $admin = adminWithRoles('HR Manager');
    $admin->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => $branch->id]);

    $this->actingAs($admin, 'admin')->delete("/admin/departments/{$parent->id}")->assertSessionHasErrors('record');

    expect($parent->fresh())->not->toBeNull();
});

it('deletes a branch together with its locations once nothing else uses it', function () {
    $branch = Branch::factory()->hasLocations(2)->create();

    $this->actingAs(superAdmin(), 'admin')->delete("/admin/branches/{$branch->id}")->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('branch_locations', ['branch_id' => $branch->id]);
});

it('keeps an unused job title deletable after its position moved to another title', function () {
    $position = Position::factory()->create();
    $oldTitle = $position->jobTitle;
    $position->update(['job_title_id' => JobTitle::factory()->create()->id]);

    $this->actingAs(superAdmin(), 'admin')->delete("/admin/job-titles/{$oldTitle->id}")->assertSessionHasNoErrors();

    expect($oldTitle->fresh())->toBeNull();
});
