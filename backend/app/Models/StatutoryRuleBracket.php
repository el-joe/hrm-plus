<?php

namespace App\Models;

use Database\Factories\StatutoryRuleBracketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An income-tax bracket: rate % applied to the part of annual taxable income
 * between lower_bound and upper_bound (null = no limit).
 */
#[Fillable(['statutory_rule_version_id', 'lower_bound', 'upper_bound', 'rate', 'sort_order'])]
class StatutoryRuleBracket extends Model
{
    /** @use HasFactory<StatutoryRuleBracketFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['lower_bound' => 'decimal:2', 'upper_bound' => 'decimal:2', 'rate' => 'decimal:4', 'sort_order' => 'integer'];
    }

    /**
     * @return BelongsTo<StatutoryRuleVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(StatutoryRuleVersion::class, 'statutory_rule_version_id');
    }
}
