<?php

use App\Actions\Assets\ChangeAssetStatus;
use App\Actions\Assets\ManageAssetCustody;
use App\Actions\Assets\SaveAsset;
use App\Enums\AssetAssignmentStatus;
use App\Enums\AssetCondition;
use App\Enums\AssetEventType;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\AssetEvent;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\Assets\AssetCustodyNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/*
| Asset custody (Phase 8a): handover → acknowledgement → return with a condition
| check, status rules and the asset / employee history.
*/

beforeEach(function () {
    Notification::fake();
    $this->hr = adminWithRoles('HR Manager');
    $this->custody = app(ManageAssetCustody::class);
    $this->employee = Employee::factory()->create(['hire_date' => '2024-01-01']);
    $this->asset = Asset::factory()->create(['branch_id' => $this->employee->branch_id]);
});

function handOverAsset(Asset $asset, Employee $employee, array $data = []): AssetAssignment
{
    return app(ManageAssetCustody::class)->assign($asset, $employee, [
        'assigned_on' => '2026-01-10', 'condition_on_issue' => 'good', 'handover_notes' => 'With charger', ...$data,
    ], test()->hr);
}

it('hands an available asset over and records the event', function () {
    $assignment = handOverAsset($this->asset, $this->employee);

    expect($assignment->status)->toBe(AssetAssignmentStatus::Active)
        ->and($assignment->condition_on_issue)->toBe(AssetCondition::Good)
        ->and($assignment->issued_by)->toBe($this->hr->id)
        ->and($assignment->isAcknowledged())->toBeFalse()
        ->and($this->asset->refresh()->status)->toBe(AssetStatus::Assigned)
        ->and(AssetEvent::query()->where('asset_id', $this->asset->id)->where('type', AssetEventType::Assigned)->sole()->employee_id)->toBe($this->employee->id);
});

it('notifies the employee account to acknowledge receipt', function () {
    $account = User::factory()->employee()->create(['employee_id' => $this->employee->id]);

    handOverAsset($this->asset, $this->employee);

    Notification::assertSentTo($account, AssetCustodyNotification::class, fn ($notification) => $notification->kind === 'assigned');
});

it('refuses to hand over an asset that is not available', function (string $status) {
    $this->asset->forceFill(['status' => $status])->save();

    handOverAsset($this->asset, $this->employee);
})->with(['assigned', 'retired', 'in_maintenance', 'lost', 'damaged'])->throws(ValidationException::class);

it('refuses a handover to a terminated employee or before the hire date', function () {
    expect(fn () => handOverAsset($this->asset, $this->employee, ['assigned_on' => '2023-12-31']))->toThrow(ValidationException::class);

    $this->employee->forceFill(['status' => 'terminated'])->save();
    expect(fn () => handOverAsset($this->asset, $this->employee))->toThrow(ValidationException::class);
});

it('records the acknowledgement once', function () {
    $assignment = handOverAsset($this->asset, $this->employee);

    $this->custody->acknowledge($assignment, 'portal', User::factory()->employee()->create(['employee_id' => $this->employee->id]));

    expect($assignment->refresh()->acknowledged_via)->toBe('portal')
        ->and($assignment->acknowledged_at)->not->toBeNull()
        ->and(AssetEvent::query()->where('type', AssetEventType::Acknowledged)->count())->toBe(1)
        ->and(fn () => $this->custody->acknowledge($assignment, 'api', $this->hr))->toThrow(ValidationException::class);
});

it('makes the asset available again after a return in good condition', function () {
    $assignment = handOverAsset($this->asset, $this->employee);

    $this->custody->returnAsset($assignment, ['returned_on' => '2026-03-01', 'condition_on_return' => 'fair', 'return_notes' => 'Scratches'], $this->hr);

    expect($assignment->refresh()->status)->toBe(AssetAssignmentStatus::Returned)
        ->and($assignment->returned_on->toDateString())->toBe('2026-03-01')
        ->and($assignment->condition_on_return)->toBe(AssetCondition::Fair)
        ->and($assignment->received_by)->toBe($this->hr->id)
        ->and($this->asset->refresh()->status)->toBe(AssetStatus::Available)
        ->and($this->asset->condition)->toBe(AssetCondition::Fair);
});

