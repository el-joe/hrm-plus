<?php

namespace App\Models;

use App\Enums\CompetencyCategory;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use App\Models\Contracts\GuardsDeletion;
use Database\Factories\CompetencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An entry of the competency framework, assessed in cycles that include it.
 */
#[Fillable(['code', 'name_ar', 'name_en', 'description', 'category', 'is_active'])]
class Competency extends Model implements GuardsDeletion
{
    /** @use HasFactory<CompetencyFactory> */
    use Auditable, HasActiveFlag, HasFactory, HasLocalizedName;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['category' => CompetencyCategory::class];
    }

    /**
     * @return array<string, int>
     */
    public function usageCounts(): array
    {
        return [
            'performance_cycles' => PerformanceCycleCompetency::query()->where('competency_id', $this->id)->count(),
            'performance_reviews' => PerformanceReviewCompetency::query()->where('competency_id', $this->id)->count(),
        ];
    }
}
