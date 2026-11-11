<?php

use App\Actions\Offboarding\OpenOffboardingCase;
use App\Actions\Payroll\ManageLoan;
use App\Enums\LoanChangeType;
use App\Enums\LoanInstalmentStatus;
use App\Enums\LoanSource;
use App\Enums\LoanStatus;
use App\Enums\LoanTransactionType;
use App\Models\EmployeeLoan;
use App\Models\LoanChange;
use App\Models\OffboardingItem;
use App\Services\Payroll\LoanInstalments;
use App\Services\Payroll\LoanLedger;
use App\Services\Payroll\LoanSchedule;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/*
| Loans and advances (Phase 7a): limits, approval → schedule + ledger, the
| payroll hook (dueFor / markDeducted / reverseDeduction), early repayment,
| skip / reschedule through approval, final settlement on offboarding.
*/

beforeEach(function () {
    seedPayroll();
    $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00:00'));
    $this->employee = paidEmployee('6000.00');
    $this->finance = adminWithRoles('Finance Officer');
    $this->manage = app(ManageLoan::class);
});

function approvedLoan(array $data = [], array $terms = []): EmployeeLoan
{
    $loan = requestLoan(test()->employee, $data);
    app(ManageLoan::class)->decide($loan, test()->finance, true, null, $terms);

    return $loan->refresh();
}

describe('schedule generation', function () {
    it('splits an amount into monthly instalments that always add up', function () {
        $rows = app(LoanSchedule::class)->build('1000.00', 3, '2026-12-15');

        expect(array_column($rows, 'amount'))->toBe(['333.33', '333.33', '333.34'])
            ->and(array_column($rows, 'due_date'))->toBe(['2026-12-01', '2027-01-01', '2027-02-01'])
            ->and(array_column($rows, 'sequence'))->toBe([1, 2, 3]);
    });

    it('activates an approved loan with its schedule and a disbursement in the ledger', function () {
        $loan = approvedLoan(['amount' => '6000', 'instalments' => 6, 'start_month' => '2026-12']);

        expect($loan->status)->toBe(LoanStatus::Active)
            ->and($loan->approved_amount)->toBe('6000.00')
            ->and($loan->outstanding_amount)->toBe('6000.00')
            ->and($loan->instalments()->count())->toBe(6)
            ->and($loan->instalments()->first()->due_date->toDateString())->toBe('2026-12-01')
            ->and($loan->transactions()->sole()->type)->toBe(LoanTransactionType::Disbursement)
            ->and(app(LoanLedger::class)->balance($loan))->toBe('6000.00');
    });

    it('lets the approver lower the amount and change the plan within the limits', function () {
        $loan = approvedLoan(['amount' => '9000', 'instalments' => 12], ['approved_amount' => '7200', 'instalment_count' => 8, 'start_month' => '2027-01']);

        expect($loan->approved_amount)->toBe('7200.00')->and($loan->instalment_count)->toBe(8)
            ->and($loan->instalments()->first()->amount)->toBe('900.00')
            ->and($loan->start_date->toDateString())->toBe('2027-01-01');

        $other = requestLoan(paidEmployee('6000.00'), ['amount' => '6000', 'instalments' => 6]);
        expect(fn () => $this->manage->decide($other, $this->finance, true, null, ['approved_amount' => '7000']))->toThrow(ValidationException::class);
    });

    it('rejects with a reason and withdraws pending requests only', function () {
        $loan = requestLoan($this->employee);
        $this->manage->decide($loan, $this->finance, false, 'Budget');
        expect($loan->refresh()->status)->toBe(LoanStatus::Rejected)->and($loan->rejection_reason)->toBe('Budget')
            ->and($loan->instalments()->count())->toBe(0);

        $pending = requestLoan(paidEmployee());
        $this->manage->cancel($pending, adminWithRoles('Payroll Accountant'), 'Mistake');
        expect($pending->refresh()->status)->toBe(LoanStatus::Cancelled);

        expect(fn () => $this->manage->cancel($loan->refresh(), adminWithRoles('Payroll Accountant')))->toThrow(ValidationException::class);
    });
});

