<?php

use App\Actions\Leave\ManageLeaveRequest;
use App\Enums\ApprovalStatus;
use App\Enums\AttendanceDayStatus;
use App\Enums\Gender;
use App\Enums\LeaveRequestSource;
use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveUnit;
use App\Models\AttendanceDay;
use App\Models\AttendanceStatus;
use App\Models\Holiday;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Notifications\Leave\LeaveRequestNotification;
use App\Services\Leave\LeavePayrollFeed;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/*
| Leave requests (Phase 6): working-day counting (rest days, holidays, half
| days, hours), overlap, balance reservation, the two-level approval
| workflow with rejection and the self-approval block, cancellation before
| and after the start, the attendance integration (on_leave days created and
| removed) and the unpaid-leave feed for payroll.
|
| "Today" is Sunday 2026-11-01; the company works Sunday–Thursday 08:00–16:00.
*/

beforeEach(function () {
    seedLeave();
    $this->travelTo(CarbonImmutable::parse('2026-11-01 07:00:00'));
    leaveWorkWeek();
    $this->annual = leaveTypeWithPolicy(['unit' => LeaveUnit::HalfDay]);
    $this->employee = attendanceEmployee(['gender' => Gender::Male]);
    $this->account = User::factory()->employee()->create(['employee_id' => $this->employee->id]);
    $this->manager = adminWithRoles('Direct Manager');
    $this->hr = adminWithRoles('HR Manager');
    accrueLeave('2026-01-01');
});

function decideLeave(LeaveRequest $leave, User $approver, bool $approve = true, ?string $reason = null): LeaveRequest
{
    app(ManageLeaveRequest::class)->decide($leave->refresh(), $approver, $approve, $reason);

    return $leave->refresh();
}

function approvedLeave(object $test, LeaveType $type, string $start, ?string $end = null): LeaveRequest
{
    $leave = fileLeave($test->employee, $type, $start, $end, by: $test->account, source: LeaveRequestSource::Portal);
    decideLeave($leave, $test->manager);

    return decideLeave($leave, $test->hr);
}

describe('counting', function () {
    it('counts working days only, skipping rest days and public holidays', function () {
        Holiday::create(['name_ar' => 'عيد', 'name_en' => 'Feast', 'start_date' => '2026-11-09', 'end_date' => '2026-11-09', 'is_recurring' => false, 'is_paid' => true, 'is_active' => true]);

        $leave = fileLeave($this->employee, $this->annual, '2026-11-04', '2026-11-10'); // Wed … Tue

        expect($leave->days)->toBe('4.00')
            ->and($leave->requestDays()->where('is_counted', false)->pluck('day_kind', 'date')->mapWithKeys(fn ($kind, $date) => [substr($date, 0, 10) => $kind])->all())
            ->toBe(['2026-11-06' => 'rest_day', '2026-11-07' => 'rest_day', '2026-11-09' => 'holiday']);
    });

    it('counts calendar days for types that count rest days and holidays', function () {
        $maternity = leaveTypeWithPolicy(['counts_rest_days' => true, 'counts_holidays' => true], ['entitlement_days' => '90']);
        Holiday::create(['name_ar' => 'عيد', 'name_en' => 'Feast', 'start_date' => '2026-11-09', 'end_date' => '2026-11-09', 'is_recurring' => false, 'is_paid' => true, 'is_active' => true]);
        accrueLeave('2026-01-01'); // credit the entitlement of the types created in this test

        expect(fileLeave($this->employee, $maternity, '2026-11-04', '2026-11-10')->days)->toBe('7.00');
    });

    it('charges half a day for a half day and a fraction of the scheduled hours for hourly leave', function () {
        $casual = leaveTypeWithPolicy(['unit' => LeaveUnit::Hour], ['entitlement_days' => '7']);
        accrueLeave('2026-01-01'); // credit the entitlement of the types created in this test

        $half = fileLeave($this->employee, $this->annual, '2026-11-04', null, ['day_part' => 'first_half']);
        $hours = fileLeave($this->employee, $casual, '2026-11-05', null, ['day_part' => 'hours', 'hours' => '2']);

        expect($half->days)->toBe('0.50')->and($half->end_date->format('Y-m-d'))->toBe('2026-11-04')
            ->and($hours->days)->toBe('0.25'); // 2 of 8 scheduled hours
    });

    it('refuses a unit the leave type does not allow and a period without working days', function () {
        $daysOnly = leaveTypeWithPolicy(['unit' => LeaveUnit::Day]);

        expect(fn () => fileLeave($this->employee, $daysOnly, '2026-11-04', null, ['day_part' => 'second_half']))->toThrow(ValidationException::class)
            ->and(fn () => fileLeave($this->employee, $this->annual, '2026-11-06', '2026-11-07'))->toThrow(ValidationException::class);
    });

    it('refuses dates outside employment', function () {
        $newcomer = attendanceEmployee(['hire_date' => '2026-11-10', 'original_hire_date' => '2026-11-10']);
        $type = leaveTypeWithPolicy(policy: ['entitlement_days' => '5', 'allow_negative_balance' => true]);

        expect(fn () => fileLeave($newcomer, $type, '2026-11-08', '2026-11-11'))->toThrow(ValidationException::class);
    });
});

