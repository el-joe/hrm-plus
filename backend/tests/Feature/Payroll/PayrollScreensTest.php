<?php

use App\Enums\LoanStatus;
use App\Enums\PayComponentKind;
use App\Enums\RequestStatus;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\EmployeeLoan;
use App\Models\PayEntry;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureAssignment;
use App\Models\StatutoryRuleSet;
use App\Models\User;
use App\Services\Employees\SensitiveDataAccessLog;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

/*
| Payroll component screens (Phase 7a): permissions per default role,
| configuration forms, the employee Compensation tab (financial permission,
| access logged), bulk bonus, loan request → approval → schedule, the portal
| "My loans" and the mobile API me/loans.
*/

beforeEach(function () {
    seedPayroll();
    app(SettingsService::class)->set('localisation.default_locale', 'en');
    $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00:00'));
    $this->basic = SalaryComponent::factory()->basic()->create(['name_en' => 'Basic salary']);
    $this->meal = SalaryComponent::factory()->create(['code' => 'MEAL', 'name_en' => 'Meal allowance']);
    $this->bonus = SalaryComponent::factory()->variable()->needsApproval()->create(['code' => 'BONUS', 'name_en' => 'Performance bonus']);
    $this->employee = paidEmployee('6000.00');
    $this->account = User::factory()->employee()->create(['employee_id' => $this->employee->id]);
});

