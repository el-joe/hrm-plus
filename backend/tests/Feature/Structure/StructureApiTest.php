<?php

use App\Models\Branch;
use App\Models\BranchLocation;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns the company profile with the localisation settings in the requested language', function () {
    Company::factory()->create(['name_ar' => 'شركة الأفق', 'name_en' => 'Horizon']);
    Sanctum::actingAs(User::factory()->employee()->create());

    $this->getJson('/api/v1/company', ['Accept-Language' => 'en'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Horizon')
        ->assertJsonPath('data.name_translations.ar', 'شركة الأفق')
        ->assertJsonPath('data.currency_code', setting('localisation.currency_code'))
        ->assertJsonPath('data.logo_url', null);

    $this->getJson('/api/v1/company', ['Accept-Language' => 'ar'])->assertJsonPath('data.name', 'شركة الأفق');
});

it('lists active branches with their active locations, paginated', function () {
    $branch = Branch::factory()->create(['code' => 'CAI']);
    BranchLocation::factory()->for($branch)->create(['latitude' => '30.0444196', 'longitude' => '31.2357116', 'radius_meters' => 150]);
    BranchLocation::factory()->for($branch)->inactive()->create();
    $old = Branch::factory()->inactive()->create(['code' => 'OLD']);
    Sanctum::actingAs(User::factory()->employee()->create(['employee_id' => Employee::factory()->create(['branch_id' => $old->id])->id]));

    $this->getJson('/api/v1/branches')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'CAI')
        ->assertJsonCount(1, 'data.0.locations')
        ->assertJsonPath('data.0.locations.0.latitude', '30.0444196')
        ->assertJsonPath('data.0.locations.0.radius_meters', 150)
        ->assertJsonPath('meta.pagination.total', 1);

    $this->getJson("/api/v1/branches/{$branch->id}")->assertOk()->assertJsonPath('data.code', 'CAI');
    $this->getJson('/api/v1/branches/'.Branch::query()->where('code', 'OLD')->value('id'))->assertNotFound();
    $this->getJson('/api/v1/branches?sort=password')->assertUnprocessable();
});

it('requires an employee token', function () {
    $this->getJson('/api/v1/company')->assertUnauthorized();

    Sanctum::actingAs(User::factory()->admin()->create());
    $this->getJson('/api/v1/branches')->assertForbidden();
});
