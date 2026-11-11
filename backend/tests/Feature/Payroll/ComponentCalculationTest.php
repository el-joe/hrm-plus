<?php

use App\Actions\Payroll\SaveSalaryComponent;
use App\Actions\Payroll\SaveStatutoryRuleVersion;
use App\Enums\PayBasis;
use App\Enums\PayEntryRecurrence;
use App\Enums\PayEntryTarget;
use App\Enums\PayRounding;
use App\Enums\StatutoryType;
use App\Exceptions\PayrollConfigurationException;
use App\Models\SalaryComponent;
use App\Models\StatutoryRuleSet;
use App\Services\Payroll\ComponentCalculator;
use App\Services\Payroll\Data\ComponentRule;
use App\Services\Payroll\Data\PayEntryInput;
use App\Services\Payroll\PayrollMath;
use App\Services\Payroll\Proration;
use App\Services\Payroll\SalaryRates;
use App\Services\Payroll\StatutoryCalculator;
use App\Services\Payroll\StatutoryRules;
use App\Services\Settings\SettingsService;
use Illuminate\Validation\ValidationException;

/*
| Payroll components (Phase 7a): every calculation type, percentage chaining,
| rounding, proration and the statutory tables. Pure bcmath — no float anywhere.
*/

function rule(SalaryComponent $component): ComponentRule
{
    return ComponentRule::fromComponent($component->refresh());
}

describe('calculation types', function () {
    it('prorates a fixed amount and keeps a basic salary as given', function () {
        $fixed = SalaryComponent::factory()->create(['amount' => '1000']);
        $basic = SalaryComponent::factory()->basic()->create();
        $calculator = app(ComponentCalculator::class);

        expect($calculator->amount(rule($fixed), payContext('6000')))->toBe('1000.00')
            ->and($calculator->amount(rule($fixed), payContext('6000', '0.5')))->toBe('500.00')
            ->and($calculator->amount(rule($basic), payContext('6000', '0.5')))->toBe('3000.00');

        $fixed->update(['prorate_partial_period' => false]);
        expect($calculator->amount(rule($fixed), payContext('6000', '0.5')))->toBe('1000.00');
    });

    it('takes a percentage of the basic salary', function () {
        $housing = SalaryComponent::factory()->percentage('25')->create();

        expect(app(ComponentCalculator::class)->amount(rule($housing), payContext('6000')))->toBe('1500.00');
    });

    it('chains percentages in dependency order whatever the order of the rules', function () {
        $first = SalaryComponent::factory()->percentage('10')->create(['code' => 'FIRST']);
        $second = SalaryComponent::factory()->percentage('50', $first)->create(['code' => 'SECOND']);
        $third = SalaryComponent::factory()->percentage('10', $second)->create(['code' => 'THIRD']);

        $amounts = app(ComponentCalculator::class)->evaluate([rule($third), rule($second), rule($first)], payContext('6000'));

        expect($amounts)->toBe(['FIRST' => '600.00', 'SECOND' => '300.00', 'THIRD' => '30.00']);
    });

    it('refuses a percentage cycle at calculation time and when saving', function () {
        $a = SalaryComponent::factory()->percentage('10')->create(['code' => 'A']);
        $b = SalaryComponent::factory()->percentage('10', $a)->create(['code' => 'B']);
        $a->update(['base_component_id' => $b->id]);

        expect(fn () => app(ComponentCalculator::class)->evaluate([rule($a), rule($b)], payContext()))
            ->toThrow(PayrollConfigurationException::class);

        $a->update(['base_component_id' => null]);
        expect(fn () => app(SaveSalaryComponent::class)->handle($a->refresh(), [...$a->only(['code', 'name_ar', 'name_en', 'kind', 'category']),
            'calculation_type' => 'percentage', 'rate' => '10', 'base_component_id' => $b->id, 'rounding' => 'half_up', 'display_order' => 1]))
            ->toThrow(ValidationException::class);
    });

    it('prices late and early minutes from the minute rate or a fixed amount per minute', function () {
        $late = SalaryComponent::factory()->deduction()->create(['calculation_type' => 'late_minutes', 'amount' => null, 'rate' => '1']);
        $early = SalaryComponent::factory()->deduction()->create(['calculation_type' => 'early_leave_minutes', 'amount' => '2', 'rate' => null]);
        $context = payContext('6000', quantities: ['late_minutes' => '30', 'early_leave_minutes' => '30']);

        // 6000 / 30 days / 8 hours / 60 = 0.416666 per minute.
        expect(app(ComponentCalculator::class)->amount(rule($late), $context))->toBe('12.50')
            ->and(app(ComponentCalculator::class)->amount(rule($early), $context))->toBe('60.00');
    });

    it('prices absent and unpaid leave days from the day rate with a multiplier', function () {
        $absence = SalaryComponent::factory()->deduction()->create(['calculation_type' => 'absent_days', 'amount' => null, 'rate' => '1.5']);
        $unpaid = SalaryComponent::factory()->deduction()->create(['calculation_type' => 'unpaid_leave_days', 'amount' => null, 'rate' => null]);
        $context = payContext('6000', quantities: ['absent_days' => '2', 'unpaid_leave_days' => '1.5']);

        expect(app(ComponentCalculator::class)->amount(rule($absence), $context))->toBe('600.00')
            ->and(app(ComponentCalculator::class)->amount(rule($unpaid), $context))->toBe('300.00');
    });

    it('prices overtime with the attendance policy multipliers or the component rate', function () {
        $policy = SalaryComponent::factory()->create(['calculation_type' => 'overtime_hours', 'amount' => null, 'use_policy_multiplier' => true]);
        $flat = SalaryComponent::factory()->create(['calculation_type' => 'overtime_hours', 'amount' => null, 'rate' => '1.25']);
        $context = payContext('6000', overtime: [['hours' => '10', 'multiplier' => '1.5'], ['hours' => '4', 'multiplier' => '2']]);

        // Hour rate 25: 10 × 25 × 1.5 + 4 × 25 × 2 = 575; flat 14 × 25 × 1.25 = 437.50.
        expect(app(ComponentCalculator::class)->amount(rule($policy), $context))->toBe('575.00')
            ->and(app(ComponentCalculator::class)->amount(rule($flat), $context))->toBe('437.50');
    });

    it('derives day and hour rates for every pay basis from the settings', function () {
        app(SettingsService::class)->set('payroll.working_days_per_month', 26);

        expect(app(SalaryRates::class)->for('5200', PayBasis::Monthly))->toMatchArray(['day' => '200.000000', 'hour' => '25.000000'])
            ->and(app(SalaryRates::class)->for('200', PayBasis::Daily))->toMatchArray(['day' => '200', 'hour' => '25.000000'])
            ->and(app(SalaryRates::class)->for('25', PayBasis::Hourly)['day'])->toBe('200.000000');
    });

    it('leaves statutory components to the statutory calculator', function () {
        $tax = SalaryComponent::factory()->statutory(StatutoryType::IncomeTax)->create();

        expect(app(ComponentCalculator::class)->amount(rule($tax), payContext()))->toBeNull();
    });
});

