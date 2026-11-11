<?php

use App\Actions\Leave\AdjustLeaveBalance;
use App\Actions\Leave\SaveLeavePolicy;
use App\Enums\Gender;
use App\Enums\LeaveAccrualMethod;
use App\Enums\LeavePolicyScope;
use App\Enums\LeaveTransactionType;
use App\Models\AuditLog;
use App\Models\EmployeeGroup;
use App\Models\EmploymentType;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Services\Leave\LeaveAccrualService;
use App\Services\Leave\LeaveBalanceCalculator;
use App\Services\Leave\LeavePolicyResolver;
use App\Services\Leave\LeaveRollover;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/*
| Leave balances (Phase 6): accrual methods, pro rata, service tiers, minimum
| service, ceiling, carry-over with expiry through the yearly rollover,
| negative balances, manual adjustments / encashment, policy resolution and
| effective dating. Leave years start in January (default setting).
*/

beforeEach(function () {
    seedLeave();
    $this->travelTo(CarbonImmutable::parse('2026-11-01 07:00:00'));
    leaveWorkWeek();
    $this->hr = adminWithRoles('HR Manager');
});

function ledgerSum(int $employeeId, int $typeId, int $year, ?LeaveTransactionType $type = null): string
{
    $query = LeaveBalanceTransaction::query()->where('employee_id', $employeeId)->where('leave_type_id', $typeId)->where('leave_year', $year);

    return bcadd((string) ($type ? $query->where('type', $type)->sum('amount') : $query->sum('amount')), '0', 2);
}

it('credits an up-front entitlement once per leave year however often accrual runs', function () {
    $type = leaveTypeWithPolicy(policy: ['entitlement_days' => '21']);
    $employee = attendanceEmployee();

    accrueLeave('2026-01-01');
    accrueLeave('2026-06-15');
    $this->artisan('leave:accrue', ['--date' => '2026-10-01'])->assertSuccessful();

    expect(availableLeave($employee, $type, 2026))->toBe('21.00')
        ->and(LeaveBalanceTransaction::query()->where('type', 'accrual')->count())->toBe(1);
});

it('prorates the hire year by the months left after the hire date', function () {
    $type = leaveTypeWithPolicy(policy: ['entitlement_days' => '21', 'accrual_method' => LeaveAccrualMethod::ProRataHire]);
    $employee = attendanceEmployee(['hire_date' => '2026-04-10', 'original_hire_date' => '2026-04-10']);

    accrueLeave('2026-05-01');
    expect(availableLeave($employee, $type, 2026))->toBe('15.75'); // 21 × 9 / 12

    accrueLeave('2027-01-01');
    expect(availableLeave($employee, $type, 2027))->toBe('21.00');
});

it('accrues a twelfth of the entitlement for every month started', function () {
    $type = leaveTypeWithPolicy(policy: ['entitlement_days' => '12', 'accrual_method' => LeaveAccrualMethod::Monthly]);
    $employee = attendanceEmployee();

    accrueLeave('2026-03-15');
    accrueLeave('2026-03-20');
    expect(availableLeave($employee, $type, 2026))->toBe('3.00');

    accrueLeave('2026-04-01');
    expect(availableLeave($employee, $type, 2026))->toBe('4.00')
        ->and(LeaveBalanceTransaction::query()->where('type', 'accrual')->count())->toBe(2);
});

it('uses the service tier for completed years and tops up when a new tier is reached', function () {
    $type = leaveTypeWithPolicy(policy: [
        'entitlement_days' => '15', 'accrual_method' => LeaveAccrualMethod::ServiceTiers,
        'service_tiers' => [['min_years' => 0, 'days' => '15'], ['min_years' => 1, 'days' => '21'], ['min_years' => 10, 'days' => '30']],
    ]);
    $veteran = attendanceEmployee(['hire_date' => '2016-06-01', 'original_hire_date' => '2016-06-01']);
    $newcomer = attendanceEmployee(['hire_date' => '2026-07-01', 'original_hire_date' => '2026-07-01']);

    accrueLeave('2026-01-01');
    expect(availableLeave($veteran, $type, 2026))->toBe('21.00');

    accrueLeave('2026-06-01');
    expect(availableLeave($veteran, $type, 2026))->toBe('30.00')
        ->and(availableLeave($newcomer, $type, 2026))->toBe('0.00');

    accrueLeave('2026-07-01');
    expect(availableLeave($newcomer, $type, 2026))->toBe('7.50'); // first tier, 6 of 12 months
});