describe('permissions', function () {
    it('lets the Payroll Accountant manage configuration and keeps others out', function () {
        $this->actingAs(adminWithRoles('Payroll Accountant'), 'admin');
        foreach (['admin.salary-components.index', 'admin.salary-structures.index', 'admin.allowances.index', 'admin.deductions.index',
            'admin.statutory-rules.index', 'admin.loans.index', 'admin.salary-components.create', 'admin.allowances.bulk', 'admin.deductions.import'] as $route) {
            $this->get(route($route))->assertOk();
        }

        $this->actingAs(adminWithRoles('HR Manager'), 'admin');
        $this->get(route('admin.salary-components.index'))->assertOk();
        $this->get(route('admin.salary-components.create'))->assertForbidden();
        $this->get(route('admin.allowances.create'))->assertForbidden();

        $this->actingAs(adminWithRoles('Attendance Officer'), 'admin');
        foreach (['admin.salary-components.index', 'admin.allowances.index', 'admin.loans.index', 'admin.statutory-rules.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
    });

    it('shows the Payroll sidebar section only to payroll roles', function () {
        $this->actingAs(adminWithRoles('Payroll Accountant'), 'admin')->get(route('admin.dashboard'))
            ->assertSee(route('admin.loans.index'))->assertSee(route('admin.salary-structures.index'));
        $this->actingAs(adminWithRoles('Attendance Officer'), 'admin')->get(route('admin.dashboard'))
            ->assertDontSee(route('admin.loans.index'));
    });
});

describe('configuration screens', function () {
    it('creates a component and requires a rate for percentages', function () {
        $this->actingAs(adminWithRoles('Payroll Accountant'), 'admin');
        $payload = ['code' => 'housing', 'name_ar' => 'سكن', 'name_en' => 'Housing', 'kind' => 'earning', 'category' => 'allowance',
            'calculation_type' => 'percentage', 'rate' => '25', 'rounding' => 'half_up', 'display_order' => 20, 'is_taxable' => '1', 'is_active' => '1'];

        $this->post(route('admin.salary-components.store'), $payload)->assertRedirect(route('admin.salary-components.index'));
        expect(SalaryComponent::query()->where('code', 'HOUSING')->sole()->rate)->toBe('25.0000');

        $this->post(route('admin.salary-components.store'), [...$payload, 'code' => 'X2', 'rate' => ''])->assertSessionHasErrors('rate');
    });

    it('creates a structure with lines, adds a version and assigns it from the employee profile', function () {
        $hr = adminWithRoles('Payroll Accountant');
        $this->actingAs($hr, 'admin')->post(route('admin.salary-structures.store'), [
            'code' => 'std', 'name_ar' => 'قياسي', 'name_en' => 'Standard', 'effective_from' => '2026-01-01', 'is_active' => '1',
            'lines' => [['salary_component_id' => $this->basic->id], ['salary_component_id' => $this->meal->id, 'amount' => '250'], ['salary_component_id' => '']],
        ])->assertSessionHasNoErrors();
        $structure = SalaryStructure::query()->where('code', 'STD')->sole();

        $this->get(route('admin.salary-structures.show', $structure))->assertOk()->assertSee('Meal allowance');
        $this->post(route('admin.salary-structures.versions.store', $structure), ['effective_from' => '2026-12-01', 'lines' => [['salary_component_id' => $this->basic->id]]])
            ->assertRedirect(route('admin.salary-structures.show', $structure));
        expect($structure->versions()->count())->toBe(2);

        $this->post(route('admin.employees.salary-structure.store', $this->employee), ['salary_structure_id' => $structure->id, 'effective_from' => '2026-12-01'])
            ->assertRedirect(route('admin.employees.tab', [$this->employee, 'compensation']));
        expect(SalaryStructureAssignment::query()->where('scope_type', 'employee')->where('scope_id', $this->employee->id)->exists())->toBeTrue();

        $this->actingAs(adminWithRoles('Finance Officer'), 'admin')
            ->post(route('admin.employees.salary-structure.store', $this->employee), ['salary_structure_id' => $structure->id, 'effective_from' => '2027-01-01'])
            ->assertForbidden();
    });

    it('manages statutory tables with versions and shows the demo label', function () {
        $this->actingAs(adminWithRoles('Payroll Accountant'), 'admin')->post(route('admin.statutory-rules.store'), [
            'code' => 'si-eg', 'name_ar' => 'تأمينات', 'name_en' => 'Insurance', 'country_code' => 'eg', 'type' => 'social_insurance', 'is_demo' => '1', 'is_active' => '1',
        ])->assertSessionHasNoErrors();
        $set = StatutoryRuleSet::query()->where('code', 'SI-EG')->sole();
        expect($set->country_code)->toBe('EG');

        $this->post(route('admin.statutory-rules.versions.store', $set), ['effective_from' => '2026-01-01', 'employee_rate' => '10', 'employer_rate' => '15'])
            ->assertRedirect(route('admin.statutory-rules.show', $set));
        $this->get(route('admin.statutory-rules.show', $set))->assertOk()->assertSee('DEMO — not legal advice');

        $this->put(route('admin.statutory-rules.update', $set), ['code' => 'SI-EG', 'name_ar' => 'تأمينات', 'name_en' => 'Insurance', 'country_code' => 'SA', 'type' => 'income_tax'])
            ->assertSessionHasErrors(['country_code', 'type']);
    });
});

describe('employee compensation tab', function () {
    it('needs the financial permission and logs every view', function () {
        $finance = adminWithRoles('Finance Officer');
        $this->actingAs($finance, 'admin')->get(route('admin.employees.tab', [$this->employee, 'compensation']))->assertOk();
        expect(AuditLog::query()->where('event', SensitiveDataAccessLog::FINANCIAL)->where('user_id', $finance->id)->sole()->meta['fields'])
            ->toContain('salary_structure', 'loans');

        $this->actingAs(adminWithRoles('HR Specialist'), 'admin')->get(route('admin.employees.tab', [$this->employee, 'compensation']))->assertForbidden();
    });

    it('lists what applies this month and the employee\'s loans', function () {
        payEntry(PayComponentKind::Earning, $this->meal, ['target_type' => 'employee', 'target_id' => $this->employee->id, 'amount' => '300']);
        requestLoan($this->employee);

        $this->actingAs(adminWithRoles('Payroll Accountant'), 'admin')->get(route('admin.employees.tab', [$this->employee, 'compensation']))
            ->assertOk()->assertSee('Meal allowance')->assertSee(EmployeeLoan::query()->sole()->reference);
    });
});

describe('allowance and deduction screens', function () {
    it('gives a whole department a one-time bonus that Finance approves', function () {
        $department = Department::query()->findOrFail($this->employee->department_id);
        $this->actingAs(adminWithRoles('Payroll Accountant'), 'admin')->post(route('admin.allowances.store'), [
            'target_type' => 'department', 'department_id' => $department->id, 'salary_component_id' => $this->bonus->id, 'calculation' => 'fixed',
            'amount' => '1500', 'recurrence' => 'one_time', 'start_date' => '2026-11-01', 'reason' => 'Target reached',
        ])->assertSessionHasNoErrors();
        $entry = PayEntry::query()->sole();
        expect($entry->status)->toBe(RequestStatus::Pending)->and($entry->department_id)->toBe($department->id);

        $this->actingAs(adminWithRoles('Finance Officer'), 'admin')->post(route('admin.allowances.decide', [$entry, 'approve']))->assertRedirect();
        expect($entry->refresh()->status)->toBe(RequestStatus::Approved);

        $this->get(route('admin.deductions.show', $entry))->assertNotFound();
    });

    it('creates bulk entries for the ticked employees', function () {
        $other = paidEmployee();
        $this->actingAs(adminWithRoles('Payroll Accountant'), 'admin')->post(route('admin.allowances.bulk.store'), [
            'employee_ids' => [$this->employee->id, $other->id], 'salary_component_id' => $this->meal->id, 'calculation' => 'fixed', 'amount' => '200',
            'recurrence' => 'one_time', 'start_date' => '2026-11-01',
        ])->assertSessionHasNoErrors();

        expect(PayEntry::query()->count())->toBe(2)->and(PayEntry::query()->pluck('pay_entry_batch_id')->unique())->toHaveCount(1);
    });

    it('refuses company-wide entries to data-scoped admins', function () {
        $admin = adminWithPermissions('allowances.view', 'allowances.create');
        $admin->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => $this->employee->branch_id]);

        $this->actingAs($admin->fresh(), 'admin')->post(route('admin.allowances.store'), [
            'target_type' => 'company', 'salary_component_id' => $this->meal->id, 'calculation' => 'fixed', 'amount' => '10', 'recurrence' => 'recurring', 'start_date' => '2026-11-01',
        ])->assertSessionHasErrors('target_type');
    });
});

