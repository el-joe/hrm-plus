<?php

namespace Tests\Fixtures;

use App\Models\Concerns\HasDataScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Test-only stand-in for a Phase 3 model (e.g. Employee) that carries
 * branch_id and department_id columns and opts into data scoping.
 */
class ScopedRecord extends Model
{
    use HasDataScope;

    protected $guarded = [];

    public $timestamps = false;

    /**
     * @return HasMany<ScopedRecordNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ScopedRecordNote::class);
    }
}
