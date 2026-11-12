<?php

use App\Actions\Assets\ManageAssetCustody;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\Branch;
use App\Models\Employee;
use Illuminate\Support\Facades\Notification;

/*
| Admin screens of the asset module (Phase 8a): permissions, data scope (asset
| branch for the register, the employee for custody), forms and exports.
*/

beforeEach(function () {
    Notification::fake();
    $this->cairo = Branch::factory()->create(['code' => 'CAI']);
    $this->alex = Branch::factory()->create(['code' => 'ALX']);
    $this->cairoAsset = Asset::factory()->create(['branch_id' => $this->cairo->id, 'asset_tag' => 'LT-CAI-1']);
    $this->alexAsset = Asset::factory()->create(['branch_id' => $this->alex->id, 'asset_tag' => 'LT-ALX-1']);
});

it('lists the register for admins with assets.view and hides it from others', function () {
    $this->actingAs(adminWithRoles('HR Specialist'), 'admin')->get(route('admin.assets.index'))
        ->assertOk()->assertSee('LT-CAI-1')->assertSee('LT-ALX-1');

    $this->actingAs(adminWithRoles('Recruiter'), 'admin')->get(route('admin.assets.index'))->assertForbidden();
    $this->actingAs(adminWithRoles('Recruiter'), 'admin')->get(route('admin.asset-assignments.index'))->assertForbidden();
});

it('limits a branch-scoped admin to the assets of their branch', function () {
    $manager = adminWithRoles('Branch Manager');
    $manager->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => $this->alex->id]);

    $this->actingAs($manager, 'admin')->get(route('admin.assets.index'))
        ->assertOk()->assertSee('LT-ALX-1')->assertDontSee('LT-CAI-1');
    $this->actingAs($manager, 'admin')->get(route('admin.assets.show', $this->cairoAsset))->assertNotFound();
    $this->actingAs($manager, 'admin')->get(route('admin.assets.show', $this->alexAsset))->assertOk();
});

it('limits the custody list to employees inside the scope', function () {
    $hr = adminWithRoles('HR Manager');
    $alexEmployee = Employee::factory()->create(['branch_id' => $this->alex->id, 'hire_date' => '2024-01-01', 'name_en' => 'Alex Holder']);
    $cairoEmployee = Employee::factory()->create(['branch_id' => $this->cairo->id, 'hire_date' => '2024-01-01', 'name_en' => 'Cairo Holder']);
    app(ManageAssetCustody::class)->assign($this->alexAsset, $alexEmployee, ['assigned_on' => '2026-01-01', 'condition_on_issue' => 'good'], $hr);
    app(ManageAssetCustody::class)->assign($this->cairoAsset, $cairoEmployee, ['assigned_on' => '2026-01-01', 'condition_on_issue' => 'good'], $hr);

    $manager = adminWithRoles('Branch Manager');
    $manager->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => $this->alex->id]);

    app()->setLocale('en');
    $this->actingAs($manager, 'admin')->get(route('admin.asset-assignments.index'))
        ->assertOk()->assertSee('Alex Holder')->assertDontSee('Cairo Holder');
});

it('registers an asset through the form and shows it', function () {
    $category = AssetCategory::factory()->create();

    $response = $this->actingAs(adminWithRoles('HR Specialist'), 'admin')->post(route('admin.assets.store'), [
        'asset_tag' => 'mb-0100', 'asset_category_id' => $category->id, 'name' => 'Phone', 'serial_number' => 'IMEI-1',
        'purchase_date' => '2026-01-05', 'purchase_cost' => '18000', 'branch_id' => $this->cairo->id, 'condition' => 'new',
    ]);

    $asset = Asset::query()->where('asset_tag', 'MB-0100')->sole();
    $response->assertRedirect(route('admin.assets.show', $asset));
    expect($asset->purchase_cost)->toBe('18000.00');

    $this->get(route('admin.assets.show', $asset))->assertOk()->assertSee('MB-0100')->assertSee('IMEI-1');
});

it('rejects duplicate tags and serial numbers', function () {
    $this->actingAs(superAdmin(), 'admin')->post(route('admin.assets.store'), [
        'asset_tag' => 'LT-CAI-1', 'asset_category_id' => AssetCategory::factory()->create()->id, 'name' => 'Laptop',
        'serial_number' => $this->alexAsset->serial_number, 'condition' => 'good',
    ])->assertSessionHasErrors(['asset_tag', 'serial_number']);
});