describe('overlap and balance', function () {
    it('prevents overlapping leave but allows the two halves of one day', function () {
        fileLeave($this->employee, $this->annual, '2026-11-08', '2026-11-10');

        expect(fn () => fileLeave($this->employee, $this->annual, '2026-11-10', '2026-11-12'))->toThrow(ValidationException::class);

        fileLeave($this->employee, $this->annual, '2026-11-15', null, ['day_part' => 'first_half']);
        fileLeave($this->employee, $this->annual, '2026-11-15', null, ['day_part' => 'second_half']);

        expect(fn () => fileLeave($this->employee, $this->annual, '2026-11-15', null, ['day_part' => 'first_half']))->toThrow(ValidationException::class);
    });

    it('frees the dates of rejected and withdrawn requests', function () {
        $leave = fileLeave($this->employee, $this->annual, '2026-11-08', '2026-11-10', by: $this->account, source: LeaveRequestSource::Portal);
        decideLeave($leave, $this->manager, false, 'Busy week');

        expect(fileLeave($this->employee, $this->annual, '2026-11-08', '2026-11-10')->status)->toBe(LeaveRequestStatus::Pending);
    });

    it('reserves the days of a pending request and releases them on rejection and withdrawal', function () {
        $leave = fileLeave($this->employee, $this->annual, '2026-11-08', '2026-11-12', by: $this->account, source: LeaveRequestSource::Portal);
        expect(availableLeave($this->employee, $this->annual))->toBe('16.00')
            ->and(LeaveBalanceTransaction::query()->where('type', 'usage')->exists())->toBeFalse();

        decideLeave($leave, $this->manager, false, 'Inventory week');
        expect(availableLeave($this->employee, $this->annual))->toBe('21.00');

        $second = fileLeave($this->employee, $this->annual, '2026-11-15', '2026-11-16', by: $this->account, source: LeaveRequestSource::Portal);
        expect(availableLeave($this->employee, $this->annual))->toBe('19.00');

        app(ManageLeaveRequest::class)->cancel($second, $this->account);
        expect($second->refresh()->status)->toBe(LeaveRequestStatus::Cancelled)
            ->and($second->latestApprovalRequest->status)->toBe(ApprovalStatus::Cancelled)
            ->and(availableLeave($this->employee, $this->annual))->toBe('21.00');
    });

    it('applies notice, past-date, per-request, attachment and eligibility rules to employees', function () {
        $portal = fn (LeaveType $type, string $start, ?string $end = null, array $files = []) => fileLeave(
            $this->employee, $type, $start, $end, by: $this->account, source: LeaveRequestSource::Portal, attachments: $files,
        );
        $noticed = leaveTypeWithPolicy(['notice_days' => 7]);
        $sick = leaveTypeWithPolicy(['allows_past_dates' => true, 'requires_attachment' => true, 'attachment_after_days' => '1', 'max_days_per_request' => '3'], ['entitlement_days' => '15']);
        $maternity = leaveTypeWithPolicy(['gender' => Gender::Female], ['entitlement_days' => '90']);
        $hajj = leaveTypeWithPolicy(['max_times_per_service' => 1], ['entitlement_days' => '30']);
        accrueLeave('2026-01-01'); // credit the entitlement of the types created in this test
        Storage::fake(config('employees.disk'));
        $certificate = UploadedFile::fake()->create('certificate.pdf', 50, 'application/pdf');

        expect(fn () => $portal($noticed, '2026-11-04'))->toThrow(ValidationException::class)
            ->and($portal($noticed, '2026-11-08')->status)->toBe(LeaveRequestStatus::Pending)
            ->and(fn () => $portal($this->annual, '2026-10-26'))->toThrow(ValidationException::class) // past dates not allowed for this type
            ->and(fn () => $portal($sick, '2026-08-02'))->toThrow(ValidationException::class) // beyond leave.max_past_days
            ->and($portal($sick, '2026-10-26')->days)->toBe('1.00') // one day: no certificate needed
            ->and(fn () => $portal($sick, '2026-10-27', '2026-10-28'))->toThrow(ValidationException::class) // two days need one
            ->and($portal($sick, '2026-10-27', '2026-10-28', [$certificate])->attachments()->count())->toBe(1)
            ->and(fn () => $portal($sick, '2026-11-15', '2026-11-18'))->toThrow(ValidationException::class) // 4 > 3 per request
            ->and(fn () => $portal($maternity, '2026-11-15'))->toThrow(ValidationException::class);

        $portal($hajj, '2026-11-22');
        expect(fn () => $portal($hajj, '2026-11-29'))->toThrow(ValidationException::class);
    });
});

