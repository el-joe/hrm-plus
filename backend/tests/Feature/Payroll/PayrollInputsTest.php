<?php

use App\Actions\Payroll\AssignSalaryStructure;
use App\Actions\Payroll\ManageLoan;
use App\Actions\Payroll\SaveStatutoryRuleVersion;
use App\Enums\PayBasis;
use App\Enums\PayComponentKind;
use App\Enums\SalaryStructureScope;
use App\Enums\StatutoryType;
use App\Models\SalaryComponent;
use App\Models\StatutoryRuleSet;
use App\Services\Payroll\ComponentCalculator;
use App\Services\Payroll\Data\EmployeePayrollInputs;
use App\Services\Payroll\Data\StatutoryRule;
use App\Services\Payroll\PayrollInputs;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;

/*
| The read API for the payroll engine (docs/PAYROLL_ENGINE.md): one call
| returns the salary segments, the structure version, the entries after
| precedence, the loan instalments due and the statutory versions — exact
| DECIMAL strings, and a snapshot array to store with a calculation.
*/

beforeEach(function () {
    seedPayroll();
    $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00:00'));
    app(SettingsService::class)->set('payroll.country_code', 'EG');
    $this->basic = SalaryComponent::factory()->basic()->create();
    $this->housing = SalaryComponent::factory()->percentage('25')->create(['code' => 'HOUSING']);
    $this->transport = SalaryComponent::factory()->create(['code' => 'TRANSPORT', 'amount' => '500']);
    $this->late = SalaryComponent::factory()->deduction()->create(['code' => 'LATE', 'calculation_type' => 'late_minutes', 'amount' => null, 'rate' => '1']);
    $this->tax = SalaryComponent::factory()->statutory(StatutoryType::IncomeTax)->create(['code' => 'TAX']);
    $this->meal = SalaryComponent::factory()->create(['code' => 'MEAL']);
    $this->employee = paidEmployee('8000.00');

    $structure = salaryStructure('STD', [$this->basic, $this->housing, [$this->transport, ['amount' => '600']], $this->late, $this->tax]);
    app(AssignSalaryStructure::class)->handle($structure, SalaryStructureScope::Company, null, '2024-01-01', null, null);

    $set = StatutoryRuleSet::create(['code' => 'TAX', 'name_ar' => 'ضريبة', 'name_en' => 'Tax', 'country_code' => 'EG', 'type' => 'income_tax', 'is_demo' => true, 'is_active' => true]);
    app(SaveStatutoryRuleVersion::class)->handle($set, ['effective_from' => '2025-01-01', 'annual_exemption' => '0', 'brackets' => [['lower_bound' => '0', 'upper_bound' => null, 'rate' => '10']]], null);

    payEntry(PayComponentKind::Earning, $this->meal, ['target_type' => 'employee', 'target_id' => $this->employee->id, 'amount' => '300']);
    $loan = requestLoan($this->employee, ['amount' => '6000', 'instalments' => 6, 'start_month' => '2026-11']);
    app(ManageLoan::class)->decide($loan, adminWithRoles('Finance Officer'), true);
});

it('returns every input of the period as value objects with exact decimal strings', function () {
    $inputs = app(PayrollInputs::class)->for($this->employee, '2026-11-01', '2026-11-30');

    expect($inputs)->toBeInstanceOf(EmployeePayrollInputs::class)
        ->and($inputs->basicSalary)->toBe('8000.00')
        ->and($inputs->payBasis)->toBe(PayBasis::Monthly)
        ->and($inputs->countryCode)->toBe('EG')
        ->and($inputs->structure->code)->toBe('STD')
        ->and($inputs->structure->version)->toBe(1)
        ->and(collect($inputs->structure->lines)->pluck('code')->all())->toEqualCanonicalizing(['BASIC', 'HOUSING', 'TRANSPORT', 'LATE', 'TAX'])
        ->and(collect($inputs->structure->lines)->firstWhere('code', 'TRANSPORT')->amount)->toBe('600.0000')
        ->and(collect($inputs->allowances())->sole()->rule->amount)->toBe('300.00')
        ->and($inputs->deductions())->toBe([])
        ->and($inputs->loanInstalments)->toHaveCount(1)
        ->and($inputs->loanInstalmentTotal())->toBe('1000.00')
        ->and($inputs->statutoryRule(StatutoryType::IncomeTax))->toBeInstanceOf(StatutoryRule::class)
        ->and($inputs->statutoryRule(StatutoryType::IncomeTax)->isDemo)->toBeTrue()
        ->and($inputs->statutoryRule(StatutoryType::SocialInsurance))->toBeNull()
        ->and($inputs->catalogue)->toHaveKeys(['BASIC', 'LATE', 'TAX']);
});

it('feeds the component calculator end to end', function () {
    $inputs = app(PayrollInputs::class)->for($this->employee, '2026-11-01', '2026-11-30');
    $context = payContext($inputs->basicSalary, quantities: ['late_minutes' => '60']);
    $calculator = app(ComponentCalculator::class);

    $structure = $calculator->evaluate($inputs->structure->lines, $context);
    $entries = $calculator->evaluateEntries($inputs->entries, $context, $inputs->periodStart, $inputs->periodEnd, $structure);

    // 8000 / 30 / 8 / 60 = 0.555555 per minute × 60 = 33.33.
    expect($structure)->toHaveCount(4)->toMatchArray(['BASIC' => '8000.00', 'HOUSING' => '2000.00', 'TRANSPORT' => '600.00', 'LATE' => '33.33'])
        ->and($entries)->toBe(['MEAL' => '300.00']);
});

it('exposes a JSON-safe snapshot of everything it returned', function () {
    $snapshot = app(PayrollInputs::class)->for($this->employee, '2026-11-01', '2026-11-30')->toArray();

    expect($snapshot)->toHaveKeys(['employee_id', 'period_start', 'period_end', 'basic_salary', 'pay_basis', 'salary_segments', 'structure', 'entries', 'loan_instalments', 'statutory', 'catalogue'])
        ->and($snapshot['structure'])->toHaveKeys(['structure_id', 'version_id', 'assignment_id', 'scope', 'lines'])
        ->and($snapshot['statutory']['income_tax']['brackets'][0])->toBe(['lower' => '0.00', 'upper' => null, 'rate' => '10.0000'])
        ->and(json_decode(json_encode($snapshot), true))->toBe($snapshot);
});

it('reads nothing outside employment and the data scope does not apply', function () {
    $inputs = app(PayrollInputs::class)->for($this->employee, '2023-01-01', '2023-01-31');

    expect($inputs->basicSalary)->toBeNull()->and($inputs->salarySegments)->toBe([]);

    $scoped = adminWithPermissions('allowances.view');
    $scoped->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => 999999]);
    $this->actingAs($scoped->fresh(), 'admin');

    expect(app(PayrollInputs::class)->for($this->employee, '2026-11-01', '2026-11-30')->allowances())->toHaveCount(1);
});

it('refuses a period that ends before it starts', function () {
    expect(fn () => app(PayrollInputs::class)->for($this->employee, '2026-11-30', '2026-11-01'))->toThrow(InvalidArgumentException::class);
});