it('credits nothing before the minimum service or to employees the type is not meant for', function () {
    $type = leaveTypeWithPolicy(['min_service_months' => 6]);
    $maternity = leaveTypeWithPolicy(['gender' => Gender::Female], ['entitlement_days' => '90']);
    $junior = attendanceEmployee(['hire_date' => '2026-08-01', 'original_hire_date' => '2026-08-01', 'gender' => Gender::Male]);

    accrueLeave('2026-10-01');
    expect(availableLeave($junior, $type, 2026))->toBe('0.00')
        ->and(availableLeave($junior, $maternity, 2026))->toBe('0.00');

    accrueLeave('2027-02-01');
    expect(availableLeave($junior, $type, 2027))->toBe('21.00');
});

it('stops accruing at the maximum balance', function () {
    $type = leaveTypeWithPolicy(policy: ['entitlement_days' => '21', 'max_balance_days' => '25']);
    $employee = attendanceEmployee();
    app(AdjustLeaveBalance::class)->handle($employee, [
        'leave_type_id' => $type->id, 'leave_year' => 2026, 'type' => 'adjustment', 'amount' => '10', 'effective_date' => '2026-01-01', 'reason' => 'Opening balance',
    ], $this->hr);

    accrueLeave('2026-02-01');

    expect(availableLeave($employee, $type, 2026))->toBe('25.00')
        ->and(ledgerSum($employee->id, $type->id, 2026, LeaveTransactionType::Accrual))->toBe('15.00');
});

it('rolls a year over within the carry-over maximum, expires the rest, then expires unused carried days', function () {
    $type = leaveTypeWithPolicy(policy: ['entitlement_days' => '21', 'carry_over_max_days' => '5', 'carry_over_expiry_months' => 3]);
    $employee = attendanceEmployee();

    accrueLeave('2025-01-01');
    fileLeave($employee, $type, '2025-03-02', '2025-03-05', by: $this->hr, recordAsApproved: true); // Sun–Wed: 4 days
    expect(availableLeave($employee, $type, 2025))->toBe('17.00');

    $this->artisan('leave:rollover', ['--year' => 2026])->assertSuccessful();

    $carried = LeaveBalanceTransaction::query()->where('leave_year', 2026)->where('type', 'carry_over')->sole();
    expect(ledgerSum($employee->id, $type->id, 2025))->toBe('0.00')
        ->and(ledgerSum($employee->id, $type->id, 2025, LeaveTransactionType::Expiry))->toBe('-12.00')
        ->and($carried->amount)->toBe('5.00')
        ->and($carried->expires_on->format('Y-m-d'))->toBe('2026-03-31');

    accrueLeave('2026-01-01');
    fileLeave($employee, $type, '2026-02-01', '2026-02-02', by: $this->hr, recordAsApproved: true); // Sun–Mon: 2 days, taken from the carried days
    expect(availableLeave($employee, $type, 2026))->toBe('24.00');

    app(LeaveAccrualService::class)->expireCarryOver(CarbonImmutable::parse('2026-04-01'));
    app(LeaveAccrualService::class)->expireCarryOver(CarbonImmutable::parse('2026-04-02'));
    expect(availableLeave($employee, $type, 2026))->toBe('21.00')
        ->and(ledgerSum($employee->id, $type->id, 2026, LeaveTransactionType::Expiry))->toBe('-3.00');

    // Rerunning the rollover changes nothing.
    $rows = LeaveBalanceTransaction::query()->count();
    $this->artisan('leave:rollover', ['--year' => 2026])->assertSuccessful();
    expect(LeaveBalanceTransaction::query()->count())->toBe($rows);
});