describe('approval', function () {
    it('goes through the manager, then HR, books the usage and notifies', function () {
        Notification::fake();

        $leave = fileLeave($this->employee, $this->annual, '2026-11-08', '2026-11-12', by: $this->account, source: LeaveRequestSource::Portal);
        Notification::assertSentTo($this->manager, LeaveRequestNotification::class, fn ($notification) => $notification->kind === 'awaiting_approval');

        decideLeave($leave, $this->manager);
        expect($leave->status)->toBe(LeaveRequestStatus::Pending)
            ->and($leave->latestApprovalRequest->currentStep->sequence)->toBe(2)
            ->and(LeaveBalanceTransaction::query()->where('type', 'usage')->exists())->toBeFalse();
        Notification::assertSentTo($this->hr, LeaveRequestNotification::class, fn ($notification) => $notification->kind === 'awaiting_approval');

        decideLeave($leave, $this->hr);
        $usage = LeaveBalanceTransaction::query()->where('type', 'usage')->sole();

        expect($leave->status)->toBe(LeaveRequestStatus::Approved)
            ->and($leave->decided_by)->toBe($this->hr->id)
            ->and($usage->amount)->toBe('-5.00')
            ->and($usage->reference)->toBe("usage:{$leave->id}:2026")
            ->and(availableLeave($this->employee, $this->annual))->toBe('16.00');
        Notification::assertSentTo($this->account, LeaveRequestNotification::class, fn ($notification) => $notification->kind === 'approved');
    });

    it('needs a reason to reject, stores it and tells the employee', function () {
        Notification::fake();
        $leave = fileLeave($this->employee, $this->annual, '2026-11-08', null, by: $this->account, source: LeaveRequestSource::Portal);

        expect(fn () => decideLeave($leave, $this->manager, false, ' '))->toThrow(ValidationException::class);

        decideLeave($leave, $this->manager, false, 'Stock count that day');

        expect($leave->status)->toBe(LeaveRequestStatus::Rejected)
            ->and($leave->rejection_reason)->toBe('Stock count that day')
            ->and($leave->decided_by)->toBe($this->manager->id);
        Notification::assertSentTo($this->account, LeaveRequestNotification::class, fn ($notification) => $notification->kind === 'rejected' && $notification->reason === 'Stock count that day');
    });

    it('never lets an approver decide on their own leave', function () {
        // One account per employee (users.employee_id is unique): the admin account replaces the portal one.
        $this->account->forceFill(['employee_id' => null])->save();
        $hrEmployee = adminWithRoles('HR Manager');
        $hrEmployee->forceFill(['employee_id' => $this->employee->id])->save();
        $leave = fileLeave($this->employee, $this->annual, '2026-11-08', null);
        decideLeave($leave, $this->manager);

        expect(fn () => decideLeave($leave, $hrEmployee->fresh()))->toThrow(ValidationException::class)
            ->and($hrEmployee->fresh()->can('decide', $leave->refresh()))->toBeFalse();
    });

    it('lets HR record leave as approved without a workflow, but not their own', function () {
        $leave = fileLeave($this->employee, $this->annual, '2026-11-08', '2026-11-09', by: $this->hr, recordAsApproved: true);

        expect($leave->status)->toBe(LeaveRequestStatus::Approved)
            ->and($leave->approvalRequests()->exists())->toBeFalse()
            ->and(availableLeave($this->employee, $this->annual))->toBe('19.00')
            ->and(fn () => fileLeave($this->employee, $this->annual, '2026-11-15', null, by: adminWithRoles('HR Specialist'), recordAsApproved: true))
            ->toThrow(ValidationException::class);
    });

    it('uses the workflow configured on the leave type', function () {
        $sick = leaveTypeWithPolicy(['approval_workflow' => 'leave_request_hr', 'allows_past_dates' => true], ['entitlement_days' => '15']);
        accrueLeave('2026-01-01'); // credit the entitlement of the types created in this test
        $leave = fileLeave($this->employee, $sick, '2026-11-08', null, by: $this->account, source: LeaveRequestSource::Portal);

        expect($this->manager->can('decide', $leave))->toBeFalse();

        expect(decideLeave($leave, $this->hr)->status)->toBe(LeaveRequestStatus::Approved);
    });
});

