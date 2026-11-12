<?php

namespace App\Models;

use App\Enums\PayComponentCategory;
use App\Enums\PayComponentKind;
use App\Enums\PayrollAdjustmentSource;
use App\Enums\PayrollAdjustmentStatus;
use App\Models\Concerns\ScopedThroughEmployee;
use Database\Factories\PayrollAdjustmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A correction of an already finalised period: detected from attendance
 * adjustments / leave changes after finalisation, or entered by an
 * administrator. A finalised run is never edited: the adjustment is paid or
 * recovered as a "correction" line by a run of a later period (or a
 * correction run) and becomes "applied" when that run is finalised.
 */
class PayrollAdjustment extends Model
{
    /** @use HasFactory<PayrollAdjustmentFactory> */
    use HasFactory, ScopedThroughEmployee;

    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => PayrollAdjustmentSource::class,
            'status' => PayrollAdjustmentStatus::class,
            'kind' => PayComponentKind::class,
            'category' => PayComponentCategory::class,
            'quantity_delta' => 'decimal:4',
            'rate' => 'decimal:6',
            'amount' => 'decimal:2',
            'details' => 'array',
            'applied_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), PayrollAdjustmentStatus::Pending);
    }

    public function isPending(): bool
    {
        return $this->status === PayrollAdjustmentStatus::Pending;
    }

    public function name(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->name_ar : $this->name_en;
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<PayrollItem, $this>
     */
    public function originalItem(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class, 'original_item_id');
    }

    /**
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function originalPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'original_period_id');
    }

    /**
     * @return BelongsTo<PayrollItem, $this>
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class, 'payroll_item_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
