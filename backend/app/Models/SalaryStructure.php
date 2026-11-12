<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use App\Models\Contracts\GuardsDeletion;
use Carbon\CarbonInterface;
use Database\Factories\SalaryStructureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A salary structure: a named template of salary components. Its content
 * lives in effective-dated versions (a change is a new version, history is
 * never overwritten); assignments say who it applies to and when.
 */
#[Fillable(['code', 'name_ar', 'name_en', 'description', 'is_active'])]
class SalaryStructure extends Model implements GuardsDeletion
{
    /** @use HasFactory<SalaryStructureFactory> */
    use Auditable, HasActiveFlag, HasFactory, HasLocalizedName;

    /**
     * @return HasMany<SalaryStructureVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(SalaryStructureVersion::class)->orderByDesc('version');
    }

    /**
     * @return HasMany<SalaryStructureAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(SalaryStructureAssignment::class);
    }

    /**
     * The version in force on a date (Y-m-d or date).
     */
    public function versionOn(CarbonInterface|string $date): ?SalaryStructureVersion
    {
        $day = $date instanceof CarbonInterface ? $date->format('Y-m-d') : $date;

        return $this->versions()->reorder()
            ->where('effective_from', '<=', $day)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $day))
            ->orderByDesc('effective_from')->orderByDesc('version')
            ->first();
    }

    public function latestVersion(): ?SalaryStructureVersion
    {
        return $this->versions()->first();
    }

    /**
     * @return array<string, int>
     */
    public function usageCounts(): array
    {
        return ['salary_structure_assignments' => $this->assignments()->count()];
    }
}