describe('cancellation', function () {
    it('cancels approved leave that has not started at once and returns the days', function () {
        $leave = approvedLeave($this, $this->annual, '2026-11-08', '2026-11-10');

        $status = app(ManageLeaveRequest::class)->cancel($leave, $this->account, 'Plans changed');

        expect($status)->toBe(LeaveRequestStatus::Cancelled)
            ->and(LeaveBalanceTransaction::query()->where('type', 'reversal')->sole()->amount)->toBe('3.00')
            ->and(availableLeave($this->employee, $this->annual))->toBe('21.00');
    });

    it('needs approval to cancel leave that has started, and keeps it until decided', function () {
        $leave = approvedLeave($this, $this->annual, '2026-11-08', '2026-11-12');
        $this->travelTo(CarbonImmutable::parse('2026-11-10 07:00:00'));

        expect(fn () => app(ManageLeaveRequest::class)->cancel($leave, $this->account))->toThrow(ValidationException::class); // reason required

        $status = app(ManageLeaveRequest::class)->cancel($leave, $this->account, 'Back to cover a colleague');
        expect($status)->toBe(LeaveRequestStatus::CancellationPending)
            ->and($leave->refresh()->isTaken())->toBeTrue()
            ->and(availableLeave($this->employee, $this->annual))->toBe('16.00');

        decideLeave($leave, $this->hr, false, 'Leave already planned around');
        expect($leave->status)->toBe(LeaveRequestStatus::Approved);

        app(ManageLeaveRequest::class)->cancel($leave, $this->account, 'Asking again');
        decideLeave($leave, $this->hr);

        expect($leave->status)->toBe(LeaveRequestStatus::Cancelled)
            ->and(availableLeave($this->employee, $this->annual))->toBe('21.00');
    });

    it('sends a cancellation request through approval when the setting asks for it even before the start', function () {
        app(SettingsService::class)->set('leave.cancel_before_start_directly', false);
        $leave = approvedLeave($this, $this->annual, '2026-11-08');

        expect(app(ManageLeaveRequest::class)->cancel($leave, $this->account, 'Changed my mind'))->toBe(LeaveRequestStatus::CancellationPending);
    });

    it('refuses to cancel rejected or cancelled requests', function () {
        $leave = fileLeave($this->employee, $this->annual, '2026-11-08', null, by: $this->account, source: LeaveRequestSource::Portal);
        decideLeave($leave, $this->manager, false, 'No');

        expect(fn () => app(ManageLeaveRequest::class)->cancel($leave, $this->account))->toThrow(ValidationException::class);
    });
});

