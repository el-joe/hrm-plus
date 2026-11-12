<?php

namespace App\Models;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollPeriodType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasLocalizedName;
use Database\Factories\PayrollPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A payroll period (a month or custom dates) with its pay date and
 * attendance cut-off. Runs belong to a period; a closed period accepts no
 * new run. See App\Actions\Payroll\ManagePayrollPeriod.
 */
#[Fillable(['code', 'name_ar', 'name_en', 'type', 'start_date', 'end_date', 'pay_date', 'cutoff_date', 'status', 'notes', 'created_by'])]
class PayrollPeriod extends Model
{
    /** @use HasFactory<PayrollPeriodFactory> */
    use Auditable, HasFactory, HasLocalizedName;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PayrollPeriodType::class,
            'status' => PayrollPeriodStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'pay_date' => 'date',
            'cutoff_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    protected function hasCodeColumn(): bool
    {
        return true;
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', PayrollPeriodStatus::Open);
    }

    /**
     * Periods that contain the date.
     *
     * @param  Builder<static>  $query
     */
    public function scopeCovering(Builder $query, string $date): void
    {
        $query->where('start_date', '<=', $date)->where('end_date', '>=', $date);
    }

    public function isOpen(): bool
    {
        return $this->status === PayrollPeriodStatus::Open;
    }

    /**
     * Pay periods per year used to annualise income tax: 12 for monthly periods,
     * otherwise how many periods of this length fit in a year.
     */
    public function periodsPerYear(): int
    {
        if ($this->type === PayrollPeriodType::Monthly) {
            return 12;
        }

        $days = (int) $this->start_date->diffInDays($this->end_date) + 1;

        return max(1, intdiv(365, max(1, $days)));
    }

    /**
     * "2026-10-01 → 2026-10-31".
     */
    public function rangeLabel(): string
    {
        return $this->start_date->toDateString().' → '.$this->end_date->toDateString();
    }

    /**
     * @return HasMany<PayrollRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