describe('loans screens', function () {
    it('takes a request on behalf, lets Finance approve with terms and shows the schedule', function () {
        $this->actingAs(adminWithRoles('Payroll Accountant'), 'admin')->post(route('admin.loans.store'), [
            'employee_id' => $this->employee->id, 'type' => 'loan', 'amount' => '6000', 'instalments' => 6, 'start_month' => '2026-12', 'reason' => 'Car',
        ])->assertSessionHasNoErrors();
        $loan = EmployeeLoan::query()->sole();

        $this->actingAs(adminWithRoles('Finance Officer'), 'admin')
            ->post(route('admin.loans.decide', [$loan, 'approve']), ['approved_amount' => '4800', 'instalment_count' => 4])->assertRedirect();
        expect($loan->refresh()->status)->toBe(LoanStatus::Active)->and($loan->instalments()->count())->toBe(4);

        $this->get(route('admin.loans.show', $loan))->assertOk()->assertSee('1,200.00');
        $this->actingAs(adminWithRoles('Attendance Officer'), 'admin')->get(route('admin.loans.show', $loan))->assertForbidden();
    });
});

describe('portal and API', function () {
    it('lets employees request and follow their own loans in the portal', function () {
        $this->actingAs($this->account, 'employee')->get(route('portal.loans'))->assertOk();
        $this->post(route('portal.loans.store'), ['type' => 'advance', 'amount' => '2000', 'instalments' => 1, 'start_month' => '2026-12'])->assertSessionHasNoErrors();
        $loan = EmployeeLoan::query()->sole();
        expect($loan->source->value)->toBe('portal');

        $this->get(route('portal.loans.show', $loan->id))->assertOk();

        $stranger = requestLoan(paidEmployee());
        $this->get(route('portal.loans.show', $stranger->id))->assertNotFound();
        $this->post(route('portal.loans.cancel', $stranger->id))->assertNotFound();

        $this->post(route('portal.loans.cancel', $loan->id))->assertRedirect(route('portal.loans'));
        expect($loan->refresh()->status)->toBe(LoanStatus::Cancelled);
    });

    it('serves me/loans to the mobile app with limits, validation and ownership', function () {
        Sanctum::actingAs($this->account);

        $this->getJson(route('api.v1.me.loans.limits'))->assertOk()->assertJsonPath('data.loan.max_amount', '18000.00');
        $this->postJson(route('api.v1.me.loans.store'), ['type' => 'loan', 'amount' => '99999', 'instalments' => 6, 'start_month' => '2026-12'])->assertUnprocessable();
        $response = $this->postJson(route('api.v1.me.loans.store'), ['type' => 'loan', 'amount' => '6000', 'instalments' => 6, 'start_month' => '2026-12'])
            ->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.requested_amount', '6000.00');
        $id = $response->json('data.id');

        $this->getJson(route('api.v1.me.loans.index'))->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(route('api.v1.me.loans.show', $id))->assertOk()->assertJsonPath('data.reference', EmployeeLoan::query()->find($id)->reference);
        $this->getJson(route('api.v1.me.loans.show', requestLoan(paidEmployee())->id))->assertNotFound();

        Sanctum::actingAs(adminWithRoles('Payroll Accountant'));
        $this->getJson(route('api.v1.me.loans.index'))->assertForbidden();
    });
});
