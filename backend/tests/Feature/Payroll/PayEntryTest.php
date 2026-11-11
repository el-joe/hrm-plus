<?php

use App\Actions\Payroll\ManagePayEntry;
use App\Enums\DataScopeType;
use App\Enums\PayComponentKind;
use App\Enums\RequestStatus;
use App\Models\Department;
use App\Models\EmployeeGroup;
use App\Models\PayEntry;
use App\Models\PayEntryBatch;
use App\Models\SalaryComponent;
use App\Services\Payroll\PayEntryImporter;
use App\Services\Payroll\PayEntryResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/*
| Allowances and deductions (Phase 7a): scope precedence, recurrence inside a
| period, instalments, approval workflows, cancel / stop, bulk and import.
*/

beforeEach(function () {
    seedPayroll();
    Storage::fake(config('employees.disk'));
    $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00:00'));
    $this->meal = SalaryComponent::factory()->create(['code' => 'MEAL']);
    $this->bonus = SalaryComponent::factory()->variable()->needsApproval()->create(['code' => 'BONUS']);
    $this->penalty = SalaryComponent::factory()->deduction()->variable()->create(['code' => 'PENALTY']);
    $this->employee = paidEmployee();
    $this->resolve = fn (string $from = '2026-11-01', string $to = '2026-11-30') => collect(app(PayEntryResolver::class)->for($this->employee, $from, $to));
});

describe('scope precedence', function () {
    it('applies only the most specific level of a component: employee > group > department > branch > company', function () {
        $group = EmployeeGroup::factory()->create();
        $this->employee->groups()->attach($group->id, ['created_at' => now()]);
        $earn = PayComponentKind::Earning;

        payEntry($earn, $this->meal, ['target_type' => 'company', 'amount' => '100']);
        expect(($this->resolve)()->pluck('rule.amount')->all())->toBe(['100.00']);

        payEntry($earn, $this->meal, ['target_type' => 'branch', 'target_id' => $this->employee->branch_id, 'amount' => '200']);
        expect(($this->resolve)()->pluck('rule.amount')->all())->toBe(['200.00']);

        payEntry($earn, $this->meal, ['target_type' => 'department', 'target_id' => $this->employee->department_id, 'amount' => '300']);
        expect(($this->resolve)()->pluck('rule.amount')->all())->toBe(['300.00']);

        payEntry($earn, $this->meal, ['target_type' => 'employee_group', 'target_id' => $group->id, 'amount' => '400']);
        expect(($this->resolve)()->pluck('rule.amount')->all())->toBe(['400.00']);

        payEntry($earn, $this->meal, ['target_type' => 'employee', 'target_id' => $this->employee->id, 'amount' => '500']);
        expect(($this->resolve)()->pluck('rule.amount')->all())->toBe(['500.00']);
    });

    it('prefers the nearest department up the tree and keeps other components independent', function () {
        $parent = Department::factory()->create(['branch_id' => $this->employee->branch_id]);
        Department::query()->whereKey($this->employee->department_id)->update(['parent_id' => $parent->id]);

        payEntry(PayComponentKind::Earning, $this->meal, ['target_type' => 'department', 'target_id' => $parent->id, 'amount' => '150']);
        expect(($this->resolve)()->pluck('rule.amount')->all())->toBe(['150.00']);

        payEntry(PayComponentKind::Earning, $this->meal, ['target_type' => 'department', 'target_id' => $this->employee->department_id, 'amount' => '250']);
        payEntry(PayComponentKind::Deduction, $this->penalty, ['target_type' => 'branch', 'target_id' => $this->employee->branch_id, 'amount' => '20', 'recurrence' => 'one_time', 'start_date' => '2026-11-05']);

        expect(($this->resolve)()->map(fn ($input) => [$input->rule->code, $input->rule->amount])->all())
            ->toBe([['MEAL', '250.00'], ['PENALTY', '20.00']]);
    });

    it('does not apply entries of other employees, departments or branches', function () {
        $other = paidEmployee();
        payEntry(PayComponentKind::Earning, $this->meal, ['target_type' => 'employee', 'target_id' => $other->id, 'amount' => '500']);
        payEntry(PayComponentKind::Earning, $this->meal, ['target_type' => 'branch', 'target_id' => $other->branch_id, 'amount' => '500']);

        expect(($this->resolve)())->toBeEmpty();
    });
});

