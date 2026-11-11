<?php

namespace App\Actions\Payroll;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollPeriodType;
use App\Enums\PayrollRunStatus;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payroll periods: monthly periods follow setting payroll.period_start_day
 * (day 1 = calendar month; day 26 = 26th of the previous month to the 25th),
 * custom periods take any dates. Monthly periods never overlap. Pay date and
 * attendance cut-off default to the period end. Closing a period refuses new
 * runs; a period with a run still in progress cannot be closed.
 */
class ManagePayrollPeriod
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * The monthly period of a month ("2026-10"), created when missing.
     */
    public function monthly(string $month, ?User $user = null, ?string $payDate = null, ?string $cutoffDate = null): PayrollPeriod
    {
        [$start, $end] = $this->monthlyRange($month);
        $existing = PayrollPeriod::query()->where('type', PayrollPeriodType::Monthly)->where('start_date', $start->toDateString())->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->store([
            'code' => $month,
            'type' => PayrollPeriodType::Monthly->value,
            'name_ar' => __('payroll_runs.periods.monthly_name', ['month' => CarbonImmutable::parse($month.'-01')->locale('ar')->translatedFormat('F Y')], 'ar'),
            'name_en' => __('payroll_runs.periods.monthly_name', ['month' => CarbonImmutable::parse($month.'-01')->locale('en')->translatedFormat('F Y')], 'en'),
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'pay_date' => $payDate ?? $end->toDateString(),
            'cutoff_date' => $cutoffDate ?? $end->toDateString(),
        ], $user);
    }

    /**
     * The monthly period containing a date (created when missing).
     */
    public function forDate(string $date, ?User $user = null): PayrollPeriod
    {
        $day = CarbonImmutable::parse($date);
        $startDay = (int) setting('payroll.period_start_day');
        $month = $startDay > 1 && $day->day >= $startDay ? $day->addMonthNoOverflow() : $day;

        return $this->monthly($month->format('Y-m'), $user);
    }

    /**
     * @param  array{code: string, type: string, name_ar: string, name_en: string, start_date: string, end_date: string, pay_date: string, cutoff_date?: ?string, notes?: ?string}  $data
     */
    public function store(array $data, ?User $user): PayrollPeriod
    {
        $start = CarbonImmutable::parse($data['start_date']);
        $end = CarbonImmutable::parse($data['end_date']);
        $type = PayrollPeriodType::from($data['type']);

        if ($end->lessThan($start)) {
            throw ValidationException::withMessages(['end_date' => __('payroll_runs.validation.period_dates')]);
        }

        if (CarbonImmutable::parse($data['pay_date'])->lessThan($start)) {
            throw ValidationException::withMessages(['pay_date' => __('payroll_runs.validation.pay_date')]);
        }

        if (filled($data['cutoff_date'] ?? null) && CarbonImmutable::parse($data['cutoff_date'])->lessThan($start)) {
            throw ValidationException::withMessages(['cutoff_date' => __('payroll_runs.validation.cutoff_date')]);
        }

        return DB::transaction(function () use ($data, $start, $end, $type, $user): PayrollPeriod {
            if ($type === PayrollPeriodType::Monthly) {
                $overlap = PayrollPeriod::query()->where('type', PayrollPeriodType::Monthly)
                    ->where('start_date', '<=', $end->toDateString())->where('end_date', '>=', $start->toDateString())->lockForUpdate()->exists();

                if ($overlap) {
                    throw ValidationException::withMessages(['start_date' => __('payroll_runs.validation.period_overlap')]);
                }
            }

            if (PayrollPeriod::query()->where('code', $data['code'])->exists()) {
                throw ValidationException::withMessages(['code' => __('payroll_runs.validation.period_code')]);
            }

            $period = PayrollPeriod::create([
                'code' => $data['code'], 'name_ar' => $data['name_ar'], 'name_en' => $data['name_en'], 'type' => $type,
                'start_date' => $start->toDateString(), 'end_date' => $end->toDateString(), 'pay_date' => $data['pay_date'],
                'cutoff_date' => $data['cutoff_date'] ?? null, 'status' => PayrollPeriodStatus::Open, 'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
            ]);
            $this->audit->log('payroll.period_created', $period, meta: ['code' => $period->code, 'type' => $type->value], actor: $user);

            return $period;
        });
    }

    /**
     * @param  array{pay_date: string, cutoff_date?: ?string, notes?: ?string}  $data
     */
    public function update(PayrollPeriod $period, array $data, User $user): PayrollPeriod
    {
        if (CarbonImmutable::parse($data['pay_date'])->lessThan($period->start_date)) {
            throw ValidationException::withMessages(['pay_date' => __('payroll_runs.validation.pay_date')]);
        }

        $period->update(['pay_date' => $data['pay_date'], 'cutoff_date' => $data['cutoff_date'] ?? null, 'notes' => $data['notes'] ?? null]);

        return $period;
    }

    public function close(PayrollPeriod $period, User $user): PayrollPeriod
    {
        return DB::transaction(function () use ($period, $user): PayrollPeriod {
            $period = PayrollPeriod::query()->lockForUpdate()->findOrFail($period->id);
            $busy = $period->runs()->withoutGlobalScopes()->whereIn('status', [
                PayrollRunStatus::Draft, PayrollRunStatus::Calculating, PayrollRunStatus::Calculated, PayrollRunStatus::PendingApproval, PayrollRunStatus::Approved,
            ])->exists();

            if ($busy) {
                throw ValidationException::withMessages(['period' => __('payroll_runs.validation.period_busy')]);
            }

            $period->forceFill(['status' => PayrollPeriodStatus::Closed, 'closed_at' => now(), 'closed_by' => $user->id])->save();
            $this->audit->log('payroll.period_closed', $period, actor: $user);

            return $period;
        });
    }

    public function reopen(PayrollPeriod $period, User $user): PayrollPeriod
    {
        $period->forceFill(['status' => PayrollPeriodStatus::Open, 'closed_at' => null, 'closed_by' => null])->save();
        $this->audit->log('payroll.period_reopened', $period, actor: $user);

        return $period;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function monthlyRange(string $month): array
    {
        $first = CarbonImmutable::parse($month.'-01')->startOfDay();
        $startDay = (int) setting('payroll.period_start_day');

        if ($startDay <= 1) {
            return [$first, $first->endOfMonth()->startOfDay()];
        }

        $start = $first->subMonthNoOverflow()->setDay(min($startDay, $first->subMonthNoOverflow()->daysInMonth));

        return [$start, $first->setDay(min($startDay - 1, $first->daysInMonth))];
    }
}
