<?php

use App\Actions\Leave\ManageLeaveRequest;
use App\Actions\Payroll\ManageLoan;
use App\Actions\Payroll\ManagePayrollRun;
use App\Actions\Payroll\ManagePayslipPayments;
use App\Enums\LoanInstalmentStatus;
use App\Enums\PayrollAdjustmentSource;
use App\Enums\PayrollAdjustmentStatus;
use App\Enums\PayrollLineSource;
use App\Enums\PayrollRunStatus;
use App\Enums\PayrollRunType;
use App\Enums\PayslipStatus;
use App\Models\AuditLog;
use App\Models\LeaveType;
use App\Models\LoanInstalment;
use App\Models\PayrollAdjustment;
use App\Models\PayrollItemLine;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Services\Payroll\PayrollCorrections;
use Carbon\CarbonImmutable;
use Database\Seeders\LeaveDefaultsSeeder;
use Illuminate\Validation\ValidationException;

/*
| Post-finalisation corrections and reversals (spec module 9 F "adjustment
| after finalization through controlled correction / reversal workflows"):
| a finalised run is never edited; late attendance / leave changes become
| pending adjustments carried by a later run (or a correction run) and
| applied when it is finalised; a full reversal mirrors the run, voids its
| payslips, reverses the loan recovery and frees the period.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 09:00:00'));
    seedAttendance();
    $this->seed(LeaveDefaultsSeeder::class);
    payrollSetup();
    leaveWorkWeek();
    $this->employee = paidEmployee('6000.00');
    $this->accountant = adminWithRoles('Payroll Accountant');
    $this->hr = adminWithRoles('HR Manager');
    $this->october = payrollPeriod('2026-10');
    $this->november = payrollPeriod('2026-11');
    $this->runs = app(ManagePayrollRun::class);
    $this->corrections = app(PayrollCorrections::class);
});

it('turns an attendance change after finalisation into a pending correction priced with the frozen rates', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-01 09:00:00'));
    $day = payrollAttendanceDay($this->employee, '2026-10-06', 'absent');
    $october = finalisedRun(calculatedRun($this->october, by: $this->accountant));
    expect(payLine($october, $this->employee, 'ABSENCE')->amount)->toBe('200.00');

    // The absence is corrected afterwards (the employee was present).
    $day->forceFill(['status' => 'present', 'worked_minutes' => 480])->save();
    expect($this->corrections->scan())->toBe(1)
        ->and($this->corrections->scan())->toBe(0);   // idempotent

    $adjustment = PayrollAdjustment::query()->sole();
    expect($adjustment->source)->toBe(PayrollAdjustmentSource::Attendance)
        ->and($adjustment->status)->toBe(PayrollAdjustmentStatus::Pending)
        ->and($adjustment->code)->toBe('ABSENCE')
        ->and((string) $adjustment->amount)->toBe('-200.00')   // a refund of the deduction
        ->and($adjustment->original_period_id)->toBe($this->october->id);

    // The October run itself is untouched.
    expect(payLine($october, $this->employee, 'ABSENCE')->amount)->toBe('200.00')
        ->and($october->refresh()->status)->toBe(PayrollRunStatus::Finalised);
});

it('turns a leave cancellation approved after finalisation into a refund carried by the next run', function () {
    $unpaid = LeaveType::factory()->unpaid()->create();
    $this->travelTo(CarbonImmutable::parse('2026-10-01 08:00:00'));
    $leave = fileLeave($this->employee, $unpaid, '2026-10-11', '2026-10-12', by: $this->hr, recordAsApproved: true);
    $this->travelTo(CarbonImmutable::parse('2026-11-01 09:00:00'));
    $october = finalisedRun(calculatedRun($this->october, by: $this->accountant));
    expect(payLine($october, $this->employee, 'UNPAID')->amount)->toBe('400.00');

    app(ManageLeaveRequest::class)->cancel($leave->refresh(), $this->hr, 'Worked after all', asAdmin: true);

    // Calculating the November run scans first, so the refund is carried without any manual step.
    // (Without punches the two days may now count as absences: that is a separate correction.)
    $this->travelTo(CarbonImmutable::parse('2026-11-25 09:00:00'));
    $november = calculatedRun($this->november, by: $this->accountant);
    $line = PayrollItemLine::query()->where('payroll_run_id', $november->id)->where('source', PayrollLineSource::Correction)->where('code', 'UNPAID')->sole();
    $refund = PayrollAdjustment::query()->where('code', 'UNPAID')->sole();

    expect((string) $line->amount)->toBe('-400.00')
        ->and($refund->source)->toBe(PayrollAdjustmentSource::Leave)
        ->and($refund->payroll_item_id)->toBe(payItem($november, $this->employee)->id);

    finalisedRun($november);

    expect($refund->refresh()->status)->toBe(PayrollAdjustmentStatus::Applied)
        ->and($refund->applied_at)->not->toBeNull();
});

it('records manual corrections and carries them in a correction run', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-01 09:00:00'));
    finalisedRun(calculatedRun($this->october, by: $this->accountant));

    $this->actingAs($this->accountant, 'admin')->post(route('admin.payroll-corrections.store'), [
        'employee_id' => $this->employee->id, 'kind' => 'earning', 'amount' => '350.00', 'original_period_id' => $this->october->id, 'reason' => 'Missed transport',
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->accountant, 'admin')->post(route('admin.payroll-corrections.run'), ['payroll_period_id' => $this->november->id])->assertRedirect();
    $run = PayrollRun::query()->where('type', PayrollRunType::Correction)->sole();
    $run = $this->runs->calculate($run, $this->accountant);

    expect($run->employee_count)->toBe(1)
        ->and(payLine($run, $this->employee, 'ADJ_EARN')->amount)->toBe('350.00')
        ->and(payLine($run, $this->employee, 'BASIC'))->toBeNull()   // corrections only
        ->and(payItem($run, $this->employee)->pay_slot)->toBeNull();   // does not hold the period's pay
});

it('never lets an admin record a correction of their own pay', function () {
    $self = adminWithRoles('Payroll Accountant');
    $self->forceFill(['employee_id' => $this->employee->id])->save();

    $this->actingAs($self, 'admin')->post(route('admin.payroll-corrections.store'), [
        'employee_id' => $this->employee->id, 'kind' => 'earning', 'amount' => '100', 'reason' => 'x',
    ])->assertForbidden();
});

it('dismisses a pending correction with a reason', function () {
    $adjustment = $this->corrections->manual($this->employee, ['employee_id' => $this->employee->id, 'kind' => 'deduction', 'amount' => '50', 'reason' => 'Test'], $this->accountant);

    $this->actingAs($this->accountant, 'admin')->post(route('admin.payroll-corrections.dismiss', $adjustment), ['reason' => 'Duplicate'])->assertRedirect();

    expect($adjustment->refresh()->status)->toBe(PayrollAdjustmentStatus::Dismissed)
        ->and($adjustment->dismiss_reason)->toBe('Duplicate');
});

it('reverses a finalised run: mirror run, void payslips, loan recovery reversed, period free again', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 09:00:00'));
    $loan = requestLoan($this->employee, ['amount' => '6000', 'instalments' => 6, 'start_month' => '2026-10']);
    app(ManageLoan::class)->decide($loan, adminWithRoles('Finance Officer'), true);
    $this->travelTo(CarbonImmutable::parse('2026-11-01 09:00:00'));

    $october = finalisedRun(calculatedRun($this->october, by: $this->accountant));
    $instalment = LoanInstalment::query()->where('employee_loan_id', $loan->id)->orderBy('sequence')->firstOrFail();
    expect($instalment->status)->toBe(LoanInstalmentStatus::Deducted);

    $finance = adminWithRoles('Finance Officer');
    $this->actingAs($finance, 'admin')->post(route('admin.payroll-runs.reverse', $october), ['reason' => 'Wrong structure applied'])->assertRedirect();

    $reversal = PayrollRun::query()->where('type', PayrollRunType::Reversal)->sole();
    expect($october->refresh()->status)->toBe(PayrollRunStatus::Reversed)
        ->and($october->reversal_reason)->toBe('Wrong structure applied')
        ->and((string) $reversal->net_total)->toBe(bcmul((string) $october->net_total, '-1', 2))
        ->and(Payslip::query()->where('payroll_run_id', $october->id)->value('status'))->toBe(PayslipStatus::Void)
        ->and($instalment->refresh()->status)->toBe(LoanInstalmentStatus::Scheduled)
        ->and($loan->refresh()->outstanding_amount)->toBe('6000.00')
        ->and(AuditLog::query()->where('event', 'payroll.run_reversed')->exists())->toBeTrue();

    // The period can be paid again.
    expect(calculatedRun($this->october, by: $this->accountant)->employee_count)->toBe(1);
});

it('refuses to reverse a run once a payment is recorded', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-01 09:00:00'));
    $october = finalisedRun(calculatedRun($this->october, by: $this->accountant));
    $payslip = Payslip::query()->where('payroll_run_id', $october->id)->firstOrFail();
    app(ManagePayslipPayments::class)->record($payslip, ['amount' => '100', 'paid_on' => '2026-11-01', 'method' => 'cash'], adminWithRoles('Finance Officer'));

    expect(fn () => $this->runs->reverse($october, adminWithRoles('Finance Officer'), 'x'))->toThrow(ValidationException::class);
});