describe('limits', function () {
    it('enforces amount, instalments, instalment size, service and open loans from the settings', function () {
        // 3 × 6000 = 18000 max; 25% of 6000 = 1500 max instalment; 24 instalments.
        foreach ([
            ['amount' => '18000.01', 'instalments' => 24],
            ['amount' => '6000', 'instalments' => 25],
            ['amount' => '6000', 'instalments' => 3],
            ['type' => 'advance', 'amount' => '3000.01', 'instalments' => 1],
            ['type' => 'advance', 'amount' => '1000', 'instalments' => 2],
        ] as $data) {
            expect(fn () => requestLoan($this->employee, $data))->toThrow(ValidationException::class);
        }

        requestLoan($this->employee, ['type' => 'advance', 'amount' => '3000', 'instalments' => 1]);
        expect(fn () => requestLoan($this->employee))->toThrow(ValidationException::class);

        $newcomer = paidEmployee(attributes: ['hire_date' => '2026-09-01', 'original_hire_date' => '2026-09-01']);
        expect(fn () => requestLoan($newcomer))->toThrow(ValidationException::class);

        app(SettingsService::class)->set('loans.min_service_months', 0);
        expect(requestLoan($newcomer)->status)->toBe(LoanStatus::Pending);
    });

    it('refuses starting months in the past and employee requests when switched off', function () {
        expect(fn () => requestLoan($this->employee, ['start_month' => '2026-10']))->toThrow(ValidationException::class);

        app(SettingsService::class)->set('loans.employee_requests_enabled', false);
        expect(fn () => requestLoan($this->employee, source: LoanSource::Portal))->toThrow(ValidationException::class);
    });

    it('never lets an approver decide their own loan', function () {
        $this->finance->forceFill(['employee_id' => $this->employee->id])->save();
        $loan = requestLoan($this->employee);

        expect(fn () => $this->manage->decide($loan, $this->finance->refresh(), true))->toThrow(ValidationException::class);
    });
});

describe('payroll hook', function () {
    it('returns instalments due in the period plus overdue ones, and records deductions in the ledger', function () {
        $loan = approvedLoan(['amount' => '3000', 'instalments' => 3, 'start_month' => '2026-11']);
        $hook = app(LoanInstalments::class);

        $due = $hook->dueFor($this->employee, '2026-11-01', '2026-11-30');
        expect($due)->toHaveCount(1)->and($due[0]->amount)->toBe('1000.00')->and($due[0]->overdue)->toBeFalse();

        $december = $hook->dueFor($this->employee, '2026-12-01', '2026-12-31');
        expect($december)->toHaveCount(2)->and($december[0]->overdue)->toBeTrue();

        $first = $loan->instalments()->first();
        $hook->markDeducted($first, '1000.00');
        expect($first->refresh()->status)->toBe(LoanInstalmentStatus::Deducted)
            ->and($loan->refresh()->outstanding_amount)->toBe('2000.00')
            ->and($hook->dueFor($this->employee, '2026-12-01', '2026-12-31'))->toHaveCount(1);

        expect(fn () => $hook->markDeducted($first->refresh(), '1.00'))->toThrow(InvalidArgumentException::class);
    });

    it('keeps a partly deducted instalment scheduled for the rest and reverses a deduction', function () {
        $loan = approvedLoan(['amount' => '3000', 'instalments' => 3, 'start_month' => '2026-11']);
        $hook = app(LoanInstalments::class);
        $first = $loan->instalments()->first();

        $hook->markDeducted($first, '400.00');
        expect($first->refresh()->status)->toBe(LoanInstalmentStatus::Scheduled)
            ->and($hook->dueFor($this->employee, '2026-11-01', '2026-11-30')[0]->amount)->toBe('600.00');

        $hook->reverseDeduction($first);
        expect($first->refresh()->paid_amount)->toBe('0.00')->and($loan->refresh()->outstanding_amount)->toBe('3000.00')
            ->and($loan->transactions()->pluck('type')->map->value->all())->toBe(['disbursement', 'payroll_deduction', 'deduction_reversal']);
    });

    it('completes the loan when the balance reaches zero and ignores loans that are not active', function () {
        $loan = approvedLoan(['type' => 'advance', 'amount' => '1500', 'instalments' => 1, 'start_month' => '2026-11']);
        app(LoanInstalments::class)->markDeducted($loan->instalments()->first(), '1500.00');

        expect($loan->refresh()->status)->toBe(LoanStatus::Completed)
            ->and(app(LoanInstalments::class)->dueFor($this->employee, '2026-11-01', '2027-12-31'))->toBe([]);

        requestLoan($this->employee);
        expect(app(LoanInstalments::class)->dueFor($this->employee, '2026-11-01', '2027-12-31'))->toBe([]);
    });
});

