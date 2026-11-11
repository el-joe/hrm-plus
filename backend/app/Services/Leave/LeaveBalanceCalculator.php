<?php

namespace App\Services\Leave;

use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveTransactionType;
use App\Models\Employee;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveRequestDay;
use App\Models\LeaveType;
use App\Services\Leave\Data\LeaveBalance;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Balances per employee, leave type and leave year, computed from the
 * ledger (never stored): see App\Services\Leave\Data\LeaveBalance.
 */
class LeaveBalanceCalculator
{
    public function __construct(
        private readonly LeaveLedger $ledger,
        private readonly LeaveYear $years,
        private readonly LeavePolicyResolver $policies,
        private readonly LeaveEntitlement $entitlement,
    ) {}

    public function for(Employee $employee, LeaveType $type, ?int $year = null): LeaveBalance
    {
        $year ??= $this->years->current();
        $sums = $this->ledger->sumsByKind($employee->id, $type->id, $year);
        $get = fn (LeaveTransactionType $kind): string => $sums[$kind->value] ?? '0.00';
        $carry = $this->ledger->rows($employee->id, $type->id, $year)->where('type', LeaveTransactionType::CarryOver)
            ->selectRaw('SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as carried_in, SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END) as carried_out')
            ->first();

        $balance = '0.00';

        foreach ($sums as $sum) {
            $balance = bcadd($balance, $sum, 2);
        }

        $policyDate = CarbonImmutable::now()->max($this->years->start($year))->min($this->years->end($year));
        $policy = $this->policies->for($employee, $type, $policyDate);
        $pending = $this->requestDays($employee->id, $type->id, $year, [LeaveRequestStatus::Pending]);

        return new LeaveBalance(
            type: $type,
            year: $year,
            policy: $policy,
            entitlement: $policy && $type->tracks_balance ? $this->entitlement->yearly($policy, $type, $employee, $year) : '0.00',
            carriedIn: bcadd((string) ($carry->carried_in ?? 0), '0', 2),
            accrued: $get(LeaveTransactionType::Accrual),
            adjusted: $get(LeaveTransactionType::Adjustment),
            used: bcmul(bcadd($get(LeaveTransactionType::Usage), $get(LeaveTransactionType::Reversal), 2), '-1', 2),
            expired: bcmul($get(LeaveTransactionType::Expiry), '-1', 2),
            encashed: bcmul($get(LeaveTransactionType::Encashment), '-1', 2),
            carriedOut: bcadd((string) ($carry->carried_out ?? 0), '0', 2),
            balance: $balance,
            pending: $pending,
            available: bcsub($balance, $pending, 2),
            taken: $this->requestDays($employee->id, $type->id, $year, LeaveRequestStatus::taken()),
        );
    }

    /**
     * Days available for a request charged to the year. For a future leave
     * year (not accrued yet) the year's projected entitlement under the policy
     * in force on its first day is added to what is already booked there.
     */
    public function availableFor(Employee $employee, LeaveType $type, int $year): string
    {
        $available = $this->for($employee, $type, $year)->available;

        if ($year <= $this->years->current()) {
            return $available;
        }

        $policy = $this->policies->for($employee, $type, $this->years->start($year));

        if ($policy === null) {
            return $available;
        }

        $projected = bcsub(
            $this->entitlement->yearly($policy, $type, $employee, $year),
            $this->ledger->sum($employee->id, $type->id, $year, [LeaveTransactionType::Accrual]),
            2,
        );

        return bccomp($projected, '0', 2) > 0 ? bcadd($available, $projected, 2) : $available;
    }

    /**
     * Balances of every active leave type open to the employee's gender (or the given types), in display order.
     *
     * @return Collection<int, LeaveBalance>
     */
    public function all(Employee $employee, ?int $year = null, ?Collection $types = null): Collection
    {
        $types ??= LeaveType::query()->active()->ordered()->get()
            ->filter(fn (LeaveType $type): bool => $type->gender === null || $type->gender === $employee->gender);

        return $types->map(fn (LeaveType $type): LeaveBalance => $this->for($employee, $type, $year))->values();
    }

    /**
     * Balance, pending and available of many employees at once (list screens, reports):
     * [employee id][type id] => {balance, pending, available, used}.
     *
     * @param  list<int>  $employeeIds
     * @param  list<int>  $typeIds
     * @return array<int, array<int, array{balance: string, pending: string, available: string, used: string}>>
     */
    public function summary(array $employeeIds, array $typeIds, int $year): array
    {
        $result = [];

        foreach ($employeeIds as $employeeId) {
            foreach ($typeIds as $typeId) {
                $result[$employeeId][$typeId] = ['balance' => '0.00', 'pending' => '0.00', 'available' => '0.00', 'used' => '0.00'];
            }
        }

        if ($employeeIds === [] || $typeIds === []) {
            return $result;
        }

        $ledger = LeaveBalanceTransaction::query()->withoutGlobalScopes()
            ->whereIn('employee_id', $employeeIds)->whereIn('leave_type_id', $typeIds)->where('leave_year', $year)
            ->selectRaw("employee_id, leave_type_id, SUM(amount) as balance, SUM(CASE WHEN type IN ('usage', 'reversal') THEN -amount ELSE 0 END) as used")
            ->groupBy('employee_id', 'leave_type_id')->get();

        foreach ($ledger as $row) {
            $result[$row->employee_id][$row->leave_type_id]['balance'] = bcadd((string) $row->balance, '0', 2);
            $result[$row->employee_id][$row->leave_type_id]['used'] = bcadd((string) $row->used, '0', 2);
        }

        $pending = LeaveRequestDay::query()
            ->join('leave_requests', 'leave_requests.id', '=', 'leave_request_days.leave_request_id')
            ->whereIn('leave_request_days.employee_id', $employeeIds)->whereIn('leave_requests.leave_type_id', $typeIds)
            ->where('leave_request_days.leave_year', $year)->where('leave_request_days.is_counted', true)
            ->where('leave_requests.status', LeaveRequestStatus::Pending->value)
            ->selectRaw('leave_request_days.employee_id, leave_requests.leave_type_id, SUM(leave_request_days.amount) as pending')
            ->groupBy('leave_request_days.employee_id', 'leave_requests.leave_type_id')->get();

        foreach ($pending as $row) {
            $result[$row->employee_id][$row->leave_type_id]['pending'] = bcadd((string) $row->pending, '0', 2);
        }

        foreach ($result as $employeeId => $types) {
            foreach ($types as $typeId => $values) {
                $result[$employeeId][$typeId]['available'] = bcsub($values['balance'], $values['pending'], 2);
            }
        }

        return $result;
    }

    /**
     * Days of the employee's requests in the given statuses charged to the year.
     *
     * @param  list<LeaveRequestStatus>  $statuses
     */
    public function requestDays(int $employeeId, int $typeId, int $year, array $statuses): string
    {
        $sum = LeaveRequestDay::query()
            ->where('employee_id', $employeeId)->where('leave_year', $year)->where('is_counted', true)
            ->whereHas('request', fn (Builder $query) => $query->withoutGlobalScopes()->where('leave_type_id', $typeId)
                ->whereIn('status', array_map(fn (LeaveRequestStatus $status): string => $status->value, $statuses)))
            ->sum('amount');

        return bcadd((string) $sum, '0', 2);
    }
}
