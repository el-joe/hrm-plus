<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use App\Models\Contracts\GuardsDeletion;
use Database\Factories\JobGradeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A job grade / level with an optional salary band (DECIMAL, company currency).
 */
#[Fillable(['code', 'name_ar', 'name_en', 'level', 'min_salary', 'max_salary', 'is_active'])]
class JobGrade extends Model implements GuardsDeletion
{
    /** @use HasFactory<JobGradeFactory> */
    use Auditable, HasActiveFlag, HasFactory, HasLocalizedName;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'min_salary' => 'decimal:2',
            'max_salary' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<Position, $this>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /**
     * @return array<string, int>
     */
    public function usageCounts(): array
    {
        return [
            'positions' => $this->positions()->count(),
            'employees' => Employee::query()->withoutGlobalScopes()->where('job_grade_id', $this->getKey())->count(),
        ];
    }
}
