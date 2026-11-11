<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use App\Models\Contracts\GuardsDeletion;
use Database\Factories\JobTitleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name_ar', 'name_en', 'description', 'is_active'])]
class JobTitle extends Model implements GuardsDeletion
{
    /** @use HasFactory<JobTitleFactory> */
    use Auditable, HasActiveFlag, HasFactory, HasLocalizedName;

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
            'employees' => Employee::query()->withoutGlobalScopes()->where('job_title_id', $this->getKey())->count(),
        ];
    }
}
