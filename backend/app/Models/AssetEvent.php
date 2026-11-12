<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\AssetEventType;
use Database\Factories\AssetEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry of an asset's history (and, through employee_id, of an
 * employee's custody history). Append-only.
 */
#[Fillable(['asset_id', 'asset_assignment_id', 'employee_id', 'type', 'condition', 'note', 'occurred_on', 'created_by'])]
class AssetEvent extends Model
{
    /** @use HasFactory<AssetEventFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AssetEventType::class,
            'condition' => AssetCondition::class,
            'occurred_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return BelongsTo<AssetAssignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(AssetAssignment::class, 'asset_assignment_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
