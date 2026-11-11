<?php

use App\Models\Branch;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Position;

function departmentPayload(Department $department, array $overrides = []): array
{
    return [...$department->only(['code', 'name_ar', 'name_en', 'parent_id', 'branch_id', 'cost_center_id', 'head_position_id']), ...$overrides];
}

it('refuses to place a department under itself or under one of its descendants', function () {
    $root = Department::factory()->create();
    $child = Department::factory()->create(['parent_id' => $root->id]);
    $grandchild = Department::factory()->create(['parent_id' => $child->id]);

    foreach ([$root->id, $grandchild->id] as $parentId) {
        $this->actingAs(superAdmin(), 'admin')
            ->put("/admin/departments/{$root->id}", departmentPayload($root, ['parent_id' => $parentId]))
            ->assertSessionHasErrors(['parent_id' => __('structure.messages.hierarchy_cycle')]);
    }

    expect($root->fresh()->parent_id)->toBeNull();
});

it('refuses a reporting line that would loop back to the position', function () {
    $ceo = Position::factory()->create();
    $manager = Position::factory()->create(['reports_to_position_id' => $ceo->id]);

    $this->actingAs(superAdmin(), 'admin')
        ->put("/admin/positions/{$ceo->id}", [
            ...$ceo->only(['code', 'name_ar', 'name_en', 'department_id', 'job_title_id', 'headcount']),
            'reports_to_position_id' => $manager->id,
        ])
        ->assertSessionHasErrors('reports_to_position_id');
});

it('refuses a cost centre cycle', function () {
    $parent = CostCenter::factory()->create();
    $child = CostCenter::factory()->create(['parent_id' => $parent->id]);

    $this->actingAs(superAdmin(), 'admin')
        ->put("/admin/cost-centers/{$parent->id}", [...$parent->only(['code', 'name_ar', 'name_en']), 'parent_id' => $child->id])
        ->assertSessionHasErrors('parent_id');
});

it('accepts only one of the department\'s own positions as its head', function () {
    $department = Department::factory()->create();
    $own = Position::factory()->for($department)->create();
    $foreign = Position::factory()->create();

    $this->actingAs(superAdmin(), 'admin')
        ->put("/admin/departments/{$department->id}", departmentPayload($department, ['head_position_id' => $foreign->id]))
        ->assertSessionHasErrors('head_position_id');

    $this->actingAs(superAdmin(), 'admin')
        ->put("/admin/departments/{$department->id}", departmentPayload($department, ['head_position_id' => $own->id]))
        ->assertSessionHasNoErrors();

    expect($department->fresh()->head_position_id)->toBe($own->id);
});

it('keeps a single headquarters branch', function () {
    $old = Branch::factory()->create(['is_headquarters' => true]);

    $this->actingAs(superAdmin(), 'admin')
        ->post('/admin/branches', ['code' => 'NEW', 'name_ar' => 'جديد', 'name_en' => 'New', 'is_headquarters' => '1'])
        ->assertSessionHasNoErrors();

    expect($old->fresh()->is_headquarters)->toBeFalse()
        ->and(Branch::query()->where('is_headquarters', true)->sole()->code)->toBe('NEW');
});

it('shows a department with its sub-departments, teams and positions', function () {
    $department = Department::factory()->create(['name_en' => 'Technology']);
    Department::factory()->create(['parent_id' => $department->id, 'name_en' => 'Mobile']);
    Position::factory()->for($department)->create(['name_en' => 'Backend engineer', 'headcount' => 4]);
    $admin = adminWithPermissions('departments.view');
    $admin->update(['locale' => 'en']);

    $this->actingAs($admin, 'admin')->get("/admin/departments/{$department->id}")
        ->assertOk()
        ->assertSee(['Technology', 'Mobile', 'Backend engineer']);
});
