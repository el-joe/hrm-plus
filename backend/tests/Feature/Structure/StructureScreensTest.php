<?php

use App\Models\Branch;
use App\Models\BranchLocation;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\EmploymentType;
use App\Models\JobGrade;
use App\Models\Position;
use App\Models\Team;
use App\Support\Navigation\NavigationBuilder;

it('renders every company structure screen in both directions', function (string $locale, string $direction) {
    $admin = superAdmin();
    $admin->update(['locale' => $locale]);
    $branch = Branch::factory()->create();
    $location = BranchLocation::factory()->for($branch)->create();
    $department = Department::factory()->create(['branch_id' => $branch->id, 'cost_center_id' => CostCenter::factory()->create()->id]);
    $position = Position::factory()->for($department)->create(['job_grade_id' => JobGrade::factory()->create()->id]);
    $department->update(['head_position_id' => $position->id]);
    $team = Team::factory()->for($department)->create();
    $employmentType = EmploymentType::factory()->create();

    foreach ([
        '/admin/company', '/admin/company/edit', '/admin/org-chart', '/admin/org-chart?view=positions',
        '/admin/branches', '/admin/branches/create', "/admin/branches/{$branch->id}", "/admin/branches/{$branch->id}/edit",
        "/admin/branches/{$branch->id}/locations/create", "/admin/branches/{$branch->id}/locations/{$location->id}/edit",
        '/admin/departments', '/admin/departments/create', "/admin/departments/{$department->id}", "/admin/departments/{$department->id}/edit",
        '/admin/teams', '/admin/teams/create', "/admin/teams/{$team->id}/edit",
        '/admin/positions', '/admin/positions/create', "/admin/positions/{$position->id}/edit",
        '/admin/job-titles', '/admin/job-titles/create', "/admin/job-titles/{$position->job_title_id}/edit",
        '/admin/job-grades', '/admin/job-grades/create', "/admin/job-grades/{$position->job_grade_id}/edit",
        '/admin/employment-types', '/admin/employment-types/create', "/admin/employment-types/{$employmentType->id}/edit",
        '/admin/cost-centers', '/admin/cost-centers/create', "/admin/cost-centers/{$department->cost_center_id}/edit",
    ] as $url) {
        $this->actingAs($admin->fresh(), 'admin')
            ->get($url)
            ->assertOk()
            ->assertSee('dir="'.$direction.'"', false)
            ->assertSee('data-admin-sidebar', false);
    }
})->with([
    'Arabic' => ['ar', 'rtl'],
    'English' => ['en', 'ltr'],
]);

it('shows the structure sidebar entries an HR Specialist may open', function () {
    $sections = app(NavigationBuilder::class)->admin(adminWithRoles('HR Specialist'), 'admin.dashboard');
    $structure = collect($sections)->firstWhere('key', 'structure');

    expect(array_column($structure['items'], 'key'))->toBe([
        'company', 'org_chart', 'branches', 'departments', 'teams', 'positions', 'job_titles', 'job_grades', 'employment_types', 'cost_centers',
    ]);

    $hidden = app(NavigationBuilder::class)->admin(adminWithRoles('Reports Viewer'), 'admin.dashboard');
    expect(array_column($hidden, 'key'))->not->toContain('structure');
});
