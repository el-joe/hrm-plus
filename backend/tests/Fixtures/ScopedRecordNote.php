<?php

namespace Tests\Fixtures;

use App\Models\Concerns\HasDataScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Test-only stand-in for a model that is scoped through its parent
 * (e.g. a leave request scoped by its employee's branch).
 */
class ScopedRecordNote extends Model
{
    use HasDataScope;

    protected $guarded = [];

    public $timestamps = false;

    public function dataScopeMap(): array
    {
        return ['branch' => 'record.branch_id'];
    }

    /**
     * @return BelongsTo<ScopedRecord, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(ScopedRecord::class, 'scoped_record_id');
    }
}