it('carries nothing when the policy carries nothing and moves a negative balance as a debt', function () {
    $type = leaveTypeWithPolicy(policy: ['entitlement_days' => '2', 'allow_negative_balance' => true, 'negative_limit_days' => '5']);
    $kept = leaveTypeWithPolicy(policy: ['entitlement_days' => '3']);
    $employee = attendanceEmployee();
    accrueLeave('2025-01-01');
    fileLeave($employee, $type, '2025-03-02', '2025-03-05', by: $this->hr, recordAsApproved: true); // 4 days of 2

    app(LeaveRollover::class)->rollover(2026);

    expect(ledgerSum($employee->id, $type->id, 2026))->toBe('-2.00')
        ->and(ledgerSum($employee->id, $kept->id, 2026))->toBe('0.00')
        ->and(ledgerSum($employee->id, $kept->id, 2025, LeaveTransactionType::Expiry))->toBe('-3.00');
});

it('refuses a request beyond the balance unless the policy allows a negative balance down to its limit', function () {
    $strict = leaveTypeWithPolicy(policy: ['entitlement_days' => '2']);
    $lenient = leaveTypeWithPolicy(policy: ['entitlement_days' => '2', 'allow_negative_balance' => true, 'negative_limit_days' => '3']);
    $employee = attendanceEmployee();
    accrueLeave('2026-01-01');

    expect(fn () => fileLeave($employee, $strict, '2026-11-08', '2026-11-10'))->toThrow(ValidationException::class);

    fileLeave($employee, $lenient, '2026-11-08', '2026-11-11'); // 4 of 2: −2
    expect(availableLeave($employee, $lenient))->toBe('-2.00')
        ->and(fn () => fileLeave($employee, $lenient, '2026-11-15', '2026-11-16'))->toThrow(ValidationException::class); // would reach −4
});

it('counts a future leave year against its projected entitlement', function () {
    $type = leaveTypeWithPolicy(policy: ['entitlement_days' => '5']);
    $employee = attendanceEmployee();
    accrueLeave('2026-01-01');

    $leave = fileLeave($employee, $type, '2026-12-27', '2027-01-07'); // 5 working days in each year

    expect($leave->days)->toBe('10.00')
        ->and(fn () => fileLeave($employee, $type, '2027-01-10', '2027-01-10'))->toThrow(ValidationException::class);
});

it('records adjustments with a reason and an audit entry, and encashes only what the policy allows', function () {
    $type = leaveTypeWithPolicy(policy: ['entitlement_days' => '10', 'encashable' => true, 'encashment_max_days' => '5']);
    $other = leaveTypeWithPolicy(policy: ['entitlement_days' => '10']);
    $employee = attendanceEmployee();
    accrueLeave('2026-01-01');
    $admin = adminWithPermissions('leave_balances.view', 'leave_balances.update', 'employees.view');

    $this->actingAs($admin, 'admin')->post(route('admin.leave-balances.transactions.store', $employee), [
        'leave_type_id' => $type->id, 'leave_year' => 2026, 'type' => 'adjustment', 'amount' => '-1.5', 'effective_date' => '2026-11-01', 'reason' => 'Unrecorded day in March',
    ])->assertRedirect(route('admin.leave-balances.show', [$employee, 'year' => 2026]));

    $row = LeaveBalanceTransaction::query()->where('type', 'adjustment')->sole();
    expect($row->amount)->toBe('-1.50')->and($row->created_by)->toBe($admin->id)->and($row->reason)->toBe('Unrecorded day in March')
        ->and(AuditLog::query()->where('event', 'leave.balance_adjustment')->exists())->toBeTrue();

    $encash = fn (LeaveType $leaveType, string $days) => app(AdjustLeaveBalance::class)->handle($employee, [
        'leave_type_id' => $leaveType->id, 'leave_year' => 2026, 'type' => 'encashment', 'amount' => $days, 'effective_date' => '2026-11-01', 'reason' => 'Paid out',
    ], $admin);

    expect($encash($type, '3')->amount)->toBe('-3.00')
        ->and(fn () => $encash($type, '3'))->toThrow(ValidationException::class) // 6 > 5 per year
        ->and(fn () => $encash($other, '1'))->toThrow(ValidationException::class); // not encashable
    expect(availableLeave($employee, $type))->toBe('5.50');

    // Ledger rows are never edited.
    $row->amount = '9';
    expect($row->save())->toBeFalse();
});

