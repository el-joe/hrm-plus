<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchLocation;

it('adds, edits and deletes an attendance location from the branch page', function () {
    $branch = Branch::factory()->create();
    $admin = adminWithPermissions('branches.view', 'branches.update');

    $this->actingAs($admin, 'admin')->get("/admin/branches/{$branch->id}/locations/create")
        ->assertOk()
        ->assertSee('value="'.setting('attendance.geofence_default_radius_meters').'"', false);

    $this->actingAs($admin, 'admin')->post("/admin/branches/{$branch->id}/locations", [
        'name_ar' => 'المبنى', 'name_en' => 'Main', 'latitude' => '30.0444196', 'longitude' => '31.2357116', 'radius_meters' => 200,
    ])->assertRedirect(route('admin.branches.show', $branch));

    $location = $branch->locations()->sole();
    expect($location)->latitude->toBe('30.0444196')->longitude->toBe('31.2357116')->radius_meters->toBe(200);
    expect(AuditLog::query()->where('auditable_type', 'branch_location')->where('event', 'created')->exists())->toBeTrue();

    $this->actingAs($admin, 'admin')->get("/admin/branches/{$branch->id}")->assertSee('30.0444196');

    $this->actingAs($admin, 'admin')->put("/admin/branches/{$branch->id}/locations/{$location->id}", [
        'name_ar' => 'المبنى', 'name_en' => 'Main', 'latitude' => '30.1', 'longitude' => '31.2', 'radius_meters' => 300, 'is_active' => '0',
    ])->assertSessionHasNoErrors();
    expect($location->fresh())->radius_meters->toBe(300)->is_active->toBeFalse();

    $this->actingAs($admin, 'admin')->delete("/admin/branches/{$branch->id}/locations/{$location->id}")->assertSessionHasNoErrors();
    expect(BranchLocation::query()->count())->toBe(0);
});

it('validates the GPS point and geofence radius', function (array $input, string $error) {
    $branch = Branch::factory()->create();

    $this->actingAs(superAdmin(), 'admin')
        ->post("/admin/branches/{$branch->id}/locations", [
            'name_ar' => 'م', 'name_en' => 'M', 'latitude' => '30', 'longitude' => '31', 'radius_meters' => 150, ...$input,
        ])
        ->assertSessionHasErrors($error);
})->with([
    'latitude out of range' => [['latitude' => '91'], 'latitude'],
    'longitude out of range' => [['longitude' => '-181'], 'longitude'],
    'too many decimals' => [['latitude' => '30.123456789'], 'latitude'],
    'radius too small' => [['radius_meters' => 5], 'radius_meters'],
    'radius too large' => [['radius_meters' => 9000], 'radius_meters'],
]);

it('needs branches.update and only reaches locations through their own branch', function () {
    $branch = Branch::factory()->create();
    $otherLocation = BranchLocation::factory()->create();

    $this->actingAs(adminWithPermissions('branches.view'), 'admin')->get("/admin/branches/{$branch->id}/locations/create")->assertForbidden();
    $this->actingAs(superAdmin(), 'admin')->get("/admin/branches/{$branch->id}/locations/{$otherLocation->id}/edit")->assertNotFound();
});
