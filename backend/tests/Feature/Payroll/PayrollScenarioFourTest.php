<?php

use App\Enums\PayComponentKind;
use App\Enums\PayrollRunStatus;
use App\Models\Branch;
use App\Models\Department;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use Carbon\CarbonImmutable;

/*
| Spec §12 scenario 4 (Payroll), end to end through the HTTP routes:
| 1. the Payroll Accountant selects a payroll period; 2. filters by a branch;
| 3. the system loads the eligible employees; 4. the engine calculates;
| 5. the accountant reviews allowances and deductions (breakdown);
| 6. the payroll is approved (HR Manager, then Finance Officer — preparation
|    and approval are separated by design) and finalised;
| 7. payslips are generated; 8. employees access their own payslips only.
*/

it('runs scenario 4 from period selection to the employee\'s own payslip', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00:00'));
    $c = payrollSetup();
    $branch = Branch::factory()->create(['timezone' => 'Africa/Cairo']);
    $department = Department::factory()->create(['branch_id' => $branch->id]);
    $inBranch = paidEmployee('6000.00', attributes: ['branch_id' => $branch->id, 'department_id' => $department->id]);
    $colleague = paidEmployee('8000.00', attributes: ['branch_id' => $branch->id, 'department_id' => $department->id]);
    $elsewhere = paidEmployee('9000.00');
    payrollAttendanceDay($inBranch, '2026-10-05', 'late', ['late_minutes' => 12]);
    payEntry(PayComponentKind::Earning, $c['TRANSPORT'], ['target_type' => 'employee', 'target_id' => $inBranch->id, 'amount' => '250', 'recurrence' => 'one_time', 'start_date' => '2026-10-15']);

    $accountant = adminWithRoles('Payroll Accountant');
    $account = User::factory()->employee()->create(['employee_id' => $inBranch->id]);
    $colleagueAccount = User::factory()->employee()->create(['employee_id' => $colleague->id]);

    // 1. Select (create) the October period.
    $this->actingAs($accountant, 'admin')->post(route('admin.payroll-periods.store'), ['type' => 'monthly', 'month' => '2026-10'])->assertRedirect();
    $period = PayrollPeriod::query()->where('code', '2026-10')->sole();

    // 2–3. Filter by the branch: the eligible employees are loaded (and only them).
    $scope = ['payroll_period_id' => $period->id, 'type' => 'regular', 'scope' => ['branch_ids' => [$branch->id]]];
    $this->actingAs($accountant, 'admin')->get(route('admin.payroll-runs.preview', $scope))
        ->assertOk()->assertSee($inBranch->employee_number)->assertSee($colleague->employee_number)->assertDontSee($elsewhere->employee_number);
    $this->actingAs($accountant, 'admin')->post(route('admin.payroll-runs.store'), $scope)->assertRedirect();
    $run = PayrollRun::query()->sole();
    expect($run->employee_count)->toBe(2)->and($run->branch_id)->toBe($branch->id);

    // 4. Calculate.
    $this->actingAs($accountant, 'admin')->post(route('admin.payroll-runs.calculate', $run))->assertRedirect();
    expect($run->refresh()->status)->toBe(PayrollRunStatus::Calculated);

    // 5. Review allowances and deductions of an employee (explained line by line).
    $item = PayrollItem::query()->withoutGlobalScopes()->where('payroll_run_id', $run->id)->where('employee_id', $inBranch->id)->sole();
    $this->actingAs($accountant, 'admin')->get(route('admin.payroll-runs.items.show', [$run, $item]))
        ->assertOk()->assertSee('6,000.00')->assertSee('30.00')->assertSee('250.00');

    // 6. Submit, approve at both levels, finalise.
    $this->actingAs($accountant, 'admin')->post(route('admin.payroll-runs.submit', $run))->assertRedirect();
    $this->actingAs(adminWithRoles('HR Manager'), 'admin')->post(route('admin.payroll-runs.decide', [$run, 'approve']))->assertRedirect();
    $finance = adminWithRoles('Finance Officer');
    $this->actingAs($finance, 'admin')->post(route('admin.payroll-runs.decide', [$run, 'approve']))->assertRedirect();
    $this->actingAs($finance, 'admin')->post(route('admin.payroll-runs.finalise', $run))->assertRedirect();
    expect($run->refresh()->status)->toBe(PayrollRunStatus::Finalised);

    // 7. Payslips generated for the branch only.
    expect(Payslip::query()->pluck('employee_id')->sort()->values()->all())->toBe(collect([$inBranch->id, $colleague->id])->sort()->values()->all());
    $own = Payslip::query()->where('employee_id', $inBranch->id)->sole();
    $theirs = Payslip::query()->where('employee_id', $colleague->id)->sole();

    // 8. Employees access their own payslips only (portal and API).
    $this->actingAs($account, 'employee')->get(route('portal.payroll'))->assertOk()->assertSee($own->number)->assertDontSee($theirs->number);
    $this->actingAs($account, 'employee')->get(route('portal.payroll.show', $theirs->id))->assertNotFound();
    $token = $colleagueAccount->createToken('phone')->plainTextToken;
    $this->getJson(route('api.v1.me.payslips.index'), ['Authorization' => 'Bearer '.$token])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.number', $theirs->number);
    $this->getJson(route('api.v1.me.payslips.show', $own->id), ['Authorization' => 'Bearer '.$token])->assertNotFound();
});
