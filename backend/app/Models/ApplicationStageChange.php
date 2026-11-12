<?php

namespace App\Models;

use Database\Factories\ApplicationStageChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One move of an application between stages (append-only history).
 */
#[Fillable(['job_application_id', 'from_stage_id', 'to_stage_id', 'from_stage_name', 'to_stage_name', 'note', 'changed_by', 'created_at'])]
class ApplicationStageChange extends Model
{
    /** @use HasFactory<ApplicationStageChangeFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
