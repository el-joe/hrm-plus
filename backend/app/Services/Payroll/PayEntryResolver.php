<?php

namespace App\Services\Payroll;

use App\Enums\PayCalculationType;
use App\Enums\PayEntryCalculation;
use App\Enums\PayEntryRecurrence;
use App\Enums\PayEntryTarget;
use App\Models\Employee;
use App\Models\PayEntry;
use App\Models\SalaryComponent;
use App\Services\Payroll\Data\ComponentRule;
use App\Services\Payroll\Data\PayEntryInput;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Approved allowances and deductions that apply to an employee in a period.
 *
 * - recurring: every period overlapping [start_date, end_date];
 * - one-time: the period containing start_date;
 * - instalment (deductions): the period containing the due date of an
 *   instalment (start_date + n − 1 months), with that instalment's amount.
 *
 * Scope precedence per component, most specific first: employee > employee
 * group > department (the employee's, then its parents) > branch > company.
 * Only entries of the most specific level that has any entry of a component
 * apply (an employee-specific housing allowance replaces the branch one);
 * several entries of that level all apply. Placement (department, branch)
 * is the one in force at the period end.
 */
class PayEntryResolver
{
    public function __construct(private readonly PayrollPlacement $placement) {}

    /**
     * @return list<PayEntryInput>
     */
    public function for(Employee $employee, CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->startOfDay();
        $placement = $this->placement->on($employee, $end);
        $departments = $this->placement->departmentChain($placement?->department_id ?? $employee->department_id);
        $branch = $placement?->branch_id ?? $employee->branch_id;
        $groups = $this->placement->groupIds($employee);

        $entries = PayEntry::query()->withoutGlobalScopes()
            ->activeBetween($start->format('Y-m-d'), $end->format('Y-m-d'))
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $q) => $q->where('target_type', PayEntryTarget::Employee)->where('employee_id', $employee->id))
                ->orWhere(fn (Builder $q) => $q->where('target_type', PayEntryTarget::EmployeeGroup)->whereIn('employee_group_id', $groups ?: [0]))
                ->orWhere(fn (Builder $q) => $q->where('target_type', PayEntryTarget::Department)->whereIn('department_id', $departments ?: [0]))
                ->orWhere(fn (Builder $q) => $q->where('target_type', PayEntryTarget::Branch)->where('branch_id', $branch))
                ->orWhere('target_type', PayEntryTarget::Company))
            ->with(['component', 'baseComponent'])
            ->orderBy('id')
            ->get()
            ->filter(fn (PayEntry $entry): bool => $this->occursIn($entry, $start, $end) !== null);

        $codes = SalaryComponent::query()->pluck('code', 'id')->all();

        return $entries->groupBy('salary_component_id')
            ->flatMap(fn (Collection $group): Collection => $this->mostSpecific($group, $departments))
            ->sortBy('id')
            ->map(fn (PayEntry $entry): PayEntryInput => $this->toInput($entry, $start, $end, $codes))
            ->values()->all();
    }

    /**
     * Instalment number falling in the period (instalment entries), 0 for other kinds that occur, null when nothing occurs.
     */
    public function occursIn(PayEntry $entry, CarbonImmutable $start, CarbonImmutable $end): ?int
    {
        $first = $entry->start_date->toImmutable();

        return match ($entry->recurrence) {
            PayEntryRecurrence::Recurring => $first->lessThanOrEqualTo($end) && ($entry->end_date === null || $entry->end_date->greaterThanOrEqualTo($start)) ? 0 : null,
            PayEntryRecurrence::OneTime => $first->betweenIncluded($start, $end) ? 0 : null,
            PayEntryRecurrence::Instalment => $this->instalmentIn($entry, $start, $end),
        };
    }

    private function instalmentIn(PayEntry $entry, CarbonImmutable $start, CarbonImmutable $end): ?int
    {
        for ($number = 1; $number <= (int) $entry->instalment_count; $number++) {
            if (CarbonImmutable::parse($entry->instalmentDueDate($number))->betweenIncluded($start, $end)) {
                return $number;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, PayEntry>  $group  entries of one component
     * @param  list<int>  $departments  nearest first
     * @return Collection<int, PayEntry>
     */
    private function mostSpecific(Collection $group, array $departments): Collection
    {
        $rank = function (PayEntry $entry) use ($departments): int {
            return match ($entry->target_type) {
                PayEntryTarget::Employee => 0,
                PayEntryTarget::EmployeeGroup => 1,
                PayEntryTarget::Department => 10 + (int) array_search($entry->department_id, $departments, true),
                PayEntryTarget::Branch => 1000,
                PayEntryTarget::Company => 1001,
            };
        };

        $best = $group->map($rank)->min();

        return $group->filter(fn (PayEntry $entry): bool => $rank($entry) === $best);
    }

    /**
     * @param  array<int, string>  $codes
     */
    private function toInput(PayEntry $entry, CarbonImmutable $start, CarbonImmutable $end, array $codes): PayEntryInput
    {
        $component = $entry->component;
        $rule = ComponentRule::fromComponent($component, codes: $codes);
        $number = null;

        if ($entry->recurrence === PayEntryRecurrence::Instalment) {
            $number = $this->instalmentIn($entry, $start, $end);
            $amount = $entry->instalmentAmount((int) $number);
        } else {
            $amount = $entry->calculation === PayEntryCalculation::Fixed ? (string) $entry->amount : null;
        }

        $isPercentage = $entry->calculation === PayEntryCalculation::Percentage && $entry->recurrence !== PayEntryRecurrence::Instalment;
        $recurring = $entry->recurrence === PayEntryRecurrence::Recurring;

        $rule = $rule->with(
            amount: $amount,
            rate: $isPercentage ? (string) $entry->rate : null,
            baseCode: $isPercentage && $entry->base_component_id !== null ? ($codes[$entry->base_component_id] ?? null) : null,
            taxable: $entry->is_taxable ?? $component->is_taxable,
            insurable: $entry->is_insurable ?? $component->is_insurable,
            prorate: $recurring && ($entry->prorate ?? $component->prorate_partial_period),
        );

        $activeFrom = $recurring && $entry->start_date->greaterThan($start) ? $entry->start_date->toDateString() : $start->toDateString();
        $activeTo = $recurring && $entry->end_date !== null && $entry->end_date->lessThan($end) ? $entry->end_date->toDateString() : $end->toDateString();

        return new PayEntryInput(
            entryId: $entry->id,
            reference: $entry->reference,
            rule: $isPercentage ? $this->asPercentage($rule) : $this->asFixed($rule),
            target: $entry->target_type,
            recurrence: $entry->recurrence,
            activeFrom: $activeFrom,
            activeTo: $activeTo,
            instalmentNumber: $number,
            instalmentCount: $entry->recurrence === PayEntryRecurrence::Instalment ? $entry->instalment_count : null,
        );
    }

    /**
     * Entry rules are always fixed or percentage, whatever the catalogue type (a "variable" component gets its amount here).
     */
    private function asFixed(ComponentRule $rule): ComponentRule
    {
        return new ComponentRule(
            $rule->componentId, $rule->code, $rule->nameAr, $rule->nameEn, $rule->kind, $rule->category, PayCalculationType::Fixed,
            $rule->amount, null, null, false, null, $rule->taxable, $rule->insurable, $rule->prorate, $rule->showOnPayslip,
            $rule->rounding, $rule->precision, $rule->displayOrder,
        );
    }

    private function asPercentage(ComponentRule $rule): ComponentRule
    {
        return new ComponentRule(
            $rule->componentId, $rule->code, $rule->nameAr, $rule->nameEn, $rule->kind, $rule->category, PayCalculationType::Percentage,
            null, $rule->rate, $rule->baseCode, false, null, $rule->taxable, $rule->insurable, $rule->prorate, $rule->showOnPayslip,
            $rule->rounding, $rule->precision, $rule->displayOrder,
        );
    }
}
