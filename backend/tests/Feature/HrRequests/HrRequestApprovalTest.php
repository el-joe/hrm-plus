<?php

use App\Actions\HrRequests\ManageHrRequest;
use App\Enums\ApprovalStatus;
use App\Enums\HrRequestStatus;
use App\Notifications\HrRequests\HrRequestNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/HrRequestHelpers.php';

/*
| Categories that need approval (workflow "hr_request", permission hr_requests.approve) — Phase 8b.
*/

beforeEach(function () {
    seedHrRequests();
    Notification::fake();

    [$this->employee, $this->user] = hrRequestEmployee();
    $this->hr = adminWithRoles('HR Manager');
    $this->approver = adminWithRoles('Direct Manager');
});

it('waits for approval, tells the approvers, and opens the request for HR once approved', function () {
    $request = fileHrRequest($this->employee, $this->user, 'OTHER');

    expect($request->status)->toBe(HrRequestStatus::PendingApproval)->and($request->due_at)->toBeNull();
    Notification::assertSentTo($this->approver, HrRequestNotification::class, fn ($notification) => $notification->kind === 'awaiting_approval');

    $this->actingAs($this->approver, 'admin')
        ->post(route('admin.hr-requests.decide', [$request, 'approve']), ['reason' => 'OK'])
        ->assertRedirect();

    $request->refresh();
    expect($request->status)->toBe(HrRequestStatus::Open)
        ->and($request->due_at)->not->toBeNull()
        ->and($request->events->pluck('type')->all())->toContain('approval_approved');
});

it('closes the request as rejected with the reason and tells the employee', function () {
    $request = fileHrRequest($this->employee, $this->user, 'OTHER');

    app(ManageHrRequest::class)->decideApproval($request, $this->approver, false, 'Not possible this quarter.');

    expect($request->refresh()->status)->toBe(HrRequestStatus::Rejected)->and($request->rejection_reason)->toBe('Not possible this quarter.');
    Notification::assertSentTo($this->user, HrRequestNotification::class, fn ($notification) => $notification->kind === 'status' && $notification->reason === 'Not possible this quarter.');
});

it('requires a reason to reject and refuses admins without the approval permission', function () {
    $request = fileHrRequest($this->employee, $this->user, 'OTHER');

    expect(fn () => app(ManageHrRequest::class)->decideApproval($request, $this->approver, false, ''))->toThrow(ValidationException::class);

    $this->actingAs(adminWithRoles('HR Specialist'), 'admin')
        ->post(route('admin.hr-requests.decide', [$request, 'approve']))
        ->assertForbidden();
});

it('lets the employee withdraw a request still waiting for approval', function () {
    $request = fileHrRequest($this->employee, $this->user, 'OTHER');

    app(ManageHrRequest::class)->cancel($request, $this->user);

    expect($request->refresh()->status)->toBe(HrRequestStatus::Cancelled)
        ->and($request->latestApprovalRequest->status)->toBe(ApprovalStatus::Cancelled);
});