it('hands over, records a return and retires through the screens', function () {
    $hr = adminWithRoles('HR Manager');
    $employee = Employee::factory()->create(['branch_id' => $this->cairo->id, 'hire_date' => '2024-01-01']);

    $this->actingAs($hr, 'admin')->post(route('admin.asset-assignments.store'), [
        'asset_id' => $this->cairoAsset->id, 'employee_id' => $employee->id, 'assigned_on' => now()->subDays(3)->toDateString(), 'condition_on_issue' => 'good',
    ])->assertRedirect(route('admin.assets.show', $this->cairoAsset));

    $assignment = AssetAssignment::query()->where('asset_id', $this->cairoAsset->id)->sole();

    $this->post(route('admin.asset-assignments.return', $assignment), ['returned_on' => now()->toDateString(), 'condition_on_return' => 'damaged'])
        ->assertSessionHasErrors('return_notes');
    $this->post(route('admin.asset-assignments.return', $assignment), ['returned_on' => now()->toDateString(), 'condition_on_return' => 'poor'])
        ->assertSessionHasNoErrors();

    $this->post(route('admin.assets.status', [$this->cairoAsset, 'retire']), ['note' => 'Old'])->assertSessionHasNoErrors();
    expect($this->cairoAsset->refresh()->status->value)->toBe('retired');
});

it('needs assets.delete to retire and assets.update to hand over', function () {
    $specialist = adminWithRoles('HR Specialist');

    $this->actingAs($specialist, 'admin')->post(route('admin.assets.status', [$this->cairoAsset, 'retire']), ['note' => 'x'])->assertForbidden();
    $this->actingAs(adminWithRoles('Branch Manager'), 'admin')->post(route('admin.asset-assignments.store'), [
        'asset_id' => $this->cairoAsset->id, 'employee_id' => Employee::factory()->create()->id, 'assigned_on' => now()->toDateString(), 'condition_on_issue' => 'good',
    ])->assertForbidden();
});

it('keeps categories behind assets.manage and protects categories in use', function () {
    $this->actingAs(adminWithRoles('HR Specialist'), 'admin')->get(route('admin.asset-categories.index'))->assertForbidden();

    $hr = adminWithRoles('HR Manager');
    $this->actingAs($hr, 'admin')->delete(route('admin.asset-categories.destroy', $this->cairoAsset->category))->assertSessionHas('error');
    expect(AssetCategory::query()->whereKey($this->cairoAsset->asset_category_id)->exists())->toBeTrue();

    $this->post(route('admin.asset-categories.store'), ['code' => 'veh', 'name_ar' => 'سيارة', 'name_en' => 'Vehicle', 'requires_serial' => '1', 'is_active' => '1'])
        ->assertRedirect(route('admin.asset-categories.index'));
    expect(AssetCategory::query()->where('code', 'VEH')->sole()->requires_serial)->toBeTrue();
});

it('exports the register and the custody list for admins with assets.export', function () {
    $hr = adminWithRoles('HR Manager');

    $this->actingAs($hr, 'admin')->get(route('admin.assets.export'))->assertOk()->assertDownload();
    $this->get(route('admin.assets.export', ['format' => 'csv']))->assertOk();
    $this->get(route('admin.asset-assignments.export'))->assertOk()->assertDownload();
    $this->actingAs(adminWithRoles('HR Specialist'), 'admin')->get(route('admin.assets.export'))->assertForbidden();
});

it('shows the assets tab on the employee profile to asset viewers only', function () {
    $employee = Employee::factory()->create(['branch_id' => $this->cairo->id, 'hire_date' => '2024-01-01']);
    app(ManageAssetCustody::class)->assign($this->cairoAsset, $employee, ['assigned_on' => '2026-01-01', 'condition_on_issue' => 'good'], adminWithRoles('HR Manager'));

    $this->actingAs(adminWithRoles('HR Specialist'), 'admin')->get(route('admin.employees.tab', [$employee, 'assets']))
        ->assertOk()->assertSee('LT-CAI-1');
    $this->actingAs(adminWithRoles('Attendance Officer'), 'admin')->get(route('admin.employees.tab', [$employee, 'assets']))->assertForbidden();
});