describe('recurrence', function () {
    it('applies recurring entries to every overlapping period with their active part', function () {
        payEntry(PayComponentKind::Earning, $this->meal, ['target_type' => 'employee', 'target_id' => $this->employee->id, 'amount' => '300',
            'start_date' => '2026-10-16', 'end_date' => '2026-12-15']);

        $october = ($this->resolve)('2026-10-01', '2026-10-31')->sole();
        expect($october->activeFrom)->toBe('2026-10-16')->and($october->activeTo)->toBe('2026-10-31')
            ->and(($this->resolve)('2026-11-01', '2026-11-30')->sole()->activeFrom)->toBe('2026-11-01')
            ->and(($this->resolve)('2026-12-01', '2026-12-31')->sole()->activeTo)->toBe('2026-12-15')
            ->and(($this->resolve)('2027-01-01', '2027-01-31'))->toBeEmpty();
    });

    it('applies a one-time entry only in the period containing its date and never prorates it', function () {
        payEntry(PayComponentKind::Deduction, $this->penalty, ['target_type' => 'employee', 'target_id' => $this->employee->id, 'amount' => '75',
            'recurrence' => 'one_time', 'start_date' => '2026-11-20', 'prorate' => '1']);

        expect(($this->resolve)('2026-10-01', '2026-10-31'))->toBeEmpty()
            ->and(($this->resolve)('2026-12-01', '2026-12-31'))->toBeEmpty()
            ->and(($this->resolve)()->sole()->rule->prorate)->toBeFalse();
    });

    it('splits an instalment deduction over months, the last instalment taking the remainder', function () {
        $entry = payEntry(PayComponentKind::Deduction, $this->penalty, ['target_type' => 'employee', 'target_id' => $this->employee->id,
            'recurrence' => 'instalment', 'total_amount' => '1000', 'instalment_count' => 3, 'start_date' => '2026-10-01']);

        expect($entry->end_date->toDateString())->toBe('2026-12-01');
        expect(array_map(fn (string $month) => ($this->resolve)($month.'-01', CarbonImmutable::parse($month.'-01')->endOfMonth()->toDateString())->sole()->rule->amount,
            ['2026-10', '2026-11', '2026-12']))->toBe(['333.33', '333.33', '333.34']);
        expect(($this->resolve)('2026-11-01', '2026-11-30')->sole()->instalmentNumber)->toBe(2)
            ->and(($this->resolve)('2027-01-01', '2027-01-31'))->toBeEmpty();
    });

    it('keeps percentage entries as percentages of their base', function () {
        payEntry(PayComponentKind::Earning, $this->meal, ['target_type' => 'employee', 'target_id' => $this->employee->id, 'calculation' => 'percentage', 'rate' => '5']);

        $input = ($this->resolve)()->sole();
        expect($input->rule->calculationType->value)->toBe('percentage')->and($input->rule->rate)->toBe('5.0000')->and($input->rule->baseCode)->toBeNull();
    });

    it('refuses instalment allowances, deductions without a reason and empty amounts', function () {
        expect(fn () => payEntry(PayComponentKind::Earning, $this->meal, ['target_type' => 'employee', 'target_id' => $this->employee->id, 'recurrence' => 'instalment', 'total_amount' => '100', 'instalment_count' => 2]))
            ->toThrow(ValidationException::class)
            ->and(fn () => payEntry(PayComponentKind::Deduction, $this->penalty, ['target_type' => 'employee', 'target_id' => $this->employee->id, 'amount' => '10', 'reason' => '']))
            ->toThrow(ValidationException::class)
            ->and(fn () => payEntry(PayComponentKind::Earning, $this->meal, ['target_type' => 'employee', 'target_id' => $this->employee->id, 'amount' => '0']))
            ->toThrow(ValidationException::class)
            ->and(fn () => payEntry(PayComponentKind::Earning, $this->penalty, ['target_type' => 'employee', 'target_id' => $this->employee->id, 'amount' => '10']))
            ->toThrow(ValidationException::class);
    });
});