describe('repayment and schedule changes', function () {
    it('applies an early repayment to the last instalments first and completes the loan when paid off', function () {
        $loan = approvedLoan(['amount' => '3000', 'instalments' => 3, 'start_month' => '2026-12']);
        $user = adminWithRoles('Payroll Accountant');

        $this->manage->repay($loan, '1500', '2026-11-12', 'Cash', $user);
        $rows = $loan->instalments()->get();
        expect($rows->pluck('status')->map->value->all())->toBe(['scheduled', 'scheduled', 'repaid'])
            ->and($rows[1]->paid_amount)->toBe('500.00')
            ->and($loan->refresh()->outstanding_amount)->toBe('1500.00');

        expect(fn () => $this->manage->repay($loan, '1500.01', '2026-11-12', null, $user))->toThrow(ValidationException::class);

        $this->manage->repay($loan, '1500', '2026-11-12', null, $user);
        expect($loan->refresh()->status)->toBe(LoanStatus::Completed)->and($loan->instalments()->scheduled()->count())->toBe(0);
    });

    it('skips an instalment to the end only after approval', function () {
        $loan = approvedLoan(['amount' => '3000', 'instalments' => 3, 'start_month' => '2026-12']);
        $first = $loan->instalments()->first();

        $change = $this->manage->requestChange($loan, LoanChangeType::Skip, ['instalment_id' => $first->id, 'reason' => 'Wedding month'], adminWithRoles('Payroll Accountant'));
        expect($first->refresh()->status)->toBe(LoanInstalmentStatus::Scheduled);

        $this->manage->decideChange($change, $this->finance, true);
        $rows = $loan->instalments()->get();
        expect($first->refresh()->status)->toBe(LoanInstalmentStatus::Skipped)
            ->and($rows->last()->due_date->toDateString())->toBe('2027-03-01')
            ->and($rows->last()->amount)->toBe('1000.00')
            ->and(app(LoanInstalments::class)->dueFor($this->employee, '2026-12-01', '2026-12-31'))->toBe([]);
    });

    it('reschedules the outstanding balance after approval and leaves it unchanged on rejection', function () {
        $loan = approvedLoan(['amount' => '3000', 'instalments' => 3, 'start_month' => '2026-11']);
        app(LoanInstalments::class)->markDeducted($loan->instalments()->first(), '1000.00');
        $user = adminWithRoles('Payroll Accountant');

        $rejected = $this->manage->requestChange($loan->refresh(), LoanChangeType::Reschedule, ['new_instalment_count' => 4, 'new_start_month' => '2027-01', 'reason' => 'x'], $user);
        $this->manage->decideChange($rejected, $this->finance, false, 'No');
        expect($loan->instalments()->scheduled()->count())->toBe(2);

        $change = $this->manage->requestChange($loan->refresh(), LoanChangeType::Reschedule, ['new_instalment_count' => 4, 'new_start_month' => '2027-01', 'reason' => 'Lower instalment'], $user);
        expect(fn () => $this->manage->requestChange($loan->refresh(), LoanChangeType::Reschedule, ['new_instalment_count' => 2, 'new_start_month' => '2027-01', 'reason' => 'again'], $user))
            ->toThrow(ValidationException::class);

        $this->manage->decideChange($change, $this->finance, true);
        $scheduled = $loan->instalments()->scheduled()->get();
        expect($scheduled)->toHaveCount(4)
            ->and($scheduled->first()->due_date->toDateString())->toBe('2027-01-01')
            ->and($scheduled->reduce(fn ($sum, $row) => bcadd($sum, (string) $row->amount, 2), '0.00'))->toBe('2000.00')
            ->and(LoanChange::query()->pluck('status')->map->value->all())->toBe(['rejected', 'approved']);
    });
});

describe('offboarding', function () {
    it('adds a finance handover item per open loan and recovers the balance from the final pay', function () {
        $loan = approvedLoan(['amount' => '3000', 'instalments' => 3, 'start_month' => '2026-12']);
        seedRecruitment();
        app(OpenOffboardingCase::class)->handle($this->employee, ['type' => 'resignation', 'last_working_date' => '2026-11-30', 'notice_date' => '2026-11-01', 'reason' => 'Relocation'], adminWithRoles('HR Manager'));

        expect(OffboardingItem::query()->where('itemable_type', 'employee_loan')->where('itemable_id', $loan->id)->exists())->toBeTrue();

        $this->manage->settleOnFinalPay($loan, '2026-11-30', adminWithRoles('Payroll Accountant'));
        $due = app(LoanInstalments::class)->dueFor($this->employee, '2026-11-01', '2026-11-30');
        expect($due)->toHaveCount(1)->and($due[0]->amount)->toBe('3000.00')->and($due[0]->finalSettlement)->toBeTrue()
            ->and($loan->instalments()->where('status', LoanInstalmentStatus::Cancelled)->count())->toBe(3);
    });
});
