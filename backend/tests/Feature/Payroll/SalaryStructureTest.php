<?php

use App\Actions\Employees\ChangeEmployeeSalary;
use App\Actions\Payroll\AssignSalaryStructure;
use App\Actions\Payroll\SaveSalaryStructureVersion;
use App\Enums\PayBasis;
use App\Enums\SalaryStructureScope;
use App\Models\Department;
use App\Models\JobGrade;
use App\Models\SalaryComponent;
use App\Models\SalaryStructureAssignment;
use App\Services\Payroll\PayrollInputs;
use App\Services\Payroll\SalaryStructureResolver;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/*
| Salary structures (Phase 7a): versions are never edited, assignments are
| effective-dated with precedence employee > job grade > department (up the
| tree) > branch > company, and employee_salaries stays the only source of
| the basic salary (with its pay basis and history).
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00:00'));
    $this->basic = SalaryComponent::factory()->basic()->create();
    $this->housing = SalaryComponent::factory()->percentage('25')->create(['code' => 'HOUSING']);
    $this->transport = SalaryComponent::factory()->create(['code' => 'TRANSPORT', 'amount' => '500']);
});

describe('versions', function () {
    it('adds a version that closes the previous one and keeps both', function () {
        $structure = salaryStructure('STD', [$this->basic, $this->housing, $this->transport], '2025-01-01');
        app(SaveSalaryStructureVersion::class)->addVersion($structure, ['effective_from' => '2026-07-01', 'lines' => [
            ['salary_component_id' => $this->basic->id], ['salary_component_id' => $this->transport->id, 'amount' => '600'],
        ]], null);

        $versions = $structure->versions()->get();
        expect($versions)->toHaveCount(2)
            ->and($versions->last()->effective_to->toDateString())->toBe('2026-06-30')
            ->and($structure->versionOn('2026-06-30')->version)->toBe(1)
            ->and($structure->versionOn('2026-07-01')->version)->toBe(2)
            ->and($structure->versionOn('2026-07-01')->lines()->where('salary_component_id', $this->transport->id)->value('amount'))->toBe('600.0000');
    });

    it('refuses a version that does not start after the latest one', function () {
        $structure = salaryStructure('STD', [$this->basic], '2026-01-01');

        expect(fn () => app(SaveSalaryStructureVersion::class)->addVersion($structure, ['effective_from' => '2026-01-01', 'lines' => [['salary_component_id' => $this->basic->id]]], null))
            ->toThrow(ValidationException::class);
    });

    it('validates the lines: no variable components, one basic, bases inside the structure, no cycles, no duplicates', function () {
        $variable = SalaryComponent::factory()->variable()->create();
        $otherBasic = SalaryComponent::factory()->basic()->create(['code' => 'BASIC2']);
        $outside = SalaryComponent::factory()->percentage('10', $this->transport)->create();
        $save = app(SaveSalaryStructureVersion::class);

        foreach ([
            [['salary_component_id' => $variable->id]],
            [['salary_component_id' => $this->basic->id], ['salary_component_id' => $otherBasic->id]],
            [['salary_component_id' => $outside->id]],
            [['salary_component_id' => $this->basic->id], ['salary_component_id' => $this->basic->id]],
            [],
        ] as $lines) {
            expect(fn () => $save->validatedLines($lines))->toThrow(ValidationException::class);
        }

        $a = SalaryComponent::factory()->percentage('10')->create();
        $b = SalaryComponent::factory()->percentage('10', $a)->create();
        expect(fn () => $save->validatedLines([
            ['salary_component_id' => $a->id, 'base_component_id' => $b->id], ['salary_component_id' => $b->id],
        ]))->toThrow(ValidationException::class);
    });
});

describe('assignments', function () {
    it('applies the most specific assignment: employee > grade > department chain > branch > company', function () {
        $company = salaryStructure('CO', [$this->basic]);
        $branchStructure = salaryStructure('BR', [$this->basic]);
        $parentStructure = salaryStructure('PD', [$this->basic]);
        $gradeStructure = salaryStructure('GR', [$this->basic]);
        $own = salaryStructure('OWN', [$this->basic]);
        $grade = JobGrade::factory()->create();
        $employee = paidEmployee();
        $parent = Department::factory()->create(['branch_id' => $employee->branch_id]);
        Department::query()->whereKey($employee->department_id)->update(['parent_id' => $parent->id]);
        $assign = app(AssignSalaryStructure::class);
        $resolver = app(SalaryStructureResolver::class);

        $assign->handle($company, SalaryStructureScope::Company, null, '2024-01-01', null, null);
        expect($resolver->for($employee, '2026-11-30')->code)->toBe('CO');

        $assign->handle($branchStructure, SalaryStructureScope::Branch, $employee->branch_id, '2024-01-01', null, null);
        expect($resolver->for($employee, '2026-11-30')->code)->toBe('BR');

        $assign->handle($parentStructure, SalaryStructureScope::Department, $parent->id, '2024-01-01', null, null);
        expect(app(SalaryStructureResolver::class)->for($employee, '2026-11-30')->code)->toBe('PD');

        $employee->forceFill(['job_grade_id' => $grade->id])->save();
        $employee->assignments()->update(['job_grade_id' => $grade->id]);
        $assign->handle($gradeStructure, SalaryStructureScope::JobGrade, $grade->id, '2024-01-01', null, null);
        expect(app(SalaryStructureResolver::class)->for($employee, '2026-11-30')->code)->toBe('GR');

        $assign->handle($own, SalaryStructureScope::Employee, $employee->id, '2026-12-01', null, null);
        expect(app(SalaryStructureResolver::class)->for($employee, '2026-11-30')->code)->toBe('GR')
            ->and(app(SalaryStructureResolver::class)->for($employee, '2026-12-31')->code)->toBe('OWN')
            ->and(app(SalaryStructureResolver::class)->for($employee, '2026-12-31')->scope)->toBe(SalaryStructureScope::Employee);
    });

    it('closes the open assignment of the same target instead of overwriting it', function () {
        $first = salaryStructure('ONE', [$this->basic]);
        $second = salaryStructure('TWO', [$this->basic]);
        $employee = paidEmployee();
        $assign = app(AssignSalaryStructure::class);

        $assign->handle($first, SalaryStructureScope::Employee, $employee->id, '2025-01-01', null, null);
        $assign->handle($second, SalaryStructureScope::Employee, $employee->id, '2026-03-01', 'Promotion', null);

        $rows = SalaryStructureAssignment::query()->orderBy('effective_from')->get();
        expect($rows)->toHaveCount(2)
            ->and($rows[0]->effective_to->toDateString())->toBe('2026-02-28')
            ->and($rows[1]->effective_to)->toBeNull()
            ->and(app(SalaryStructureResolver::class)->for($employee, '2026-02-15')->code)->toBe('ONE');

        expect(fn () => $assign->handle($first, SalaryStructureScope::Employee, $employee->id, '2026-03-01', null, null))->toThrow(ValidationException::class);
    });

    it('withdraws only assignments that have not taken effect and reopens the previous one', function () {
        $first = salaryStructure('ONE', [$this->basic]);
        $second = salaryStructure('TWO', [$this->basic]);
        $assign = app(AssignSalaryStructure::class);
        $old = $assign->handle($first, SalaryStructureScope::Company, null, '2025-01-01', null, null);
        $future = $assign->handle($second, SalaryStructureScope::Company, null, '2027-01-01', null, null);

        expect(fn () => $assign->withdraw($old->refresh(), null))->toThrow(ValidationException::class);

        $assign->withdraw($future, null);
        expect(SalaryStructureAssignment::query()->count())->toBe(1)->and($old->refresh()->effective_to)->toBeNull();
    });

    it('ignores inactive structures', function () {
        $structure = salaryStructure('OFF', [$this->basic]);
        app(AssignSalaryStructure::class)->handle($structure, SalaryStructureScope::Company, null, '2025-01-01', null, null);
        $structure->update(['is_active' => false]);

        expect(app(SalaryStructureResolver::class)->for(paidEmployee(), '2026-11-30'))->toBeNull();
    });
});

describe('basic salary and pay basis', function () {
    it('keeps employee_salaries as the only source, with the pay basis carried over and mirrored', function () {
        $employee = paidEmployee('300.00', PayBasis::Daily, from: '2024-01-01');
        expect($employee->pay_basis)->toBe(PayBasis::Daily)->and($employee->basic_salary)->toBe('300.00');

        app(ChangeEmployeeSalary::class)->handle($employee, '320.00', '2026-11-01', 'Raise');
        $employee->refresh();

        expect($employee->pay_basis)->toBe(PayBasis::Daily)
            ->and($employee->salaries()->latest('id')->first()->pay_basis)->toBe(PayBasis::Daily)
            ->and($employee->salaries()->count())->toBe(2);

        app(ChangeEmployeeSalary::class)->handle($employee, '7000.00', '2026-11-10', 'Monthly contract', payBasis: PayBasis::Monthly);
        expect($employee->refresh()->pay_basis)->toBe(PayBasis::Monthly);
    });

    it('splits a period at mid-period salary changes, including scheduled ones', function () {
        $employee = paidEmployee('6000.00');
        app(ChangeEmployeeSalary::class)->handle($employee, '6600.00', '2026-11-16', 'Raise');
        app(ChangeEmployeeSalary::class)->handle($employee, '7000.00', '2026-12-10', 'Scheduled raise');

        $segments = app(PayrollInputs::class)->for($employee, '2026-11-01', '2026-11-30')->salarySegments;
        expect(array_map(fn ($segment) => [$segment->from, $segment->to, $segment->basicSalary], $segments))->toBe([
            ['2026-11-01', '2026-11-15', '6000.00'], ['2026-11-16', '2026-11-30', '6600.00'],
        ]);

        $december = app(PayrollInputs::class)->for($employee, '2026-12-01', '2026-12-31');
        expect($december->basicSalary)->toBe('7000.00')->and($december->salarySegments)->toHaveCount(2);
    });
});
