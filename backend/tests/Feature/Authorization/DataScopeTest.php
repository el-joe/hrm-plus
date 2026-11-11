<?php

use App\Enums\DataScopeType;
use App\Models\User;
use App\Services\Authorization\DataScopeManager;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\ScopedRecord;
use Tests\Fixtures\ScopedRecordNote;

/**
 * Give an admin data scopes, e.g. restrict($admin, branch: [1, 2]).
 */
function restrict(User $admin, array $branch = [], array $department = []): User
{
    foreach (['branch' => $branch, 'department' => $department] as $type => $ids) {
        foreach ($ids as $id) {
            $admin->dataScopes()->create(['scope_type' => $type, 'scope_id' => $id]);
        }
    }

    return $admin->unsetRelation('dataScopes');
}

function names($records): array
{
    return $records->pluck('name')->sort()->values()->all();
}

beforeEach(function () {
    ScopedRecord::insert([
        ['name' => 'cairo-sales', 'branch_id' => 1, 'department_id' => 10],
        ['name' => 'cairo-it', 'branch_id' => 1, 'department_id' => 20],
        ['name' => 'alex-sales', 'branch_id' => 2, 'department_id' => 10],
        ['name' => 'giza-it', 'branch_id' => 3, 'department_id' => 20],
    ]);
});

it('shows every record to an admin with no data scopes', function () {
    $admin = adminWithRoles('HR Manager');

    expect(ScopedRecord::query()->visibleTo($admin)->count())->toBe(4);
});

it('limits a branch-restricted admin to the records of their branches', function () {
    $admin = restrict(adminWithRoles('Branch Manager'), branch: [1]);

    expect(names(ScopedRecord::query()->visibleTo($admin)->get()))->toBe(['cairo-it', 'cairo-sales']);
});

it('limits a department-restricted admin to the records of their departments', function () {
    $admin = restrict(adminWithRoles('Department Manager'), department: [20]);

    expect(names(ScopedRecord::query()->visibleTo($admin)->get()))->toBe(['cairo-it', 'giza-it']);
});

it('shows records matching any of the scopes when several dimensions are assigned', function () {
    $admin = restrict(adminWithRoles('Branch Manager'), branch: [2], department: [20]);

    expect(names(ScopedRecord::query()->visibleTo($admin)->get()))->toBe(['alex-sales', 'cairo-it', 'giza-it']);
});

it('keeps the restriction when the query has its own or-conditions', function () {
    $admin = restrict(adminWithRoles('Branch Manager'), branch: [1]);

    $records = ScopedRecord::query()
        ->where('department_id', 10)
        ->orWhere('department_id', 20)
        ->visibleTo($admin)
        ->get();

    expect(names($records))->toBe(['cairo-it', 'cairo-sales']);
});

it('ignores data scopes for a Super Admin', function () {
    $admin = restrict(superAdmin(), branch: [1]);

    expect(ScopedRecord::query()->visibleTo($admin)->count())->toBe(4);
});

it('shows nothing when the model maps none of the dimensions the admin is restricted by', function () {
    $admin = restrict(adminWithRoles('Department Manager'), department: [10]);
    ScopedRecordNote::create(['scoped_record_id' => ScopedRecord::first()->id, 'body' => 'note']);

    expect(ScopedRecordNote::query()->visibleTo($admin)->count())->toBe(0);
});

it('scopes a child model through its parent relation', function () {
    $admin = restrict(adminWithRoles('Branch Manager'), branch: [1]);
    $cairo = ScopedRecord::query()->where('name', 'cairo-sales')->first();
    $alex = ScopedRecord::query()->where('name', 'alex-sales')->first();
    ScopedRecordNote::create(['scoped_record_id' => $cairo->id, 'body' => 'cairo note']);
    ScopedRecordNote::create(['scoped_record_id' => $alex->id, 'body' => 'alex note']);

    expect(ScopedRecordNote::query()->visibleTo($admin)->pluck('body')->all())->toBe(['cairo note']);
});

it('answers record-level checks for policies', function () {
    $admin = restrict(adminWithRoles('Branch Manager'), branch: [1]);

    expect(ScopedRecord::query()->where('name', 'cairo-it')->first()->isVisibleTo($admin))->toBeTrue()
        ->and(ScopedRecord::query()->where('name', 'giza-it')->first()->isVisibleTo($admin))->toBeFalse();
});

it('does not restrict queries outside the admin area', function () {
    restrict(adminWithRoles('Branch Manager'), branch: [1]);

    expect(ScopedRecord::query()->count())->toBe(4);
});

it('restricts every query automatically once the admin is active, including lookups by id', function () {
    $admin = restrict(adminWithRoles('Branch Manager'), branch: [1]);
    $outside = ScopedRecord::query()->where('name', 'giza-it')->first();

    app(DataScopeManager::class)->activate($admin);

    expect(ScopedRecord::query()->count())->toBe(2)
        ->and(ScopedRecord::query()->find($outside->id))->toBeNull();
});

it('lifts the automatic restriction inside withoutScoping only', function () {
    $admin = restrict(adminWithRoles('Branch Manager'), branch: [1]);
    $manager = app(DataScopeManager::class);
    $manager->activate($admin);

    expect($manager->withoutScoping(fn () => ScopedRecord::query()->count()))->toBe(4)
        ->and(ScopedRecord::query()->count())->toBe(2);
});

it('applies the signed-in admin\'s scopes to admin routes and returns 404 for a record outside them', function () {
    Route::middleware(['web', 'admin'])->group(function () {
        Route::get('/admin/_test/records', fn () => ScopedRecord::query()->orderBy('name')->pluck('name'));
        Route::get('/admin/_test/records/{record}', fn (ScopedRecord $record) => $record->name);
    });

    $admin = restrict(adminWithRoles('Branch Manager'), branch: [2]);
    $outside = ScopedRecord::query()->where('name', 'cairo-it')->first();

    $this->actingAs($admin, 'admin')->get('/admin/_test/records')->assertExactJson(['alex-sales']);
    $this->actingAs($admin, 'admin')->get('/admin/_test/records/'.$outside->id)->assertNotFound();
});

it('stores the scopes of an admin grouped by type', function () {
    $admin = restrict(adminWithRoles('Branch Manager'), branch: [3, 1], department: [20]);

    expect($admin->dataScopeIds())->toBe(['branch' => [1, 3], 'department' => [20]])
        ->and($admin->dataScopeIdsFor(DataScopeType::EmployeeGroup))->toBe([])
        ->and($admin->hasDataRestrictions())->toBeTrue();
});
