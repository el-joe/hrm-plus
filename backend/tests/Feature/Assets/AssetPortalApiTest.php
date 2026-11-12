<?php

use App\Actions\Assets\ManageAssetCustody;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

/*
| "My assets" in the portal and GET/POST /api/v1/me/assets (Phase 8a): own
| custody only, acknowledgement of receipt, no purchase values exposed.
*/

beforeEach(function () {
    Notification::fake();
    $hr = adminWithRoles('HR Manager');
    $this->employee = Employee::factory()->create(['hire_date' => '2024-01-01']);
    $this->user = User::factory()->employee()->create(['employee_id' => $this->employee->id]);
    $this->other = Employee::factory()->create(['hire_date' => '2024-01-01']);

    $assign = fn (Employee $employee, string $tag): AssetAssignment => app(ManageAssetCustody::class)->assign(
        Asset::factory()->create(['asset_tag' => $tag, 'branch_id' => $employee->branch_id, 'purchase_cost' => '42500.00']),
        $employee, ['assigned_on' => '2026-01-01', 'condition_on_issue' => 'good', 'handover_notes' => 'Bag included'], $hr,
    );

    $this->mine = $assign($this->employee, 'LT-MINE');
    $this->returned = $assign($this->employee, 'LT-OLD');
    app(ManageAssetCustody::class)->returnAsset($this->returned, ['returned_on' => '2026-02-01', 'condition_on_return' => 'good'], $hr);
    $this->theirs = $assign($this->other, 'LT-THEIRS');
});

it('shows the employee their own assets in the portal', function () {
    $this->actingAs($this->user, 'employee')->get(route('portal.assets'))
        ->assertOk()->assertSee('LT-MINE')->assertSee('LT-OLD')->assertDontSee('LT-THEIRS')->assertDontSee('42,500');
});

it('lets the employee acknowledge receipt in the portal', function () {
    $this->actingAs($this->user, 'employee')->post(route('portal.assets.acknowledge', $this->mine->id), ['note' => 'All good'])
        ->assertRedirect(route('portal.assets'));

    expect($this->mine->refresh()->acknowledged_via)->toBe('portal');
});

it('answers 404 when acknowledging another employee\'s asset', function () {
    $this->actingAs($this->user, 'employee')->post(route('portal.assets.acknowledge', $this->theirs->id))->assertNotFound();

    expect($this->theirs->refresh()->acknowledged_at)->toBeNull();
});

it('returns current custody and history through the API', function () {
    Sanctum::actingAs($this->user);

    $this->getJson(route('api.v1.me.assets.index'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.current')
        ->assertJsonCount(1, 'data.history')
        ->assertJsonPath('data.current.0.asset.tag', 'LT-MINE')
        ->assertJsonPath('data.current.0.can_acknowledge', true)
        ->assertJsonPath('data.current.0.handover_notes', 'Bag included')
        ->assertJsonPath('data.history.0.status', 'returned')
        ->assertJsonPath('meta.unacknowledged', 1)
        ->assertJsonMissingPath('data.current.0.asset.purchase_cost');
});

it('acknowledges through the API once and keeps others\' records out of reach', function () {
    Sanctum::actingAs($this->user);

    $this->postJson(route('api.v1.me.assets.acknowledge', $this->mine->id))
        ->assertOk()->assertJsonPath('data.acknowledged', true)->assertJsonPath('data.can_acknowledge', false);
    expect($this->mine->refresh()->acknowledged_via)->toBe('api');

    $this->postJson(route('api.v1.me.assets.acknowledge', $this->mine->id))->assertUnprocessable();
    $this->getJson(route('api.v1.me.assets.show', $this->theirs->id))->assertNotFound();
    $this->postJson(route('api.v1.me.assets.acknowledge', $this->theirs->id))->assertNotFound();
});

it('refuses admin tokens on the assets API', function () {
    Sanctum::actingAs(adminWithRoles('HR Manager'));

    $this->getJson(route('api.v1.me.assets.index'))->assertForbidden();
});