describe('approval', function () {
    it('keeps entries of components that need approval out of payroll until Finance approves them', function () {
        $entry = payEntry(PayComponentKind::Earning, $this->bonus, ['target_type' => 'employee', 'target_id' => $this->employee->id, 'amount' => '1000', 'recurrence' => 'one_time', 'start_date' => '2026-11-01']);

        expect($entry->status)->toBe(RequestStatus::Pending)->and(($this->resolve)())->toBeEmpty();

        app(ManagePayEntry::class)->decide($entry, adminWithRoles('Finance Officer'), true, 'ok');
        expect($entry->refresh()->status)->toBe(RequestStatus::Approved)->and(($this->resolve)())->toHaveCount(1);
    });

    it('records a rejection with its reason and never lets the requester or the employee decide', function () {
        $accountant = adminWithRoles('Payroll Accountant', 'Finance Officer');
        $entry = payEntry(PayComponentKind::Earning, $this->bonus, ['target_type' => 'employee', 'target_id' => $this->employee->id, 'amount' => '1000'], $accountant);

        expect(fn () => app(ManagePayEntry::class)->decide($entry, $accountant, true))->toThrow(ValidationException::class);

        $self = adminWithRoles('Finance Officer');
        $self->forceFill(['employee_id' => $this->employee->id])->save();
        expect(fn () => app(ManagePayEntry::class)->decide($entry, $self, true))->toThrow(ValidationException::class);

        app(ManagePayEntry::class)->decide($entry, adminWithRoles('Finance Officer'), false, 'No budget');
        expect($entry->refresh()->status)->toBe(RequestStatus::Rejected)->and($entry->rejection_reason)->toBe('No budget');
    });

    it('approves entries of components without approval at once and stores supporting documents', function () {
        $entry = app(ManagePayEntry::class)->create(PayComponentKind::Deduction, [
            'salary_component_id' => $this->penalty->id, 'target_type' => 'employee', 'target_id' => $this->employee->id, 'calculation' => 'fixed',
            'amount' => '50', 'recurrence' => 'one_time', 'start_date' => '2026-11-03', 'reason' => 'Damage',
        ], adminWithRoles('Payroll Accountant'), [UploadedFile::fake()->create('memo.pdf', 20, 'application/pdf')]);

        expect($entry->status)->toBe(RequestStatus::Approved)->and($entry->attachments)->toHaveCount(1);
        Storage::disk(config('employees.disk'))->assertExists($entry->attachments->first()->file_path);
    });
});

describe('cancel and stop', function () {
    it('cancels pending or not-yet-started entries and stops running ones without losing history', function () {
        $actions = app(ManagePayEntry::class);
        $user = adminWithRoles('Payroll Accountant');
        $future = payEntry(PayComponentKind::Earning, $this->meal, ['target_type' => 'employee', 'target_id' => $this->employee->id, 'amount' => '10', 'start_date' => '2026-12-01'], $user);
        $running = payEntry(PayComponentKind::Earning, $this->meal, ['target_type' => 'employee', 'target_id' => $this->employee->id, 'amount' => '10', 'start_date' => '2026-01-01'], $user);

        $actions->cancel($future, $user, 'Not needed');
        expect($future->refresh()->status)->toBe(RequestStatus::Cancelled);

        expect(fn () => $actions->cancel($running, $user, 'x'))->toThrow(ValidationException::class);

        $actions->stop($running, '2026-11-15', $user, 'Moved to another site');
        expect($running->refresh()->end_date->toDateString())->toBe('2026-11-15')
            ->and($running->status)->toBe(RequestStatus::Approved)
            ->and(($this->resolve)()->sole()->activeTo)->toBe('2026-11-15')
            ->and(($this->resolve)('2026-12-01', '2026-12-31'))->toBeEmpty();
    });
});

