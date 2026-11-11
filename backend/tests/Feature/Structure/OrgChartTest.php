<?php

use App\Models\Branch;
use App\Models\Department;
use App\Models\Position;
use App\Services\Authorization\DataScopeManager;
use App\Services\Structure\OrgChartBuilder;

it('renders the department tree with nested children, heads and counts', function () {
    $root = Department::factory()->create(['name_en' => 'Executive office', 'code' => 'EXEC']);
    $child = Department::factory()->create(['name_en' => 'Finance', 'parent_id' => $root->id]);
    Department::factory()->create(['name_en' => 'Payroll', 'parent_id' => $child->id]);
    $cfo = Position::factory()->for($child)->create(['name_en' => 'Chief financial officer', 'headcount' => 2]);
    $child->update(['head_position_id' => $cfo->id]);
    $admin = adminWithPermissions('departments.view');
    $admin->update(['locale' => 'en']);

    $response = $this->actingAs($admin, 'admin')->get('/admin/org-chart')->assertOk();

    $response->assertSeeInOrder(['Executive office', 'Finance', 'Chief financial officer', 'Payroll'])
        ->assertSee('data-org-chart', false)
        ->assertSee(route('admin.departments.show', $child), false);

    $tree = $response->viewData('nodes');
    expect($tree)->toHaveCount(1)
        ->and($tree[0]['children'][0]['title'])->toBe('Finance')
        ->and($tree[0]['children'][0]['lead'])->toBe('Chief financial officer')
        ->and($tree[0]['children'][0]['meta'][1]['value'])->toBe('2')
        ->and($tree[0]['children'][0]['children'][0]['title'])->toBe('Payroll')
        ->and($tree[0]['children'][0]['people'])->toBe([])
        ->and($response->viewData('summary'))->toBe(['nodes' => 3, 'roots' => 1, 'depth' => 3]);
});

it('renders the position reporting hierarchy for admins who may view positions', function () {
    $ceo = Position::factory()->create(['name_en' => 'CEO']);
    Position::factory()->create(['name_en' => 'CTO', 'reports_to_position_id' => $ceo->id]);
    $admin = adminWithPermissions('departments.view', 'positions.view');
    $admin->update(['locale' => 'en']);

    $response = $this->actingAs($admin, 'admin')->get('/admin/org-chart?view=positions')->assertOk();

    expect($response->viewData('view'))->toBe('positions')
        ->and($response->viewData('nodes')[0]['title'])->toBe('CEO')
        ->and($response->viewData('nodes')[0]['children'][0]['title'])->toBe('CTO');
});

it('falls back to the department view without positions.view, and needs departments.view', function () {
    $admin = adminWithPermissions('departments.view');

    $this->actingAs($admin, 'admin')->get('/admin/org-chart?view=positions')->assertOk()->assertViewHas('view', 'departments');
    $this->actingAs(adminWithPermissions('positions.view'), 'admin')->get('/admin/org-chart')->assertForbidden();
});

it('makes a node whose parent is outside the admin\'s scope a root', function () {
    $alex = Branch::factory()->create();
    $root = Department::factory()->create(['name_en' => 'Head office']);
    Department::factory()->create(['name_en' => 'Alex sales', 'parent_id' => $root->id, 'branch_id' => $alex->id]);
    $admin = adminWithRoles('HR Manager');
    $admin->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => $alex->id]);
    app(DataScopeManager::class)->activate($admin->fresh());

    app()->setLocale('en');
    $nodes = app(OrgChartBuilder::class)->departments($admin);

    expect(array_column($nodes, 'title'))->toBe(['Alex sales']);
});

it('shows an empty state when there is nothing to chart', function () {
    $this->actingAs(adminWithPermissions('departments.view'), 'admin')->get('/admin/org-chart')
        ->assertOk()
        ->assertSee(__('structure.org_chart.empty_departments'));
});