it('leaves a damaged return out of service', function () {
    $assignment = handOverAsset($this->asset, $this->employee);

    $this->custody->returnAsset($assignment, ['returned_on' => '2026-03-01', 'condition_on_return' => 'damaged', 'return_notes' => 'Broken hinge'], $this->hr);

    expect($this->asset->refresh()->status)->toBe(AssetStatus::Damaged)
        ->and(fn () => handOverAsset($this->asset, Employee::factory()->create(['hire_date' => '2024-01-01'])))->toThrow(ValidationException::class);
});

it('refuses a return dated before the handover or of a closed handover', function () {
    $assignment = handOverAsset($this->asset, $this->employee);

    expect(fn () => $this->custody->returnAsset($assignment, ['returned_on' => '2026-01-01', 'condition_on_return' => 'good'], $this->hr))->toThrow(ValidationException::class);

    $this->custody->returnAsset($assignment, ['returned_on' => '2026-02-01', 'condition_on_return' => 'good'], $this->hr);
    expect(fn () => $this->custody->returnAsset($assignment->refresh(), ['returned_on' => '2026-02-02', 'condition_on_return' => 'good'], $this->hr))->toThrow(ValidationException::class);
});

it('closes the handover as lost when an assigned asset is reported lost', function () {
    $assignment = handOverAsset($this->asset, $this->employee);

    $this->custody->markLost($assignment, '2026-02-01', 'Left in a taxi', $this->hr);

    expect($assignment->refresh()->status)->toBe(AssetAssignmentStatus::Lost)
        ->and($this->asset->refresh()->status)->toBe(AssetStatus::Lost);
});

it('keeps the full history of an asset across custodians', function () {
    $other = Employee::factory()->create(['hire_date' => '2024-01-01', 'branch_id' => $this->employee->branch_id]);
    $first = handOverAsset($this->asset, $this->employee);
    $this->custody->returnAsset($first, ['returned_on' => '2026-02-01', 'condition_on_return' => 'good'], $this->hr);
    handOverAsset($this->asset->refresh(), $other, ['assigned_on' => '2026-02-05']);

    expect($this->asset->assignments()->count())->toBe(2)
        ->and($this->asset->currentAssignment->employee_id)->toBe($other->id)
        ->and($this->asset->events()->pluck('type')->map->value->all())->toContain('registered', 'assigned', 'returned')
        ->and(AssetEvent::query()->where('employee_id', $this->employee->id)->pluck('type')->map->value->sort()->values()->all())->toBe(['assigned', 'returned']);
});

it('allows only the status transitions of the life cycle', function () {
    $status = app(ChangeAssetStatus::class);

    expect($status->available($this->asset))->toBe(['maintenance', 'damaged', 'lost', 'retire']);

    $status->handle($this->asset, 'maintenance', $this->hr, 'Battery');
    expect($this->asset->refresh()->status)->toBe(AssetStatus::InMaintenance);

    $status->handle($this->asset, 'maintenance_done', $this->hr, null, 'good');
    expect($this->asset->refresh()->status)->toBe(AssetStatus::Available);

    handOverAsset($this->asset, $this->employee);
    expect(fn () => $status->handle($this->asset->refresh(), 'retire', $this->hr, 'Old'))->toThrow(ValidationException::class);
});

it('requires a serial number for categories that need one and keeps tags unique per asset', function () {
    $category = AssetCategory::factory()->requiresSerial()->create();

    expect(fn () => app(SaveAsset::class)->handle(null, [
        'asset_tag' => 'lt-9', 'asset_category_id' => $category->id, 'name' => 'Laptop', 'condition' => 'new',
    ], $this->hr))->toThrow(ValidationException::class);

    $asset = app(SaveAsset::class)->handle(null, [
        'asset_tag' => 'lt-9', 'asset_category_id' => $category->id, 'name' => 'Laptop', 'serial_number' => 'X1', 'purchase_cost' => '1000.5', 'condition' => 'new',
    ], $this->hr);

    expect($asset->asset_tag)->toBe('LT-9')
        ->and($asset->purchase_cost)->toBe('1000.50')
        ->and($asset->events()->sole()->type)->toBe(AssetEventType::Registered);
});