describe('rounding and proration', function () {
    it('rounds with bcmath in every mode', function () {
        expect(PayrollMath::round('12.345', 2, PayRounding::HalfUp))->toBe('12.35')
            ->and(PayrollMath::round('12.344', 2, PayRounding::HalfUp))->toBe('12.34')
            ->and(PayrollMath::round('12.349', 2, PayRounding::Down))->toBe('12.34')
            ->and(PayrollMath::round('12.341', 2, PayRounding::Up))->toBe('12.35')
            ->and(PayrollMath::round('12.5', 0, PayRounding::HalfUp))->toBe('13')
            ->and(PayrollMath::round('-12.345', 2, PayRounding::HalfUp))->toBe('-12.35');
    });

    it('uses the component precision and falls back to the setting', function () {
        $component = SalaryComponent::factory()->create(['amount' => '10.555', 'rounding_precision' => 1]);
        expect(app(ComponentCalculator::class)->amount(rule($component), payContext()))->toBe('10.6');

        app(SettingsService::class)->set('payroll.rounding_precision', 0);
        $component->update(['rounding_precision' => null]);
        expect(app(ComponentCalculator::class)->amount(rule($component), payContext()))->toBe('11');
    });

    it('computes calendar-day proration factors', function () {
        $proration = app(Proration::class);

        expect($proration->factor('2026-11-01', '2026-11-30', '2026-11-16', null))->toBe('0.500000')
            ->and($proration->factor('2026-11-01', '2026-11-30', null, null))->toBe('1')
            ->and($proration->factor('2026-11-01', '2026-11-30', '2026-12-01', null))->toBe('0');
    });

    it('prorates a recurring entry that starts inside the period', function () {
        $meal = SalaryComponent::factory()->create(['code' => 'MEAL']);
        $entryRule = rule($meal)->with('300.00', null, null, false, false, true);
        $entry = new PayEntryInput(1, 'ALW-1', $entryRule, PayEntryTarget::Employee, PayEntryRecurrence::Recurring, '2026-11-16', '2026-11-30');

        expect(app(ComponentCalculator::class)->evaluateEntries([$entry], payContext(), '2026-11-01', '2026-11-30'))->toBe(['MEAL' => '150.00']);
    });
});

