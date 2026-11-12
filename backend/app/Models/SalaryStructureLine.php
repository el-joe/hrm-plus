<?php

namespace App\Models;

use Database\Factories\SalaryStructureLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A component of a salary structure version, optionally overriding the
 * component's default amount, rate or percentage base.
 */
#[Fillable(['salary_structure_version_id', 'salary_component_id', 'amount', 'rate', 'base_component_id', 'sort_order'])]
class SalaryStructureLine extends Model
{
    /** @use HasFactory<SalaryStructureLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'rate' => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SalaryStructureVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(SalaryStructureVersion::class, 'salary_structure_version_id');
    }

    /**
     * @return BelongsTo<SalaryComponent, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }

    /**
     * @return BelongsTo<SalaryComponent, $this>
     */
    public function baseComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'base_component_id');
    }
}