describe('bulk and import', function () {
    it('creates one entry per selected employee in one batch, all or nothing', function () {
        $others = [paidEmployee(), paidEmployee()];
        $ids = [$this->employee->id, ...array_map(fn ($e) => $e->id, $others)];

        $batch = app(ManagePayEntry::class)->bulk(PayComponentKind::Earning, [
            'salary_component_id' => $this->meal->id, 'calculation' => 'fixed', 'amount' => '1000', 'recurrence' => 'one_time', 'start_date' => '2026-11-01',
        ], $ids, adminWithRoles('Payroll Accountant'));

        expect($batch->entries_count)->toBe(3)
            ->and(PayEntry::query()->where('pay_entry_batch_id', $batch->id)->pluck('employee_id')->sort()->values()->all())->toBe(collect($ids)->sort()->values()->all());

        expect(fn () => app(ManagePayEntry::class)->bulk(PayComponentKind::Earning, ['salary_component_id' => $this->meal->id, 'calculation' => 'fixed',
            'amount' => '0', 'recurrence' => 'one_time', 'start_date' => '2026-11-01'], $ids, adminWithRoles('Payroll Accountant')))->toThrow(ValidationException::class);
        expect(PayEntryBatch::query()->count())->toBe(1);
    });

    it('imports a spreadsheet only when every row is valid', function () {
        $path = tempnam(sys_get_temp_dir(), 'pay').'.csv';
        file_put_contents($path, implode("\n", [
            implode(',', PayEntryImporter::COLUMNS),
            "{$this->employee->employee_number},PENALTY,fixed,40,,one_time,2026-11-10,,,,Late",
            'UNKNOWN,PENALTY,fixed,40,,one_time,2026-11-10,,,,Late',
        ]));
        $importer = app(PayEntryImporter::class);
        $user = adminWithRoles('Payroll Accountant');
        $this->actingAs($user, 'admin');

        $report = $importer->import($path, 'csv', PayComponentKind::Deduction, $user);
        expect($report['created'])->toBe(0)->and($report['errors'])->toHaveCount(1)->and(PayEntry::query()->count())->toBe(0);

        file_put_contents($path, implode("\n", [implode(',', PayEntryImporter::COLUMNS), "{$this->employee->employee_number},PENALTY,fixed,40,,one_time,2026-11-10,,,,Late"]));
        $report = $importer->import($path, 'csv', PayComponentKind::Deduction, $user);
        expect($report['created'])->toBe(1)->and(PayEntry::query()->sole()->amount)->toBe('40.00');
    });
});

describe('data scope', function () {
    it('limits entry lists and records to the admin\'s branches', function () {
        $other = paidEmployee();
        $mine = payEntry(PayComponentKind::Earning, $this->meal, ['target_type' => 'employee', 'target_id' => $this->employee->id, 'amount' => '10']);
        $theirs = payEntry(PayComponentKind::Earning, $this->meal, ['target_type' => 'employee', 'target_id' => $other->id, 'amount' => '10']);
        $admin = adminWithPermissions('allowances.view');
        $admin->dataScopes()->create(['scope_type' => DataScopeType::Branch, 'scope_id' => $this->employee->branch_id]);

        $this->actingAs($admin->fresh(), 'admin')->get(route('admin.allowances.index'))
            ->assertOk()->assertSee($mine->reference)->assertDontSee($theirs->reference);
        $this->get(route('admin.allowances.show', $theirs))->assertNotFound();
        $this->get(route('admin.allowances.show', $mine))->assertOk();
    });
});