describe('statutory tables', function () {
    beforeEach(function () {
        app(SettingsService::class)->set('payroll.country_code', 'EG');
        $this->insurance = StatutoryRuleSet::create(['code' => 'SI', 'name_ar' => 'تأمينات', 'name_en' => 'Insurance', 'country_code' => 'EG', 'type' => 'social_insurance', 'is_active' => true]);
        $this->tax = StatutoryRuleSet::create(['code' => 'TAX', 'name_ar' => 'ضريبة', 'name_en' => 'Tax', 'country_code' => 'EG', 'type' => 'income_tax', 'is_demo' => true, 'is_active' => true]);
        $save = app(SaveStatutoryRuleVersion::class);
        $save->handle($this->insurance, ['effective_from' => '2025-01-01', 'employee_rate' => '10', 'employer_rate' => '15', 'salary_floor' => '2000', 'salary_ceiling' => '12000'], null);
        $save->handle($this->insurance, ['effective_from' => '2026-01-01', 'employee_rate' => '11', 'employer_rate' => '15', 'salary_floor' => '2000', 'salary_ceiling' => '12000'], null);
        $save->handle($this->tax, ['effective_from' => '2025-01-01', 'annual_exemption' => '20000', 'brackets' => [
            ['lower_bound' => '0', 'upper_bound' => '30000', 'rate' => '0'],
            ['lower_bound' => '30000', 'upper_bound' => '60000', 'rate' => '10'],
            ['lower_bound' => '60000', 'upper_bound' => null, 'rate' => '15'],
        ]], null);
    });

    it('resolves the version in force on a date and closes the previous one', function () {
        $rules = app(StatutoryRules::class);

        expect($rules->for(StatutoryType::SocialInsurance, '2025-06-30')->employeeRate)->toBe('10.0000')
            ->and($rules->for(StatutoryType::SocialInsurance, '2026-11-30')->employeeRate)->toBe('11.0000')
            ->and($this->insurance->versions()->where('version', 1)->value('effective_to'))->toStartWith('2025-12-31')
            ->and($rules->for(StatutoryType::IncomeTax, '2026-11-30')->isDemo)->toBeTrue();
    });

    it('ignores inactive tables, other countries and an unset payroll country', function () {
        $this->tax->update(['is_active' => false]);
        expect(app(StatutoryRules::class)->for(StatutoryType::IncomeTax, '2026-11-30'))->toBeNull()
            ->and(app(StatutoryRules::class)->for(StatutoryType::SocialInsurance, '2026-11-30', 'SA'))->toBeNull();

        app(SettingsService::class)->set('payroll.country_code', '');
        expect(app(StatutoryRules::class)->all('2026-11-30'))->toBe([]);
    });

    it('never edits a version and refuses one that does not start after the latest', function () {
        expect(fn () => app(SaveStatutoryRuleVersion::class)->handle($this->insurance, ['effective_from' => '2026-01-01', 'employee_rate' => '12'], null))
            ->toThrow(ValidationException::class);

        expect(fn () => app(SaveStatutoryRuleVersion::class)->handle($this->tax, ['effective_from' => '2027-01-01', 'brackets' => [
            ['lower_bound' => '0', 'upper_bound' => '30000', 'rate' => '0'], ['lower_bound' => '40000', 'upper_bound' => null, 'rate' => '10'],
        ]], null))->toThrow(ValidationException::class);
    });

    it('applies insurance floor and ceiling and progressive tax brackets', function () {
        $calculator = app(StatutoryCalculator::class);
        $insurance = app(StatutoryRules::class)->for(StatutoryType::SocialInsurance, '2026-11-30');
        $tax = app(StatutoryRules::class)->for(StatutoryType::IncomeTax, '2026-11-30');

        expect($calculator->socialInsurance($insurance, '1000'))->toBe(['base' => '2000.00', 'employee' => '220.00', 'employer' => '300.00'])
            ->and($calculator->socialInsurance($insurance, '20000'))->toBe(['base' => '12000.00', 'employee' => '1320.00', 'employer' => '1800.00']);

        // 10000 × 12 − 20000 = 100000: 3000 (10% of 30000) + 6000 (15% of 40000) = 9000 a year → 750 a month.
        expect($calculator->incomeTax($tax, '10000'))->toBe('750.00')
            ->and($calculator->incomeTax($tax, '2000'))->toBe('0.00');
    });
});