it('refuses balance adjustments without the permission', function () {
    $type = leaveTypeWithPolicy();
    $employee = attendanceEmployee();

    $this->actingAs(adminWithPermissions('leave_balances.view', 'employees.view'), 'admin')
        ->post(route('admin.leave-balances.transactions.store', $employee), [
            'leave_type_id' => $type->id, 'leave_year' => 2026, 'type' => 'adjustment', 'amount' => '5', 'effective_date' => '2026-11-01', 'reason' => 'x',
        ])->assertForbidden();

    expect(LeaveBalanceTransaction::query()->count())->toBe(0);
});

it('resolves the most specific policy: group over employment type over company', function () {
    $type = leaveTypeWithPolicy(policy: ['code' => 'COMPANY', 'entitlement_days' => '21']);
    $partTime = EmploymentType::factory()->create();
    $field = EmployeeGroup::factory()->create();
    LeavePolicy::factory()->create(['code' => 'PT', 'leave_type_id' => $type->id, 'scope_type' => LeavePolicyScope::EmploymentType, 'scope_id' => $partTime->id, 'entitlement_days' => '10']);
    LeavePolicy::factory()->create(['code' => 'FIELD', 'leave_type_id' => $type->id, 'scope_type' => LeavePolicyScope::EmployeeGroup, 'scope_id' => $field->id, 'entitlement_days' => '25']);

    $regular = attendanceEmployee();
    $partTimer = attendanceEmployee(['employment_type_id' => $partTime->id]);
    $fieldWorker = attendanceEmployee(['employment_type_id' => $partTime->id]);
    $fieldWorker->groups()->attach($field->id, ['created_at' => now()]);
    $resolver = app(LeavePolicyResolver::class);

    expect($resolver->for($regular, $type, '2026-11-01')->code)->toBe('COMPANY')
        ->and($resolver->for($partTimer, $type, '2026-11-01')->code)->toBe('PT')
        ->and($resolver->for($fieldWorker->fresh(), $type, '2026-11-01')->code)->toBe('FIELD');
});

it('keeps policies effective-dated: a new version closes the previous one and applies from its date', function () {
    $type = leaveTypeWithPolicy(policy: ['code' => 'OLD', 'entitlement_days' => '12']);
    $employee = attendanceEmployee();

    app(SaveLeavePolicy::class)->handle([
        'code' => 'NEW', 'name_ar' => 'جديدة', 'name_en' => 'New', 'leave_type_id' => $type->id, 'scope_type' => LeavePolicyScope::Company, 'scope_id' => null,
        'effective_from' => '2026-01-01', 'entitlement_days' => '15', 'accrual_method' => LeaveAccrualMethod::AnnualUpfront, 'carry_over_max_days' => '0',
        'allow_negative_balance' => false, 'encashable' => false, 'is_active' => true,
    ]);

    expect(LeavePolicy::query()->where('code', 'OLD')->value('effective_to')->format('Y-m-d'))->toBe('2025-12-31');

    accrueLeave('2025-06-01');
    accrueLeave('2026-06-01');

    expect(availableLeave($employee, $type, 2025))->toBe('12.00')
        ->and(availableLeave($employee, $type, 2026))->toBe('15.00')
        ->and(fn () => app(SaveLeavePolicy::class)->handle([
            'code' => 'CLASH', 'name_ar' => 'x', 'name_en' => 'x', 'leave_type_id' => $type->id, 'scope_type' => LeavePolicyScope::Company, 'scope_id' => null,
            'effective_from' => '2025-06-01', 'effective_to' => '2025-08-01', 'entitlement_days' => '1', 'accrual_method' => LeaveAccrualMethod::AnnualUpfront,
        ]))->toThrow(ValidationException::class);
});

it('breaks a balance down into carried, accrued, adjusted, taken and pending days', function () {
    $type = leaveTypeWithPolicy(policy: ['entitlement_days' => '21']);
    $employee = attendanceEmployee();
    accrueLeave('2026-01-01');
    fileLeave($employee, $type, '2026-03-01', '2026-03-02', by: $this->hr, recordAsApproved: true);
    fileLeave($employee, $type, '2026-11-08', '2026-11-10');

    $balance = app(LeaveBalanceCalculator::class)->for($employee, $type, 2026);

    expect($balance->entitlement)->toBe('21.00')
        ->and($balance->accrued)->toBe('21.00')
        ->and($balance->used)->toBe('2.00')
        ->and($balance->pending)->toBe('3.00')
        ->and($balance->balance)->toBe('19.00')
        ->and($balance->available)->toBe('16.00');
});
