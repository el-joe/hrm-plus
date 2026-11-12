<?php

use App\Actions\HrRequests\ManageHrRequest;
use App\Enums\HrRequestSource;
use App\Enums\HrRequestStatus;
use App\Models\HrRequestEvent;
use App\Notifications\HrRequests\HrRequestNotification;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/HrRequestHelpers.php';

/*
| HR request lifecycle (Phase 8b): submit → SLA / default assignee → reply / internal note → resolve,
| reject, cancel, waiting for the employee, history entries.
*/

beforeEach(function () {
    seedHrRequests();
    Notification::fake();

    [$this->employee, $this->user] = hrRequestEmployee();
    $this->hr = adminWithRoles('HR Manager');
    $this->specialist = adminWithRoles('HR Specialist');
    $this->manage = app(ManageHrRequest::class);
});

it('opens a request with a reference, the category SLA and the default assignee, and tells everyone', function () {
    hrCategory('SALARY_INQUIRY')->update(['default_assignee_id' => $this->specialist->id, 'sla_hours' => 24]);
    $this->freezeSecond();

    $request = fileHrRequest($this->employee, $this->user, 'SALARY_INQUIRY', ['fields' => ['period' => 'October 2026']]);

    expect($request->reference)->toStartWith('HRQ-'.now()->format('Y').'-')
        ->and($request->status)->toBe(HrRequestStatus::Open)
        ->and($request->assigned_to)->toBe($this->specialist->id)
        ->and($request->due_at->equalTo(now()->addHours(24)))->toBeTrue()
        ->and($request->fieldValues)->toHaveCount(1)
        ->and($request->events->pluck('type')->all())->toContain('submitted', 'assigned');

    Notification::assertSentTo($this->user, HrRequestNotification::class, fn ($notification) => $notification->kind === 'received');
    Notification::assertSentTo($this->specialist, HrRequestNotification::class, fn ($notification) => $notification->kind === 'assigned');
});

it('moves through in progress, waiting for the employee and back, then resolves', function () {
    $request = fileHrRequest($this->employee, $this->user);

    $this->manage->start($request, $this->specialist);
    expect($request->refresh()->status)->toBe(HrRequestStatus::InProgress)->and($request->assigned_to)->toBe($this->specialist->id);

    $this->manage->reply($request, $this->specialist, 'Which month do you mean?', awaitEmployee: true);
    expect($request->refresh()->status)->toBe(HrRequestStatus::AwaitingEmployee)->and($request->first_response_at)->not->toBeNull();
    Notification::assertSentTo($this->user, HrRequestNotification::class, fn ($notification) => $notification->kind === 'reply');

    $this->manage->employeeReply($request, $this->user, 'September, please.');
    expect($request->refresh()->status)->toBe(HrRequestStatus::InProgress);
    Notification::assertSentTo($this->specialist, HrRequestNotification::class, fn ($notification) => $notification->kind === 'employee_reply');

    $this->manage->resolve($request, $this->specialist, 'Corrected in the next payroll.');

    expect($request->refresh()->status)->toBe(HrRequestStatus::Resolved)
        ->and($request->resolved_at)->not->toBeNull()
        ->and($request->closed_by)->toBe($this->specialist->id)
        ->and(HrRequestEvent::query()->where('hr_request_id', $request->id)->where('type', 'status_changed')->pluck('to_value')->all())
        ->toBe(['in_progress', 'awaiting_employee', 'in_progress', 'resolved']);
    Notification::assertSentTo($this->user, HrRequestNotification::class, fn ($notification) => $notification->kind === 'status');
});

it('rejects with a reason and refuses further work on a closed request', function () {
    $request = fileHrRequest($this->employee, $this->user);

    $this->manage->reject($request, $this->hr, 'Not an HR matter.');

    expect($request->refresh()->status)->toBe(HrRequestStatus::Rejected)->and($request->rejection_reason)->toBe('Not an HR matter.');
    expect(fn () => $this->manage->reply($request, $this->hr, 'More'))->toThrow(ValidationException::class);
});

it('lets the employee cancel only before HR starts', function () {
    $open = fileHrRequest($this->employee, $this->user);
    $this->manage->cancel($open, $this->user);
    expect($open->refresh()->status)->toBe(HrRequestStatus::Cancelled);

    $started = fileHrRequest($this->employee, $this->user);
    $this->manage->start($started, $this->specialist);

    expect(fn () => $this->manage->cancel($started->refresh(), $this->user))->toThrow(ValidationException::class);
    expect($started->refresh()->status)->toBe(HrRequestStatus::InProgress);

    $this->manage->cancel($started, $this->hr, 'Duplicate.');
    expect($started->refresh()->status)->toBe(HrRequestStatus::Cancelled);
});

it('requires an attachment for categories that ask for one and validates category fields', function () {
    expect(fn () => fileHrRequest($this->employee, $this->user, 'DOCUMENT_REQUEST', ['fields' => ['document_type' => 'ID copy on file']]))
        ->toThrow(ValidationException::class);

    expect(fn () => fileHrRequest($this->employee, $this->user, 'DOCUMENT_REQUEST', ['fields' => ['document_type' => 'Not a choice']], [fakeCv('scan.pdf')]))
        ->toThrow(ValidationException::class);

    $request = fileHrRequest($this->employee, $this->user, 'DOCUMENT_REQUEST', ['fields' => ['document_type' => 'ID copy on file']], [fakeCv('scan.pdf')]);
    expect($request->attachments)->toHaveCount(1);
});

it('refuses new employee requests when the module is switched off', function () {
    app(SettingsService::class)->set('hr_requests.employee_requests_enabled', false);

    expect(fn () => fileHrRequest($this->employee, $this->user))->toThrow(ValidationException::class);

    $request = fileHrRequest($this->employee, $this->hr, source: HrRequestSource::Admin);
    expect($request->status)->toBe(HrRequestStatus::Open);
});
