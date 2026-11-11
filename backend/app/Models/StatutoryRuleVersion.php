<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One effective-dated version of a statutory rule table: social insurance
 * rates with floor / ceiling, or income-tax brackets with an annual
 * exemption. Saved once, never edited (a change is a new version).
 */
#[Fillable([
    'statutory_rule_set_id', 'version', 'effective_from', 'effective_to', 'employee_rate', 'employer_rate', 'salary_floor',
    'salary_ceiling', 'annual_exemption', 'base_reductions', 'deduct_employee_insurance', 'notes', 'created_by',
])]
class StatutoryRuleVersion extends Model
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'employee_rate' => 'decimal:4',
            'employer_rate' => 'decimal:4',
            'salary_floor' => 'decimal:2',
            'salary_ceiling' => 'decimal:2',
            'annual_exemption' => 'decimal:2',
            'base_reductions' => 'array',
            'deduct_employee_insurance' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<StatutoryRuleSet, $this>
     */
    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(StatutoryRuleSet::class, 'statutory_rule_set_id');
    }

    /**
     * @return HasMany<StatutoryRuleBracket, $this>
     */
    public function brackets(): HasMany
    {
        return $this->hasMany(StatutoryRuleBracket::class)->orderBy('sort_order')->orderBy('lower_bound');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
