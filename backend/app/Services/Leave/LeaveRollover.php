<?php

namespace App\Services\Leave;

use App\Enums\LeaveTransactionType;
use App\Models\Employee;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveType;
use Illuminate\Support\Facades\DB;

/**
 * Closes a leave year into the next one (command leave:rollover; safe to
 * run daily — every balance is rolled once, reference "rollover:*").
 *
 * For each employee and balance-tracked type with ledger rows in the closing
 * year, using the policy in force on the last day of that year:
 * - a positive balance: up to carry_over_max_days (null = all) moves to the
 *   new year as a carry-over credit (expiring after carry_over_expiry_months),
 *   the rest expires;
 * - a negative balance (allowed by the policy) moves to the new year as a debt;
 * - the closing year ends at zero.
 *
 * Employees terminated before the new year starts are skipped (their final
 * balance stays where it is, for the final settlement).
 */
class LeaveRollover
{
    public function __construct(
        private readonly LeaveLedger $ledger,
        private readonly LeaveYear $years,
        private readonly LeavePolicyResolver $policies,
    ) {}

    /**
     * @return int balances rolled over
     */
    public function rollover(int $toYear, ?int $employeeId = null): int
    {
        $fromYear = $toYear - 1;
        $closing = $this->years->end($fromYear);
        $opening = $this->years->start($toYear);
        $types = LeaveType::query()->where('tracks_balance', true)->get()->keyBy('id');
        $pairs = LeaveBalanceTransaction::query()->withoutGlobalScopes()
            ->where('leave_year', $fromYear)
            ->whereIn('leave_type_id', $types->keys())
            ->when($employeeId, fn ($query, int $id) => $query->where('employee_id', $id))
            ->select('employee_id', 'leave_type_id')->distinct()->get();
        $employees = Employee::query()->withoutGlobalScopes()->with('groups')->whereIn('id', $pairs->pluck('employee_id')->unique())->get()->keyBy('id');
        $count = 0;

        foreach ($pairs as $pair) {
            $employee = $employees->get($pair->employee_id);
            $type = $types->get($pair->leave_type_id);

            if ($employee === null || $type === null) {
                continue;
            }

            if ($employee->termination_date !== null && $employee->termination_date->format('Y-m-d') < $opening->format('Y-m-d')) {
                continue;
            }

            $count += $this->rollOne($employee, $type, $fromYear, $toYear, $closing->format('Y-m-d'), $opening->format('Y-m-d')) ? 1 : 0;
        }

        return $count;
    }

    private function rollOne(Employee $employee, LeaveType $type, int $fromYear, int $toYear, string $closing, string $opening): bool
    {
        $base = "rollover:{$employee->id}:{$type->id}:{$fromYear}";

        if ($this->ledger->hasReference($base.':out') || $this->ledger->hasReference($base.':forfeit')) {
            return false;
        }

        return DB::transaction(function () use ($employee, $type, $fromYear, $toYear, $closing, $opening, $base): bool {
            Employee::query()->withoutGlobalScopes()->whereKey($employee->id)->lockForUpdate()->first();

            $balance = $this->ledger->sum($employee->id, $type->id, $fromYear);
            $policy = $this->policies->for($employee, $type, $closing);
            $sign = bccomp($balance, '0', 2);

            if ($sign === 0) {
                return false;
            }

            if ($sign < 0) {
                $carry = $balance;
                $forfeit = '0.00';
            } else {
                $max = $policy?->carry_over_max_days;
                $carry = $policy === null ? '0.00' : ($max === null ? $balance : (bccomp($balance, (string) $max, 2) > 0 ? bcadd((string) $max, '0', 2) : $balance));
                $forfeit = bcsub($balance, $carry, 2);
            }

            $expires = $policy?->carry_over_expiry_months && bccomp($carry, '0', 2) > 0
                ? $this->years->start($toYear)->addMonths($policy->carry_over_expiry_months)->subDay()->format('Y-m-d')
                : null;

            $this->ledger->record($employee, $type, $fromYear, LeaveTransactionType::CarryOver, bcmul($carry, '-1', 2), $closing,
                ['reference' => $base.':out', 'leave_policy_id' => $policy?->id]);
            $this->ledger->record($employee, $type, $fromYear, LeaveTransactionType::Expiry, bcmul($forfeit, '-1', 2), $closing,
                ['reference' => $base.':forfeit', 'leave_policy_id' => $policy?->id]);
            $this->ledger->record($employee, $type, $toYear, LeaveTransactionType::CarryOver, $carry, $opening,
                ['reference' => $base.':in', 'expires_on' => $expires, 'leave_policy_id' => $policy?->id]);

            return true;
        });
    }
}
