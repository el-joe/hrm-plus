<?php

namespace App\Models;

use App\Enums\PayComponentKind;
use Database\Factories\PayEntryBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Entries created together: a bulk assignment (one entry per selected
 * employee) or a spreadsheet import.
 */
#[Fillable(['source', 'kind', 'entries_count', 'file_name', 'note', 'created_by'])]
class PayEntryBatch extends Model
{
    /** @use HasFactory<PayEntryBatchFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => PayComponentKind::class, 'entries_count' => 'integer'];
    }

    /**
     * @return HasMany<PayEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(PayEntry::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
