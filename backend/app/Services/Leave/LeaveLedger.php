<?php

namespace App\Services\Leave;

use App\Enums\LeaveTransactionType;
use App\Models\Employee;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The only writer of leave_balance_transactions. Rows are append-only;
 * automatic rows carry a unique reference so a rerun never books twice.
 */
class LeaveLedger
{
    /**
     * @param  array{expires_on?: ?string, leave_request_id?: ?int, leave_policy_id?: ?int, reference?: ?string, reason?: ?string}  $extra
     */
    public function record(
        Employee|int $employee,
        LeaveType|int $type,
        int $year,
        LeaveTransactionType $kind,
        string $amount,
        string $effectiveDate,
        array $extra = [],
        ?User $user = null,
    ): ?LeaveBalanceTransaction {
        $reference = $extra['reference'] ?? null;

        if ($reference !== null && LeaveBalanceTransaction::query()->withoutGlobalScopes()->where('reference', $reference)->exists()) {
            return null;
        }

        if (bccomp($amount, '0', 2) === 0) {
            return null;
        }

        return LeaveBalanceTransaction::create([
            'employee_id' => $employee instanceof Employee ? $employee->id : $employee,
            'leave_type_id' => $type instanceof LeaveType ? $type->id : $type,
            'leave_year' => $year,
            'type' => $kind,
            'amount' => bcadd($amount, '0', 2),
            'effective_date' => $effectiveDate,
            'expires_on' => $extra['expires_on'] ?? null,
            'leave_request_id' => $extra['leave_request_id'] ?? null,
            'leave_policy_id' => $extra['leave_policy_id'] ?? null,
            'reference' => $reference,
            'reason' => $extra['reason'] ?? null,
            'created_by' => $user?->id,
        ]);
    }

    public function hasReference(string $reference): bool
    {
        return LeaveBalanceTransaction::query()->withoutGlobalScopes()->where('reference', $reference)->exists();
    }

    /**
     * Sum of the rows (all kinds, or the given kinds) of an employee, type and year.
     *
     * @param  list<LeaveTransactionType>  $kinds
     */
    public function sum(int $employeeId, int $typeId, int $year, array $kinds = []): string
    {
        $sum = $this->rows($employeeId, $typeId, $year)
            ->when($kinds !== [], fn (Builder $query) => $query->whereIn('type', array_map(fn (LeaveTransactionType $kind): string => $kind->value, $kinds)))
            ->sum('amount');

        return bcadd((string) $sum, '0', 2);
    }

    /**
     * Sums per kind: kind value => signed sum.
     *
     * @return array<string, string>
     */
    public function sumsByKind(int $employeeId, int $typeId, int $year): array
    {
        return $this->rows($employeeId, $typeId, $year)
            ->selectRaw('type, SUM(amount) as total')->groupBy('type')
            ->pluck('total', 'type')
            ->map(fn ($total): string => bcadd((string) $total, '0', 2))
            ->all();
    }

    /**
     * @return Builder<LeaveBalanceTransaction>
     */
    public function rows(int $employeeId, int $typeId, int $year): Builder
    {
        return LeaveBalanceTransaction::query()->withoutGlobalScopes()
            ->where('employee_id', $employeeId)->where('leave_type_id', $typeId)->where('leave_year', $year);
    }
}
