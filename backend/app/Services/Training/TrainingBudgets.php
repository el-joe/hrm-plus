<?php

namespace App\Services\Training;

use App\Enums\TrainingEnrolmentStatus;
use App\Models\TrainingBudget;
use App\Models\TrainingEnrolment;
use Illuminate\Support\Collection;

/**
 * Training budget figures of a year, in bcmath (DECIMAL strings, 2 decimals):
 *
 * - spent of a department budget = cost snapshots of the enrolments of that department
 *   (enrolled, completed, failed, no-show — a seat that was paid for) on sessions starting
 *   in the year;
 * - spent of the company-wide budget (department null) = the same total over every department;
 * - remaining = budget − spent (negative when over budget); used % = spent / budget × 100.
 *
 * Enrolment queries ignore admin data scopes: a budget is a company figure.
 */
class TrainingBudgets
{
    /**
     * Rows of the budgets of a year, company-wide first.
     *
     * @return Collection<int, array{budget: TrainingBudget, spent: string, committed: string, remaining: string, used_percent: string, over: bool}>
     */
    public function forYear(int $year): Collection
    {
        $byDepartment = $this->spentByDepartment($year);
        $pending = $this->pendingByDepartment($year);
        $total = $byDepartment->reduce(fn (string $carry, string $amount): string => bcadd($carry, $amount, 2), '0.00');
        $pendingTotal = $pending->reduce(fn (string $carry, string $amount): string => bcadd($carry, $amount, 2), '0.00');

        return TrainingBudget::query()->where('year', $year)
            ->with(['department' => fn ($query) => $query->withoutGlobalScopes()])
            ->orderByRaw('department_id is not null')->orderBy('department_id')->get()
            ->map(function (TrainingBudget $budget) use ($byDepartment, $pending, $total, $pendingTotal): array {
                $spent = $budget->isCompanyWide() ? $total : ($byDepartment[$budget->department_id] ?? '0.00');
                $committed = $budget->isCompanyWide() ? $pendingTotal : ($pending[$budget->department_id] ?? '0.00');

                return $this->figures($budget, $spent, $committed);
            });
    }

    /**
     * Figures of one budget.
     *
     * @return array{budget: TrainingBudget, spent: string, committed: string, remaining: string, used_percent: string, over: bool}
     */
    public function figures(TrainingBudget $budget, ?string $spent = null, ?string $committed = null): array
    {
        $spent ??= $budget->isCompanyWide()
            ? $this->spentByDepartment($budget->year)->reduce(fn (string $carry, string $amount): string => bcadd($carry, $amount, 2), '0.00')
            : ($this->spentByDepartment($budget->year)[$budget->department_id] ?? '0.00');
        $committed ??= '0.00';
        $amount = (string) $budget->amount;

        return [
            'budget' => $budget,
            'spent' => $spent,
            'committed' => $committed,
            'remaining' => bcsub($amount, $spent, 2),
            'used_percent' => bccomp($amount, '0', 2) === 1 ? bcdiv(bcmul($spent, '100', 4), $amount, 2) : '0.00',
            'over' => bccomp($spent, $amount, 2) === 1,
        ];
    }

    /**
     * Spent per department id ('' key = enrolments without a department) for sessions starting in the year.
     *
     * @return Collection<int|string, string>
     */
    public function spentByDepartment(int $year): Collection
    {
        return $this->sumByDepartment($year, TrainingEnrolmentStatus::costed());
    }

    /**
     * Cost of requests awaiting approval (not spent yet), per department.
     *
     * @return Collection<int|string, string>
     */
    public function pendingByDepartment(int $year): Collection
    {
        return $this->sumByDepartment($year, [TrainingEnrolmentStatus::Pending]);
    }

    /**
     * Total spent of the year over every department.
     */
    public function totalSpent(int $year): string
    {
        return $this->spentByDepartment($year)->reduce(fn (string $carry, string $amount): string => bcadd($carry, $amount, 2), '0.00');
    }

    /**
     * @param  list<TrainingEnrolmentStatus>  $statuses
     * @return Collection<int|string, string>
     */
    private function sumByDepartment(int $year, array $statuses): Collection
    {
        // Each cost is summed as a string by bcmath (never SQL SUM into float).
        $sums = [];

        TrainingEnrolment::query()->withoutGlobalScopes()
            ->whereIn('training_enrolments.status', $statuses)
            ->whereHas('session', fn ($query) => $query->whereYear('start_date', $year))
            ->select(['id', 'department_id', 'cost'])
            ->orderBy('id')
            ->each(function (TrainingEnrolment $enrolment) use (&$sums): void {
                $key = $enrolment->department_id ?? '';
                $sums[$key] = bcadd($sums[$key] ?? '0.00', (string) $enrolment->cost, 2);
            });

        return collect($sums);
    }
}
