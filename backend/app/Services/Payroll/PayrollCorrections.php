<?php

namespace App\Services\Payroll;

use App\Enums\PayBasis;
use App\Enums\PayCalculationType;
use App\Enums\PayComponentCategory;
use App\Enums\PayComponentKind;
use App\Enums\PayrollAdjustmentSource;
use App\Enums\PayrollAdjustmentStatus;
use App\Enums\PayrollRunStatus;
use App\Enums\PayRounding;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollItem;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Leave\LeavePayrollFeed;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Post-finalisation corrections (spec module 9 F "adjustment after
 * finalization through controlled correction / reversal workflows").
 * A finalised run is never edited; differences become payroll_adjustments
 * that a later run (or a correction run) pays or recovers:
 *
 * - scan(): compares the attendance / leave quantities stored with each
 *   finalised item (per salary segment) with what attendance (locked-day
 *   adjustments) and LeavePayrollFeed (e.g. a cancellation approved after
 *   finalisation) say now, and turns each difference into an adjustment priced
 *   with the item's own frozen rates and rules. Encashment rows booked into an
 *   already paid period are picked up the same way. Idempotent: a difference
 *   that already has an adjustment (pending, applied or dismissed) is not
 *   created again (fingerprint). Monthly pay basis for attendance / leave
 *   quantities; overtime for every basis; daily / hourly basic changes are
 *   corrected manually.
 * - manual(): an administrator's adjustment (reason required).
 * - dismiss(): decided not to correct (reason required).
 */
class PayrollCorrections
{
    /**
     * Quantity keys compared, with the component calculation type that prices them.
     */
    private const QUANTITIES = [
        'late_minutes' => PayCalculationType::LateMinutes,
        'early_leave_minutes' => PayCalculationType::EarlyLeaveMinutes,
        'absent_days' => PayCalculationType::AbsentDays,
        'unpaid_leave_days' => PayCalculationType::UnpaidLeaveDays,
    ];

    public function __construct(
        private readonly AttendanceQuantities $attendance,
        private readonly LeavePayrollFeed $leave,
        private readonly PayrollReferences $references,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Scan finalised items of the last $months months (optionally only some employees). Returns the created adjustments count.
     *
     * @param  list<int>|null  $employeeIds
     */
    public function scan(?User $user = null, ?array $employeeIds = null, int $months = 6): int
    {
        $created = 0;

        PayrollItem::query()->withoutGlobalScopes()
            ->with(['run', 'period', 'employee' => fn ($query) => $query->withoutGlobalScopes()])
            ->where('pay_slot', PayrollItem::PAY_SLOT)
            ->whereHas('run', fn ($query) => $query->withoutGlobalScopes()->where('status', PayrollRunStatus::Finalised))
            ->whereHas('period', fn ($query) => $query->where('end_date', '>=', now()->subMonths($months)->toDateString()))
            ->when($employeeIds !== null, fn ($query) => $query->whereIn('employee_id', $employeeIds))
            ->orderBy('id')
            ->chunkById(100, function ($items) use ($user, &$created): void {
                foreach ($items as $item) {
                    $created += $this->scanItem($item, $user);
                }
            });

        if ($created > 0) {
            $this->audit->log('payroll.corrections_scanned', null, meta: ['created' => $created, 'employees' => $employeeIds === null ? 'all' : count($employeeIds)], actor: $user);
        }

        return $created;
    }

    /**
     * @param  array{employee_id: int, salary_component_id?: ?int, kind?: ?string, amount: string, reason: string, original_period_id?: ?int}  $data
     */
    public function manual(Employee $employee, array $data, User $user): PayrollAdjustment
    {
        $component = filled($data['salary_component_id'] ?? null) ? SalaryComponent::query()->findOrFail($data['salary_component_id']) : null;
        $kind = $component?->kind ?? PayComponentKind::from((string) ($data['kind'] ?? PayComponentKind::Earning->value));

        if ($kind === PayComponentKind::EmployerContribution) {
            throw ValidationException::withMessages(['salary_component_id' => __('payroll_runs.validation.adjustment_kind')]);
        }

        $amount = bcadd((string) $data['amount'], '0', 2);

        if (bccomp($amount, '0', 2) === 0) {
            throw ValidationException::withMessages(['amount' => __('payroll_runs.validation.adjustment_amount')]);
        }

        return DB::transaction(function () use ($employee, $component, $kind, $amount, $data, $user): PayrollAdjustment {
            $adjustment = PayrollAdjustment::create([
                'reference' => $this->references->next('adjustment'),
                'employee_id' => $employee->id,
                'original_period_id' => $data['original_period_id'] ?? null,
                'source' => PayrollAdjustmentSource::Manual,
                'status' => PayrollAdjustmentStatus::Pending,
                'salary_component_id' => $component?->id,
                'code' => $component?->code ?? ($kind === PayComponentKind::Earning ? 'ADJ_EARN' : 'ADJ_DED'),
                'name_ar' => $component?->name_ar ?? __('payroll_runs.lines.manual_'.$kind->value, [], 'ar'),
                'name_en' => $component?->name_en ?? __('payroll_runs.lines.manual_'.$kind->value, [], 'en'),
                'kind' => $kind,
                'category' => $component?->category ?? PayComponentCategory::Other,
                'amount' => $amount,
                'reason' => $data['reason'],
                'created_by' => $user->id,
            ]);
            $this->audit->log('payroll.adjustment_created', $adjustment, newValues: ['amount' => $amount, 'kind' => $kind->value, 'reason' => $data['reason']], actor: $user);

            return $adjustment;
        });
    }

    public function dismiss(PayrollAdjustment $adjustment, string $reason, User $user): PayrollAdjustment
    {
        return DB::transaction(function () use ($adjustment, $reason, $user): PayrollAdjustment {
            $adjustment = PayrollAdjustment::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($adjustment->id);

            if (! $adjustment->isPending() || $adjustment->payroll_item_id !== null) {
                throw ValidationException::withMessages(['adjustment' => __('payroll_runs.validation.adjustment_reserved')]);
            }

            $adjustment->forceFill(['status' => PayrollAdjustmentStatus::Dismissed, 'dismissed_at' => now(), 'dismiss_reason' => $reason, 'decided_by' => $user->id])->save();
            $this->audit->log('payroll.adjustment_dismissed', $adjustment, meta: ['reason' => $reason], actor: $user);

            return $adjustment;
        });
    }

    /**
     * Differences of one finalised item; returns the number of adjustments created.
     */
    private function scanItem(PayrollItem $item, ?User $user): int
    {
        $inputs = $item->inputs ?? [];
        $lines = collect($inputs['structure']['lines'] ?? []);
        $created = 0;

        foreach ($inputs['contexts'] ?? [] as $index => $context) {
            $segment = $context['segment'];
            $basis = PayBasis::tryFrom((string) ($segment['pay_basis'] ?? ''));
            $current = $this->attendance->between($item->employee_id, $segment['from'], $segment['to']);
            $now = [
                'late_minutes' => $current['late_minutes'],
                'early_leave_minutes' => $current['early_leave_minutes'],
                'absent_days' => $current['absent_days'],
                'unpaid_leave_days' => $this->leave->unpaidDays($item->employee, CarbonImmutable::parse($segment['from']), CarbonImmutable::parse($segment['to'])),
            ];

            if ($basis === PayBasis::Monthly) {
                foreach (self::QUANTITIES as $key => $type) {
                    $rule = $lines->firstWhere('calculation_type', $type->value);

                    if ($rule === null) {
                        continue;
                    }

                    $was = (string) ($context['quantities'][$key] ?? '0');
                    $unit = $rule['amount'] ?? PayrollMath::mul(in_array($key, ['late_minutes', 'early_leave_minutes'], true) ? $context['minute_rate'] : $context['day_rate'], (string) ($rule['rate'] ?? '1'));
                    $created += $this->record($item, $index, $key, $rule, $was, (string) $now[$key], $unit, $key === 'unpaid_leave_days' ? PayrollAdjustmentSource::Leave : PayrollAdjustmentSource::Attendance, $user);
                }
            }

            $rule = $lines->firstWhere('calculation_type', PayCalculationType::OvertimeHours->value);

            if ($rule !== null) {
                $was = collect($context['overtime'] ?? [])->groupBy('multiplier')->map(fn ($rows): string => $rows->reduce(fn (string $sum, array $row): string => bcadd($sum, (string) $row['hours'], 4), '0'));
                $isNow = collect($current['overtime'])->groupBy('multiplier')->map(fn ($rows): string => $rows->reduce(fn (string $sum, array $row): string => bcadd($sum, (string) $row['hours'], 4), '0'));

                foreach ($was->keys()->merge($isNow->keys())->unique() as $multiplier) {
                    $applied = ($rule['use_policy_multiplier'] ?? false) ? (string) $multiplier : (string) ($rule['rate'] ?? setting('payroll.overtime_rate_multiplier'));
                    $unit = $rule['amount'] ?? PayrollMath::mul($context['hour_rate'], $applied);
                    $created += $this->record($item, $index, 'overtime_'.$multiplier, $rule, $was->get($multiplier, '0'), $isNow->get($multiplier, '0'), $unit, PayrollAdjustmentSource::Attendance, $user);
                }
            }
        }

        return $created + $this->scanEncashments($item, $user);
    }

    /**
     * @param  array<string, mixed>  $rule  structure line of the item snapshot
     */
    private function record(PayrollItem $item, int $segment, string $key, array $rule, string $was, string $now, string $unit, PayrollAdjustmentSource $source, ?User $user): int
    {
        $already = PayrollAdjustment::query()->withoutGlobalScopes()->where('original_item_id', $item->id)
            ->where('details->key', $key)->where('details->segment', $segment)->get()
            ->reduce(fn (string $sum, PayrollAdjustment $row): string => bcadd($sum, (string) $row->quantity_delta, 4), '0');
        $delta = bcsub(bcsub($now, $was, 4), $already, 4);

        if (bccomp($delta, '0', 4) === 0) {
            return 0;
        }

        $amount = PayrollMath::round(PayrollMath::mul($delta, $unit), (int) ($rule['precision'] ?? 2), PayRounding::from((string) ($rule['rounding'] ?? 'half_up')));

        if (bccomp($amount, '0', 2) === 0) {
            return 0;
        }

        $fingerprint = hash('sha256', $item->id.'|'.$segment.'|'.$key.'|'.bcadd($now, '0', 4));

        if (PayrollAdjustment::query()->withoutGlobalScopes()->where('fingerprint', $fingerprint)->exists()) {
            return 0;
        }

        $adjustment = PayrollAdjustment::create([
            'reference' => $this->references->next('adjustment'),
            'employee_id' => $item->employee_id,
            'original_item_id' => $item->id,
            'original_period_id' => $item->payroll_period_id,
            'source' => $source,
            'status' => PayrollAdjustmentStatus::Pending,
            'salary_component_id' => $rule['component_id'] ?? null,
            'code' => $rule['code'],
            'name_ar' => $rule['name_ar'],
            'name_en' => $rule['name_en'],
            'kind' => $rule['kind'],
            'category' => $rule['category'],
            'quantity_delta' => $delta,
            'rate' => bcadd($unit, '0', 6),
            'amount' => $amount,
            'reason' => __('payroll_runs.corrections.detected_reason', [
                'quantity' => __('payroll_runs.corrections.quantities.'.(str_starts_with($key, 'overtime_') ? 'overtime' : $key), [], (string) setting('localisation.default_locale')),
                'was' => PayrollMath::trim($was), 'now' => PayrollMath::trim($now), 'period' => $item->period->code,
                'rate' => Money::format(PayrollMath::round($unit, 2)),
            ], (string) setting('localisation.default_locale')),
            'fingerprint' => $fingerprint,
            'details' => ['key' => $key, 'segment' => $segment, 'was' => $was, 'now' => $now],
            'created_by' => $user?->id,
        ]);
        $this->audit->log('payroll.adjustment_detected', $adjustment, newValues: ['amount' => $amount, 'key' => $key, 'delta' => $delta], actor: $user);

        return 1;
    }

    private function scanEncashments(PayrollItem $item, ?User $user): int
    {
        $inputs = $item->inputs ?? [];
        $known = collect($item->quantities['encashments'] ?? [])->pluck('transaction_id')->all();
        $rows = $this->leave->encashments($item->employee, $item->period->start_date->toImmutable(), $item->period->end_date->toImmutable())
            ->reject(fn (array $row): bool => in_array($row['transaction_id'], $known, true));
        $component = SalaryComponent::query()->where('code', (string) setting('payroll.leave_encashment_component'))->first();
        $context = collect($inputs['contexts'] ?? [])->last();

        if ($rows->isEmpty() || $component === null || $context === null) {
            return 0;
        }

        $created = 0;

        foreach ($rows as $row) {
            $fingerprint = hash('sha256', $item->id.'|encashment|'.$row['transaction_id']);

            if (PayrollAdjustment::query()->withoutGlobalScopes()->where('fingerprint', $fingerprint)->exists()) {
                continue;
            }

            $amount = PayrollMath::round(PayrollMath::mul($row['days'], (string) $context['day_rate']), $component->precision());
            $adjustment = PayrollAdjustment::create([
                'reference' => $this->references->next('adjustment'),
                'employee_id' => $item->employee_id, 'original_item_id' => $item->id, 'original_period_id' => $item->payroll_period_id,
                'source' => PayrollAdjustmentSource::Leave, 'status' => PayrollAdjustmentStatus::Pending, 'salary_component_id' => $component->id,
                'code' => $component->code, 'name_ar' => $component->name_ar, 'name_en' => $component->name_en,
                'kind' => PayComponentKind::Earning, 'category' => $component->category, 'quantity_delta' => $row['days'], 'rate' => (string) $context['day_rate'],
                'amount' => $amount,
                'reason' => __('payroll_runs.corrections.encashment_reason', ['days' => PayrollMath::trim($row['days']), 'period' => $item->period->code], (string) setting('localisation.default_locale')),
                'fingerprint' => $fingerprint, 'details' => ['key' => 'encashment', 'transaction_id' => $row['transaction_id']],
                'created_by' => $user?->id,
            ]);
            $this->audit->log('payroll.adjustment_detected', $adjustment, newValues: ['amount' => $amount, 'key' => 'encashment'], actor: $user);
            $created++;
        }

        return $created;
    }

    /**
     * Pending adjustments not yet carried by a run, for the correction run wizard.
     */
    public function pendingCount(): int
    {
        return PayrollAdjustment::query()->pending()->whereNull('payroll_item_id')->count();
    }
}
