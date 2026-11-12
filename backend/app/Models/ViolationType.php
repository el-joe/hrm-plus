<?php

namespace App\Models;

use App\Enums\DisciplinaryActionType;
use App\Enums\ViolationSeverity;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use Database\Factories\ViolationTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catalogue of disciplinary violations (bilingual): severity and the action
 * suggested for a first offence (App\Services\Disciplinary\WarningEscalation).
 * Types in use are deactivated, never deleted.
 */
#[Fillable(['code', 'name_ar', 'name_en', 'description', 'severity', 'default_action', 'is_active'])]
class ViolationType extends Model
{
    /** @use HasFactory<ViolationTypeFactory> */
    use Auditable, HasActiveFlag, HasFactory, HasLocalizedName;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severity' => ViolationSeverity::class,
            'default_action' => DisciplinaryActionType::class,
        ];
    }

    /**
     * @return HasMany<DisciplinaryCase, $this>
     */
    public function cases(): HasMany
    {
        return $this->hasMany(DisciplinaryCase::class);
    }
}
