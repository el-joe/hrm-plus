<?php

use App\Actions\HrRequests\ManageHrRequest;
use App\Models\HrRequest;
use App\Notifications\HrRequests\HrRequestNotification;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/HrRequestHelpers.php';

/*
| SLA escalation (hr-requests:escalate, Phase 8b): overdue open / in-progress requests climb one level per
| repeat window up to the maximum; requests waiting for the employee or closed are never escalated.
*/

beforeEach(function () {
    seedHrRequests();
    Notification::fake();

    [$this->employee, $this->user] = hrRequestEmployee();
    $this->hr = adminWithRoles('HR Manager');
    $this->specialist = adminWithRoles('HR Specialist');
    hrCategory('GENERAL_INQUIRY')->update(['sla_hours' => 24, 'escalation_user_id' => $this->hr->id]);
    app(SettingsService::class)->setMany(['hr_requests.escalation_repeat_hours' => 12, 'hr_requests.max_escalation_level' => 2]);
});

it('escalates an overdue request and tells the escalation user and the assignee', function () {
    $this->travelTo(now()->subHours(30));
    $request = fileHrRequest($this->employee, $this->user);
    app(ManageHrRequest::class)->start($request, $this->specialist);
    $this->travelBack();

    $this->artisan('hr-requests:escalate')->assertSuccessful();

    $request->refresh();
    expect($request->escalation_level)->toBe(1)->and($request->last_escalated_at)->not->toBeNull()
        ->and($request->events()->where('type', 'escalated')->count())->toBe(1);
    Notification::assertSentTo($this->hr, HrRequestNotification::class, fn ($notification) => $notification->kind === 'escalated');
    Notification::assertSentTo($this->specialist, HrRequestNotification::class, fn ($notification) => $notification->kind === 'escalated');
    Notification::assertNotSentTo($this->user, HrRequestNotification::class, fn ($notification) => $notification->kind === 'escalated');
});

it('waits for the repeat window and stops at the maximum level', function () {
    $this->travelTo(now()->subHours(60));
    $request = fileHrRequest($this->employee, $this->user);
    $this->travelBack();

    $this->artisan('hr-requests:escalate');
    $this->artisan('hr-requests:escalate');
    expect($request->refresh()->escalation_level)->toBe(1);

    $this->travel(13)->hours();
    $this->artisan('hr-requests:escalate');
    expect($request->refresh()->escalation_level)->toBe(2);

    $this->travel(13)->hours();
    $this->artisan('hr-requests:escalate');
    expect($request->refresh()->escalation_level)->toBe(2);
});

it('never escalates requests waiting for the employee, closed or not yet due', function () {
    $this->travelTo(now()->subHours(48));
    $waiting = fileHrRequest($this->employee, $this->user);
    app(ManageHrRequest::class)->reply($waiting, $this->specialist, 'Please send the document.', awaitEmployee: true);
    $closed = fileHrRequest($this->employee, $this->user);
    app(ManageHrRequest::class)->resolve($closed, $this->specialist, 'Done.');
    $this->travelBack();
    $fresh = fileHrRequest($this->employee, $this->user);

    $this->artisan('hr-requests:escalate');

    expect(HrRequest::query()->whereKey([$waiting->id, $closed->id, $fresh->id])->pluck('escalation_level')->unique()->all())->toBe([0]);
});

it('does nothing when escalation is switched off', function () {
    app(SettingsService::class)->set('hr_requests.escalation_enabled', false);
    $this->travelTo(now()->subHours(48));
    $request = fileHrRequest($this->employee, $this->user);
    $this->travelBack();

    $this->artisan('hr-requests:escalate');

    expect($request->refresh()->escalation_level)->toBe(0);
});
