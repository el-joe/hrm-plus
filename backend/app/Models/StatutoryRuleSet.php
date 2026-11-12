<?php

namespace App\Models;

use App\Enums\StatutoryType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use App\Models\Contracts\GuardsDeletion;
use Carbon\CarbonInterface;
use Database\Factories\StatutoryRuleSetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A statutory rule table (income tax or social insurance) of one country.
 * Its rates live in effective-dated versions; nothing is hard-coded.
 * is_demo marks illustrative values ("DEMO — not legal advice").
 */
#[Fillable(['code', 'name_ar', 'name_en', 'country_code', 'type', 'is_demo', 'description', 'is_active'])]
class StatutoryRuleSet extends Model implements GuardsDeletion
{
    /** @use HasFactory<StatutoryRuleSetFactory> */
    use Auditable, HasActiveFlag, HasFactory, HasLocalizedName;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['type' => StatutoryType::class, 'is_demo' => 'boolean'];
    }

    /**
     * @return HasMany<StatutoryRuleVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(StatutoryRuleVersion::class)->orderByDesc('version');
    }

    public function versionOn(CarbonInterface|string $date): ?StatutoryRuleVersion
    {
        $day = $date instanceof CarbonInterface ? $date->format('Y-m-d') : $date;

        return $this->versions()->reorder()->with('brackets')
            ->where('effective_from', '<=', $day)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $day))
            ->orderByDesc('effective_from')->orderByDesc('version')
            ->first();
    }

    /**
     * @return array<string, int>
     */
    public function usageCounts(): array
    {
        return ['statutory_rule_versions' => $this->versions()->count()];
    }
}
