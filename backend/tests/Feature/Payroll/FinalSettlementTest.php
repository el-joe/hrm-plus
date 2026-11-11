<?php

use App\Actions\Offboarding\ManageOffboardingCase;
use App\Actions\Offboarding\OpenOffboardingCase;
use App\Actions\Payroll\CreateFinalSettlement;
use App\Actions\Payroll\ManageLoan;
use App\Actions\Payroll\ManagePayrollRun;
use App\Enums\PayrollLineSource;
use App\Enums\PayrollRunType;
use App\Enums\TaskStatus;
use App\Models\OffboardingCase;
use App\Models\PayrollItemLine;
use App\Models\PayrollRun;
use App\Services\Payroll\PayrollPopulation;
use Carbon\CarbonImmutable;
use Database\Seeders\LeaveDefaultsSeeder;
use Illuminate\Validation\ValidationException;

/*
| Offboarding final settlement: the case's "Final financial settlement" item
| opens an off-cycle run (prorated salary to the last working day, leave
| encashment through the leave policy, recovery of every scheduled loan
| instalment) that takes the employee's pay slot of the period; finalising
| it marks the item done.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 09:00:00'));
    seedAttendance();
    $this->seed(LeaveDefaultsSeeder::class);
    payrollSetup();
    $this->employee = paidEmployee('6000.00');
    $this->annual = leaveTypeWithPolicy([], ['encashable' => true, 'encashment_max_days' => '10']);
    accrueLeave('2026-01-01', $this->employee->id);
    $this->loan = requestLoan($this->employee, ['amount' => '3000', 'instalments' => 3, 'start_month' => '2026-10']);
    app(ManageLoan::class)->decide($this->loan, adminWithRoles('Finance Officer'), true);

    $this->travelTo(CarbonImmutable::parse('2026-10-25 09:00:00'));
    $this->hr = adminWithRoles('HR Manager');
    $this->case = app(OpenOffboardingCase::class)->handle($this->employee, ['type' => 'resignation', 'last_working_date' => '2026-10-20', 'reason' => 'Moving'], $this->hr);
    $this->accountant = adminWithRoles('Payroll Accountant');
});

it('adds a final settlement handover item linked to the case', function () {
    $item = $this->case->items()->where('itemable_type', 'offboarding_case')->sole();

    expect($item->itemable_id)->toBe($this->case->id)->and($item->category->value)->toBe('finance');

    $this->actingAs($this->hr, 'admin')->get(route('admin.offboarding.show', $this->case))->assertOk()->assertDontSee(route('admin.offboarding.final-settlement.create', $this->case));
    $this->actingAs(adminWithRoles('Super Admin'), 'admin')->get(route('admin.offboarding.show', $this->case))->assertOk()->assertSee(route('admin.offboarding.final-settlement.create', $this->case));
});

it('settles the last pay: prorated salary, encashment, full loan recovery, pay slot taken', function () {
    $this->actingAs($this->accountant, 'admin')->get(route('admin.offboarding.final-settlement.create', $this->case))->assertForbidden();   // needs offboarding view

    $settlement = adminWithRoles('Super Admin');
    $this->actingAs($settlement, 'admin')->post(route('admin.offboarding.final-settlement.store', $this->case), ['encash' => [$this->annual->id => '5']])->assertRedirect();

    $run = PayrollRun::query()->where('type', PayrollRunType::FinalSettlement)->sole();
    $run = app(ManagePayrollRun::class)->calculate($run, $settlement);

    expect($run->offboarding_case_id)->toBe($this->case->id)
        ->and($run->period->code)->toBe('2026-10')
        ->and(payLine($run, $this->employee, 'BASIC')->amount)->toBe('3870.97')            // 20 / 31 days
        ->and(payLine($run, $this->employee, 'LEAVE_ENCASH')->amount)->toBe('1000.00')    // 5 days × 200
        ->and(PayrollItemLine::query()->where('payroll_run_id', $run->id)->where('source', PayrollLineSource::Loan)->count())->toBe(3)
        ->and(payItem($run, $this->employee)->pay_slot)->toBe('regular');

    // No regular run of October can pay the leaver again.
    $preview = app(PayrollPopulation::class)->preview($run->period, PayrollRunType::Regular, ['all' => true]);
    expect(collect($preview['excluded'])->firstWhere('employee_id', $this->employee->id)['reason'])->toBe('already_paid');
});

it('completes the handover item when the settlement is finalised', function () {
    $run = app(CreateFinalSettlement::class)->handle($this->case, $this->accountant);
    finalisedRun(app(ManagePayrollRun::class)->calculate($run, $this->accountant));

    $item = $this->case->items()->where('itemable_type', 'offboarding_case')->sole();
    expect($item->status)->toBe(TaskStatus::Done)
        ->and($item->note)->toContain($run->reference);

    // The case can then be completed (other items done), terminating the employee.
    $manage = app(ManageOffboardingCase::class);
    $this->case->items()->where('status', 'pending')->get()->each(fn ($row) => $manage->updateItem($row, TaskStatus::Done, $this->hr));
    $manage->complete($this->case->refresh(), $this->hr);

    expect($this->employee->refresh()->termination_date?->toDateString())->toBe('2026-10-20');
});

it('refuses a second final settlement for the same case', function () {
    app(CreateFinalSettlement::class)->handle($this->case, $this->accountant);

    expect(fn () => app(CreateFinalSettlement::class)->handle(OffboardingCase::query()->findOrFail($this->case->id), $this->accountant))
        ->toThrow(ValidationException::class);
});
