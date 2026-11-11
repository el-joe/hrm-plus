<?php

namespace App\Models;

use App\Enums\PayComponentCategory;
use App\Enums\PayComponentKind;
use App\Enums\PayrollLineSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One line of an employee's payroll result: an earning, a deduction or an
 * employer contribution, with where it came from (source + sourceable) and
 * how it was obtained (explanation, rendered in the viewer's language).
 * Amounts are signed DECIMAL strings; lines of a finalised run never change.
 */
class PayrollItemLine extends Model
{
    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PayComponentKind::class,
            'category' => PayComponentCategory::class,
            'source' => PayrollLineSource::class,
            'quantity' => 'decimal:4',
            'rate' => 'decimal:6',
            'amount' => 'decimal:2',
            'is_taxable' => 'boolean',
            'is_insurable' => 'boolean',
            'show_on_payslip' => 'boolean',
            'explanation' => 'array',
            'display_order' => 'integer',
        ];
    }

    public function name(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->name_ar : $this->name_en;
    }

    /**
     * The explanation in the given language: "12 late minutes × 2.50 = 30.00".
     */
    public function explain(?string $locale = null): string
    {
        return implode(' · ', $this->explanationLines($locale));
    }

    /**
     * One sentence per explanation part. Parameters stored in both languages ({ar, en}) are
     * resolved to the requested one.
     *
     * @return list<string>
     */
    public function explanationLines(?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        return array_map(fn (array $part): string => __('payroll_runs.explain.'.$part['key'], array_map(
            fn (mixed $value): string => is_array($value) ? (string) ($value[$locale] ?? reset($value)) : (string) $value,
            $part['params'] ?? [],
        ), $locale), $this->explanation ?? []);
    }

    /**
     * @return BelongsTo<PayrollItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class, 'payroll_item_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }
}
