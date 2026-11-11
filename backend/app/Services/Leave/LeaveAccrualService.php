<?php

namespace App\Services\Leave;

use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveTransactionType;
use App\Models\Employee;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveRequestDay;
use App\Models\LeaveType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Books accruals and carry-over expiries (command leave:accrue, daily).
 *
 * Accrual is target based and idempotent: the entitlement due by the date
 * (LeaveEntitlement::target) minus what the year already accrued is
 * credited, capped by the policy's maximum balance. A lower target (policy
 * change) never takes days back; a higher one (new service tier, new month)
 * tops the year up.
 *
 * Carry-over expiry: once a carry-over credit's expires_on has passed, the
 * part not used by leave taken up to that date (carried days are used
 * first) is expired, never more than the remaining balance.
 */
class LeaveAccrualService
{
    public function __construct(
        private readonly LeaveLedger $ledger,
        private readonly LeaveYear $years,
        private readonly LeavePolicyResolver $policies,
        private readonly LeaveEntitlement $entitlement,
    ) {}

    /**
     * Accrue every employed employee and every balance-tracked active type as of the date.
     *
     * @return int rows written
     */
    public function accrueAll(CarbonImmutable $asOf, ?int $employeeId = null): int
    {
        $types = LeaveType::query()->active()->where('tracks_balance', true)->get();
        $written = 0;

        Employee::query()->withoutGlobalScopes()->employed()->with('groups')
            ->when($employeeId, fn (Builder $query, int $id) => $query->whereKey($id))
            ->orderBy('id')
            ->chunkById(100, function ($employees) use ($types, $asOf, &$written): void {
                foreach ($employees as $employee) {
                    foreach ($types as $type) {
                        $written += $this->accrue($employee, $type, $asOf) ? 1 : 0;
                    }
                }
            });

        return $written;
    }

    public function accrue(Employee $employee, LeaveType $type, CarbonImmutable $asOf): ?LeaveBalanceTransaction
    {
        if (! $type->tracks_balance) {
            return null;
        }

        $year = $this->years->of($asOf);
        $policy = $this->policies->for($employee, $type, $asOf);

        if ($policy === null) {
            return null;
        }

        return DB::transaction(function () use ($employee, $type, $asOf, $year, $policy): ?LeaveBalanceTransaction {
            Employee::query()->withoutGlobalScopes()->whereKey($employee->id)->lockForUpdate()->first();

            $target = $this->entitlement->target($policy, $type, $employee, $year, $asOf);
            $accrued = $this->ledger->sum($employee->id, $type->id, $year, [LeaveTransactionType::Accrual]);
            $credit = bcsub($target, $accrued, 2);

            if ($policy->max_balance_days !== null) {
                $room = bcsub((string) $policy->max_balance_days, $this->ledger->sum($employee->id, $type->id, $year), 2);
                $credit = bccomp($room, $credit, 2) < 0 ? $room : $credit;
            }

            if (bccomp($credit, '0', 2) <= 0) {
                return null;
            }

            return $this->ledger->record($employee, $type, $year, LeaveTransactionType::Accrual, $credit, $asOf->format('Y-m-d'), [
                'leave_policy_id' => $policy->id,
                'reference' => "accrual:{$employee->id}:{$type->id}:{$year}:".$asOf->format('Ymd'),
            ]);
        });
    }

    /**
     * Expire the unused part of carry-over credits whose expiry date has passed.
     *
     * @return int rows written
     */
    public function expireCarryOver(CarbonImmutable $asOf): int
    {
        $written = 0;
        $credits = LeaveBalanceTransaction::query()->withoutGlobalScopes()
            ->where('type', LeaveTransactionType::CarryOver)->where('amount', '>', 0)
            ->whereNotNull('expires_on')->whereDate('expires_on', '<', $asOf->format('Y-m-d'))
            ->orderBy('id')->get();

        foreach ($credits as $credit) {
            $reference = 'expiry:carry:'.$credit->id;

            if ($this->ledger->hasReference($reference)) {
                continue;
            }

            $used = LeaveRequestDay::query()
                ->where('employee_id', $credit->employee_id)->where('leave_year', $credit->leave_year)->where('is_counted', true)
                ->whereDate('date', '<=', $credit->expires_on->format('Y-m-d'))
                ->whereHas('request', fn (Builder $query) => $query->withoutGlobalScopes()->where('leave_type_id', $credit->leave_type_id)
                    ->whereIn('status', array_map(fn (LeaveRequestStatus $status): string => $status->value, LeaveRequestStatus::taken())))
                ->sum('amount');
            $unused = bcsub((string) $credit->amount, bcadd((string) $used, '0', 2), 2);
            $balance = $this->ledger->sum($credit->employee_id, $credit->leave_type_id, $credit->leave_year);
            $expire = bccomp($unused, $balance, 2) > 0 ? $balance : $unused;

            if (bccomp($expire, '0', 2) <= 0) {
                continue;
            }

            $row = $this->ledger->record($credit->employee_id, $credit->leave_type_id, $credit->leave_year, LeaveTransactionType::Expiry,
                bcmul($expire, '-1', 2), $credit->expires_on->addDay()->format('Y-m-d'), [
                    'reference' => $reference,
                ]);
            $written += $row ? 1 : 0;
        }

        return $written;
    }
}
