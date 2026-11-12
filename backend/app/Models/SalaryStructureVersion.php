<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\SalaryStructureVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One effective-dated version of a salary structure with its lines. Saved
 * once and never edited afterwards (App\Actions\Payroll\SaveSalaryStructureVersion);
 * payroll stores the version id it calculated with.
 */
#[Fillable(['salary_structure_id', 'version', 'effective_from', 'effective_to', 'notes', 'created_by'])]
class SalaryStructureVersion extends Model
{
    /** @use HasFactory<SalaryStructureVersionFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * @return BelongsTo<SalaryStructure, $this>
     */
    public function structure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id');
    }

    /**
     * @return HasMany<SalaryStructureLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SalaryStructureLine::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
