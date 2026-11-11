<?php

namespace App\Services\Leave;

use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveTransactionType;
use App\Models\Employee;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveRequestDay;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * What payroll (Phase 7) reads from leave. Data scopes are ignored: payroll
 * runs decide their own population.
 *
 *     $feed->unpaidDays($employee, $from, $to);      // "2.50" — days to deduct
 *     $feed->unpaidBreakdown($employee, $from, $to); // per date and leave type
 *     $feed->encashments($employee, $from, $to);     // encashed days to pay
 *
 * Unpaid days are the counted days, dated inside the period, of taken
 * requests (approved, or cancellation pending) of leave types flagged
 * "affects payroll". Amounts are days (half days 0.5, hours as a fraction
 * of the scheduled day); payroll converts them to money with its own day
 * rate. A cancellation approved after a period was finalised is a
 * correction for payroll to pick up (the days disappear from this feed).
 */
class LeavePayrollFeed
{
    /**
     * Total unpaid leave days in the period (bcmath string).
     */
    public function unpaidDays(Employee $employee, CarbonInterface $from, CarbonInterface $to): string
    {
        $sum = $this->unpaidQuery($employee, $from, $to)->sum('amount');

        return bcadd((string) $sum, '0', 2);
    }

    /**
     * @return Collection<int, array{date: string, leave_request_id: int, leave_type: string, amount: string, day_part: string}>
     */
    public function unpaidBreakdown(Employee $employee, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->unpaidQuery($employee, $from, $to)
            ->with(['request' => fn ($query) => $query->withoutGlobalScopes()->with('leaveType')])
            ->orderBy('date')->get()
            ->map(fn (LeaveRequestDay $day): array => [
                'date' => $day->date->format('Y-m-d'),
                'leave_request_id' => $day->leave_request_id,
                'leave_type' => $day->request->leaveType->code,
                'amount' => bcadd((string) $day->amount, '0', 2),
                'day_part' => $day->day_part->value,
            ]);
    }

    /**
     * Encashment rows booked in the period (amounts as positive days to pay).
     *
     * @return Collection<int, array{transaction_id: int, leave_type: string, days: string, date: string, leave_year: int}>
     */
    public function encashments(Employee $employee, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return LeaveBalanceTransaction::query()->withoutGlobalScopes()->with('leaveType')
            ->where('employee_id', $employee->id)->where('type', LeaveTransactionType::Encashment)
            ->whereBetween('effective_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->orderBy('effective_date')->orderBy('id')->get()
            ->map(fn (LeaveBalanceTransaction $row): array => [
                'transaction_id' => $row->id,
                'leave_type' => $row->leaveType->code,
                'days' => bcmul((string) $row->amount, '-1', 2),
                'date' => $row->effective_date->format('Y-m-d'),
                'leave_year' => $row->leave_year,
            ]);
    }

    /**
     * @return Builder<LeaveRequestDay>
     */
    private function unpaidQuery(Employee $employee, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return LeaveRequestDay::query()
            ->where('employee_id', $employee->id)->where('is_counted', true)
            ->whereBetween('date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->whereHas('request', fn (Builder $query) => $query->withoutGlobalScopes()
                ->whereIn('status', array_map(fn (LeaveRequestStatus $status): string => $status->value, LeaveRequestStatus::taken()))
                ->whereHas('leaveType', fn (Builder $type) => $type->where('affects_payroll', true)));
    }
}