describe('attendance integration', function () {
    it('turns approved past leave into on_leave days and back when it is cancelled', function () {
        $sickStatus = AttendanceStatus::query()->where('code', 'sick_leave')->value('id');
        $sick = leaveTypeWithPolicy(['allows_past_dates' => true, 'attendance_status_id' => $sickStatus], ['entitlement_days' => '15']);
        accrueLeave('2026-01-01'); // credit the entitlement of the types created in this test
        $this->travelTo(CarbonImmutable::parse('2026-11-12 07:00:00'));
        punchAt($this->employee, '2026-11-09 08:00', 'in');
        punchAt($this->employee, '2026-11-09 16:00', 'out');
        processDay($this->employee, '2026-11-08');
        processDay($this->employee, '2026-11-09');
        expect(AttendanceDay::query()->whereDate('work_date', '2026-11-08')->value('status'))->toBe(AttendanceDayStatus::Absent);

        $annual = fileLeave($this->employee, $this->annual, '2026-11-08'); // past date: filed by HR
        decideLeave($annual, $this->manager);
        decideLeave($annual, $this->hr);
        $sickLeave = fileLeave($this->employee, $sick, '2026-11-10', '2026-11-11', by: $this->hr, recordAsApproved: true);

        $day = fn (string $date) => AttendanceDay::query()->where('employee_id', $this->employee->id)->whereDate('work_date', $date)->first();
        expect($day('2026-11-08')->status)->toBe(AttendanceDayStatus::OnLeave)
            ->and($day('2026-11-08')->leave_reference)->toBe('leave_request:'.$annual->id)
            ->and($day('2026-11-10')->status)->toBe(AttendanceDayStatus::OnLeave)
            ->and($day('2026-11-10')->attendanceStatus->code)->toBe('sick_leave')
            ->and($day('2026-11-11')->attendanceStatus->code)->toBe('sick_leave')
            ->and($day('2026-11-09')->status)->toBe(AttendanceDayStatus::Present);

        app(ManageLeaveRequest::class)->cancel($sickLeave, $this->hr, 'Entered for the wrong employee', asAdmin: true);

        expect($day('2026-11-10')->status)->toBe(AttendanceDayStatus::Absent)
            ->and($day('2026-11-10')->leave_reference)->toBeNull();
    });

    it('leaves future dates to the nightly processing', function () {
        approvedLeave($this, $this->annual, '2026-11-08', '2026-11-09');

        expect(AttendanceDay::query()->where('employee_id', $this->employee->id)->exists())->toBeFalse();
    });

    it('reports half-day leave without marking the whole day as leave', function () {
        $this->travelTo(CarbonImmutable::parse('2026-11-12 07:00:00'));
        $leave = fileLeave($this->employee, $this->annual, '2026-11-09', null, ['day_part' => 'second_half'], by: $this->hr, recordAsApproved: true);
        punchAt($this->employee, '2026-11-09 08:00', 'in');
        punchAt($this->employee, '2026-11-09 12:00', 'out');
        $day = processDay($this->employee, '2026-11-09')->first();

        expect($leave->days)->toBe('0.50')
            ->and($day->status)->not->toBe(AttendanceDayStatus::Absent)
            ->and($day->flags)->toContain('half_day_leave');
    });
});

describe('payroll feed', function () {
    it('reports the unpaid days of taken unpaid leave in a period', function () {
        $unpaid = LeaveType::factory()->unpaid()->create();
        $feed = app(LeavePayrollFeed::class);

        fileLeave($this->employee, $unpaid, '2026-11-08', '2026-11-09', by: $this->hr, recordAsApproved: true);
        fileLeave($this->employee, $unpaid, '2026-11-30', '2026-12-02', by: $this->hr, recordAsApproved: true); // 1 day in November
        fileLeave($this->employee, $unpaid, '2026-11-15'); // pending: not deducted
        $cancelled = fileLeave($this->employee, $unpaid, '2026-11-16', null, by: $this->hr, recordAsApproved: true);
        app(ManageLeaveRequest::class)->cancel($cancelled, $this->hr, 'Not needed', asAdmin: true);
        fileLeave($this->employee, $this->annual, '2026-11-18', null, by: $this->hr, recordAsApproved: true); // paid

        $from = CarbonImmutable::parse('2026-11-01');
        $to = CarbonImmutable::parse('2026-11-30');

        expect($feed->unpaidDays($this->employee, $from, $to))->toBe('3.00')
            ->and($feed->unpaidBreakdown($this->employee, $from, $to)->pluck('date')->all())->toBe(['2026-11-08', '2026-11-09', '2026-11-30'])
            ->and(LeaveBalanceTransaction::query()->where('leave_type_id', $unpaid->id)->exists())->toBeFalse();
    });
});
