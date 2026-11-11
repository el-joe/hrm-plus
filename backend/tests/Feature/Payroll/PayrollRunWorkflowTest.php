<?php

use App\Actions\Payroll\ManageLoan;
use App\Actions\Payroll\ManagePayrollRun;
use App\Enums\LoanInstalmentStatus;
use App\Enums\PayrollRunStatus;
use App\Enums\PayrollRunType;
use App\Jobs\CalculatePayrollRun;
use App\Models\AuditLog;
use App\Models\LoanInstalment;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

/*
| The run state machine (spec module 9 F / H): draft → calculate → recalculate
| → review → approve (two levels, distinct approvers, never the submitter) →
| finalise (distinct permission) → locked. Finalising issues payslips, marks
| loan instalments deducted and freezes the data; every transition is audited.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 09:00:00'));
    payrollSetup();
    $this->employee = paidEmployee('6000.00');
    $this->loan = requestLoan($this->employee, ['amount' => '6000', 'instalments' => 6, 'start_month' => '2026-10']);
    app(ManageLoan::class)->decide($this->loan, adminWithRoles('Finance Officer'), true);
    $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00:00'));

    $this->period = payrollPeriod('2026-10');
    $this->accountant = adminWithRoles('Payroll Accountant');
    $this->hr = adminWithRoles('HR Manager');
    $this->finance = adminWithRoles('Finance Officer');
    $this->runs = app(ManagePayrollRun::class);
});

it('moves through draft, calculated, pending approval, approved and finalised', function () {
    $run = $this->runs->create($this->period, PayrollRunType::Regular, ['all' => true], $this->accountant);
    expect($run->status)->toBe(PayrollRunStatus::Draft);

    $run = $this->runs->calculate($run, $this->accountant);
    expect($run->status)->toBe(PayrollRunStatus::Calculated)
        ->and($run->net_total)->not->toBe('0.00');

    $this->runs->submit($run, $this->accountant);
    expect($run->refresh()->status)->toBe(PayrollRunStatus::PendingApproval);

    $this->runs->decide($run, $this->hr, true);
    expect($run->refresh()->status)->toBe(PayrollRunStatus::PendingApproval);

    $this->runs->decide($run->refresh(), $this->finance, true);
    expect($run->refresh()->status)->toBe(PayrollRunStatus::Approved);

    $run = $this->runs->finalise($run, $this->finance);
    expect($run->status)->toBe(PayrollRunStatus::Finalised)
        ->and($run->finalised_by)->toBe($this->finance->id)
        ->and($run->totals_hash)->toHaveLength(64);

    expect(AuditLog::query()->where('auditable_type', 'payroll_run')->where('auditable_id', $run->id)->pluck('event')->all())
        ->toContain('payroll.run_created', 'payroll.run_calculated', 'payroll.run_submitted', 'payroll.run_approved', 'payroll.run_finalised');
});

it('recalculates idempotently and audits the recalculation', function () {
    $run = calculatedRun($this->period, by: $this->accountant);
    $hash = payItem($run, $this->employee)->calculation_hash;

    $run = $this->runs->calculate($run, $this->accountant);

    expect(payItem($run, $this->employee)->calculation_hash)->toBe($hash)
        ->and(AuditLog::query()->where('event', 'payroll.run_recalculated')->exists())->toBeTrue()
        ->and(LoanInstalment::query()->where('employee_loan_id', $this->loan->id)->where('status', LoanInstalmentStatus::Deducted)->exists())->toBeFalse();
});

it('claims the run before calculating so two calculations cannot overlap', function () {
    $run = $this->runs->create($this->period, PayrollRunType::Regular, ['all' => true], $this->accountant);
    $run->forceFill(['status' => PayrollRunStatus::Calculating])->save();

    expect(fn () => $this->runs->calculate($run, $this->accountant))->toThrow(ValidationException::class);
});

it('queues large runs and reports progress', function () {
    Queue::fake();
    app(SettingsService::class)->set('payroll.sync_calculation_limit', 1);
    paidEmployee('5000.00');
    $run = $this->runs->create($this->period, PayrollRunType::Regular, ['all' => true], $this->accountant);

    $run = $this->runs->calculate($run, $this->accountant);

    expect($run->status)->toBe(PayrollRunStatus::Calculating)->and($run->progress_total)->toBe(2);
    Queue::assertPushed(CalculatePayrollRun::class, fn (CalculatePayrollRun $job): bool => $job->runId === $run->id);

    (new CalculatePayrollRun($run->id, $this->accountant->id, 'draft'))->handle($this->runs);
    expect($run->refresh()->status)->toBe(PayrollRunStatus::Calculated)->and($run->progress_done)->toBe(2);

    $this->actingAs($this->accountant, 'admin')->getJson(route('admin.payroll-runs.progress', $run))
        ->assertOk()->assertJson(['status' => 'calculated', 'done' => 2, 'total' => 2, 'percent' => 100]);
});

it('refuses to submit while an employee has a blocking issue', function () {
    $run = calculatedRun($this->period, by: $this->accountant);
    PayrollItem::query()->withoutGlobalScopes()->where('payroll_run_id', $run->id)->update(['has_blocking_warning' => true]);

    expect(fn () => $this->runs->submit($run->refresh(), $this->accountant))->toThrow(ValidationException::class);
});

it('requires two distinct approvers and never lets the submitter approve', function () {
    $run = calculatedRun($this->period, by: $this->accountant);
    $this->runs->submit($run, $this->hr);

    // The submitter cannot act; another approver can, but not twice.
    expect($this->hr->can('decide', $run->refresh()))->toBeFalse()
        ->and($this->finance->can('decide', $run))->toBeTrue();

    $this->runs->decide($run, $this->finance, true);
    expect($this->finance->can('decide', $run->refresh()))->toBeFalse();

    $this->actingAs($this->finance, 'admin')->post(route('admin.payroll-runs.decide', [$run, 'approve']))->assertForbidden();
    $this->actingAs($this->accountant, 'admin')->post(route('admin.payroll-runs.decide', [$run, 'approve']))->assertForbidden();

    $third = adminWithRoles('HR Manager');
    $this->actingAs($third, 'admin')->post(route('admin.payroll-runs.decide', [$run, 'approve']))->assertRedirect();
    expect($run->refresh()->status)->toBe(PayrollRunStatus::Approved);
});

it('sends a rejected run back to review with the reason', function () {
    $run = calculatedRun($this->period, by: $this->accountant);
    $this->runs->submit($run, $this->accountant);

    $this->actingAs($this->hr, 'admin')->post(route('admin.payroll-runs.decide', [$run, 'reject']), ['reason' => 'Overtime of IT looks wrong'])->assertRedirect();

    expect($run->refresh()->status)->toBe(PayrollRunStatus::Calculated)
        ->and(AuditLog::query()->where('event', 'payroll.run_rejected')->value('meta'))->toMatchArray(['reason' => 'Overtime of IT looks wrong']);
});

it('keeps finalisation to the dedicated permission', function () {
    $run = calculatedRun($this->period, by: $this->accountant);
    $this->runs->submit($run, $this->accountant);
    $this->runs->decide($run->refresh(), $this->hr, true);
    $this->runs->decide($run->refresh(), $this->finance, true);

    $this->actingAs($this->accountant, 'admin')->post(route('admin.payroll-runs.finalise', $run))->assertForbidden();
    $this->actingAs($this->hr, 'admin')->post(route('admin.payroll-runs.finalise', $run))->assertForbidden();
    $this->actingAs($this->finance, 'admin')->post(route('admin.payroll-runs.finalise', $run))->assertRedirect();

    expect($run->refresh()->status)->toBe(PayrollRunStatus::Finalised);
});

it('issues payslips and marks the loan instalment deducted only on finalisation', function () {
    $run = calculatedRun($this->period, by: $this->accountant);
    $instalment = LoanInstalment::query()->where('employee_loan_id', $this->loan->id)->orderBy('sequence')->firstOrFail();

    expect($instalment->status)->toBe(LoanInstalmentStatus::Scheduled)->and(Payslip::query()->count())->toBe(0);

    $run = finalisedRun($run, $this->accountant);
    $item = payItem($run, $this->employee);

    expect($instalment->refresh()->status)->toBe(LoanInstalmentStatus::Deducted)
        ->and($instalment->settled_by_type)->toBe('payroll_item')
        ->and($instalment->settled_by_id)->toBe($item->id)
        ->and($this->loan->refresh()->outstanding_amount)->toBe('5000.00')
        ->and(Payslip::query()->where('payroll_item_id', $item->id)->value('net_pay'))->toBe((string) $item->net_pay);
});

it('never changes a finalised run', function () {
    $run = finalisedRun(calculatedRun($this->period, by: $this->accountant), $this->accountant);
    $item = payItem($run, $this->employee);
    $before = [$item->calculation_hash, (string) $item->net_pay, $item->lines()->count()];

    payrollAttendanceDay($this->employee, '2026-10-06', 'absent');

    expect(fn () => $this->runs->calculate($run, $this->accountant))->toThrow(ValidationException::class)
        ->and(fn () => $this->runs->updateScope($run, ['all' => true], $this->accountant))->toThrow(ValidationException::class)
        ->and(fn () => $this->runs->cancel($run, $this->accountant, 'x'))->toThrow(ValidationException::class);

    $item->refresh();
    expect([$item->calculation_hash, (string) $item->net_pay, $item->lines()->count()])->toBe($before);

    $this->actingAs($this->accountant, 'admin')->post(route('admin.payroll-runs.calculate', $run))->assertForbidden();
});

it('stores the full calculation snapshot with every item', function () {
    $run = calculatedRun($this->period, by: $this->accountant);
    $item = payItem($run, $this->employee);

    expect($item->inputs)->toHaveKeys(['salary_segments', 'structure', 'entries', 'loan_instalments', 'statutory', 'contexts', 'period'])
        ->and($item->rule_versions)->toHaveKeys(['structure', 'statutory', 'salary_ids', 'entries', 'attendance_policy_version_ids'])
        ->and($item->quantities)->toHaveKeys(['attendance', 'totals', 'placement'])
        ->and($item->calculation_hash)->toHaveLength(64)
        ->and($item->lines()->where('code', 'BASIC')->value('explanation'))->not->toBeEmpty();
});

it('denies payroll runs to admins without payroll permissions', function () {
    $run = calculatedRun($this->period, by: $this->accountant);
    $officer = adminWithRoles('Attendance Officer');

    $this->actingAs($officer, 'admin')->get(route('admin.payroll-runs.index'))->assertForbidden();
    $this->actingAs($officer, 'admin')->get(route('admin.payroll-runs.show', $run))->assertForbidden();
    $this->actingAs($officer, 'admin')->get(route('admin.payroll-runs.items.show', [$run, payItem($run, $this->employee)]))->assertForbidden();
});

it('renders the run screens for the accountant', function () {
    $run = calculatedRun($this->period, by: $this->accountant);

    $this->actingAs($this->accountant, 'admin')->get(route('admin.payroll-runs.index'))->assertOk()->assertSee($run->reference);
    $this->actingAs($this->accountant, 'admin')->get(route('admin.payroll-runs.create'))->assertOk();
    foreach (['employees', 'components', 'approvals', 'history'] as $tab) {
        $this->actingAs($this->accountant, 'admin')->get(route('admin.payroll-runs.show', [$run, 'tab' => $tab]))->assertOk();
    }
    $this->actingAs($this->accountant, 'admin')->get(route('admin.payroll-runs.items.show', [$run, payItem($run, $this->employee)]))
        ->assertOk()->assertSee('6,000.00');
    $this->actingAs($this->accountant, 'admin')->get(route('admin.payroll-periods.index'))->assertOk()->assertSee($this->period->code);
});

it('creates monthly and custom periods and refuses overlapping months', function () {
    $this->actingAs($this->accountant, 'admin')->post(route('admin.payroll-periods.store'), ['type' => 'monthly', 'month' => '2026-11'])->assertRedirect();
    $this->actingAs($this->accountant, 'admin')->post(route('admin.payroll-periods.store'), ['type' => 'monthly', 'month' => '2026-11'])->assertSessionHasErrors('month');
    $this->actingAs($this->accountant, 'admin')->post(route('admin.payroll-periods.store'), [
        'type' => 'custom', 'code' => 'BONUS-2026', 'name_ar' => 'مكافأة', 'name_en' => 'Bonus', 'start_date' => '2026-12-01', 'end_date' => '2026-12-15', 'pay_date' => '2026-12-16',
    ])->assertRedirect();

    expect(PayrollPeriod::query()->where('code', '2026-11')->value('end_date')?->toDateString())->toBe('2026-11-30')
        ->and(PayrollPeriod::query()->where('code', 'BONUS-2026')->exists())->toBeTrue();
});
