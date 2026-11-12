<?php

use App\Actions\Assets\ManageAssetCustody;
use App\Actions\Offboarding\ManageOffboardingCase;
use App\Actions\Offboarding\OpenOffboardingCase;
use App\Actions\Onboarding\StartOnboarding;
use App\Enums\HandoverCategory;
use App\Enums\OnboardingTaskAction;
use App\Enums\TaskStatus;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Models\OffboardingItem;
use App\Models\OnboardingTask;
use Database\Seeders\AssetDefaultsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/*
| Assets in the employee life cycle (Phase 8a): offboarding clearance items for
| every asset held, completed by recording the return; the onboarding task
| "Assign company assets" linked to the handover form.
*/

beforeEach(function () {
    Notification::fake();
    $this->hr = adminWithRoles('HR Manager');
    $this->employee = Employee::factory()->create(['hire_date' => '2024-01-01']);
    $this->custody = app(ManageAssetCustody::class);
});

function giveDemoAsset(Employee $employee): AssetAssignment
{
    return app(ManageAssetCustody::class)->assign(Asset::factory()->create(['branch_id' => $employee->branch_id]), $employee, [
        'assigned_on' => '2025-06-01', 'condition_on_issue' => 'good',
    ], test()->hr);
}

function openAssetClearanceCase(Employee $employee): OffboardingCase
{
    return app(OpenOffboardingCase::class)->handle($employee, [
        'type' => 'resignation', 'last_working_date' => now()->addDays(20)->toDateString(),
    ], test()->hr);
}

it('adds one clearance item per asset the employee holds when the case opens', function () {
    $laptop = giveDemoAsset($this->employee);
    $phone = giveDemoAsset($this->employee);
    $returned = giveDemoAsset($this->employee);
    $this->custody->returnAsset($returned, ['returned_on' => now()->toDateString(), 'condition_on_return' => 'good'], $this->hr);

    $case = openAssetClearanceCase($this->employee);
    $items = $case->items()->where('itemable_type', 'asset_assignment')->get();

    expect($items)->toHaveCount(2)
        ->and($items->pluck('itemable_id')->sort()->values()->all())->toBe([$laptop->id, $phone->id])
        ->and($items->every(fn (OffboardingItem $item): bool => $item->category === HandoverCategory::Asset && $item->status === TaskStatus::Pending))->toBeTrue();
});

it('completes the clearance item when the asset is returned', function () {
    $assignment = giveDemoAsset($this->employee);
    $case = openAssetClearanceCase($this->employee);

    $this->custody->returnAsset($assignment, ['returned_on' => now()->toDateString(), 'condition_on_return' => 'good'], $this->hr);

    $item = $case->items()->where('itemable_type', 'asset_assignment')->sole();
    expect($item->status)->toBe(TaskStatus::Done)
        ->and($item->completed_by)->toBe($this->hr->id)
        ->and($item->note)->not->toBeEmpty();
});

it('refuses to tick an asset item done while the asset is still out, but allows waiving it', function () {
    giveDemoAsset($this->employee);
    $case = openAssetClearanceCase($this->employee);
    $item = $case->items()->where('itemable_type', 'asset_assignment')->sole();
    $manage = app(ManageOffboardingCase::class);

    expect(fn () => $manage->updateItem($item, TaskStatus::Done, $this->hr))->toThrow(ValidationException::class);

    $manage->updateItem($item, TaskStatus::Skipped, $this->hr, 'Written off');
    expect($item->refresh()->status)->toBe(TaskStatus::Skipped);
});

it('adds an item to the open case when an asset is handed over during offboarding', function () {
    $case = openAssetClearanceCase($this->employee);
    $assignment = giveDemoAsset($this->employee);

    expect($case->items()->where('itemable_type', 'asset_assignment')->where('itemable_id', $assignment->id)->exists())->toBeTrue();
});

it('blocks completing the case while an asset item is pending', function () {
    giveDemoAsset($this->employee);
    $case = openAssetClearanceCase($this->employee);
    $case->items()->where('itemable_type', '!=', 'asset_assignment')->orWhereNull('itemable_type')->update(['status' => TaskStatus::Done->value]);

    expect(fn () => app(ManageOffboardingCase::class)->complete($case->refresh(), $this->hr))->toThrow(ValidationException::class);
});

it('links the onboarding task to the handover form and completes it on handover', function () {
    seedRecruitment();
    $this->seed(AssetDefaultsSeeder::class);
    $onboarding = app(StartOnboarding::class)->handle($this->employee);
    $task = OnboardingTask::query()->where('onboarding_id', $onboarding->id)->where('action', OnboardingTaskAction::IssueAssets)->sole();

    $target = $task->action->target($this->employee->id, $task->id);
    expect($target['route'])->toBe('admin.asset-assignments.create')
        ->and($target['parameters'])->toBe(['employee' => $this->employee->id, 'onboarding_task' => $task->id]);

    $asset = Asset::factory()->create(['branch_id' => $this->employee->branch_id]);
    $this->actingAs($this->hr, 'admin')->post(route('admin.asset-assignments.store'), [
        'asset_id' => $asset->id, 'employee_id' => $this->employee->id, 'assigned_on' => now()->toDateString(),
        'condition_on_issue' => 'new', 'onboarding_task_id' => $task->id,
    ])->assertRedirect(route('admin.assets.show', $asset));

    expect($task->refresh()->status)->toBe(TaskStatus::Done)
        ->and($asset->refresh()->currentAssignment->onboarding_task_id)->toBe($task->id);
});

it('refuses an onboarding task of another employee', function () {
    seedRecruitment();
    $this->seed(AssetDefaultsSeeder::class);
    $onboarding = app(StartOnboarding::class)->handle(Employee::factory()->create(['hire_date' => '2024-01-01']));
    $task = OnboardingTask::query()->where('onboarding_id', $onboarding->id)->where('action', OnboardingTaskAction::IssueAssets)->sole();

    $this->custody->assign(Asset::factory()->create(), $this->employee, ['assigned_on' => now()->toDateString(), 'condition_on_issue' => 'good'], $this->hr, $task);
})->throws(ValidationException::class);
