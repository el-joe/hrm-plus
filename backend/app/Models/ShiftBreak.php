<?php

namespace App\Models;

use App\Enums\BreakType;
use Database\Factories\ShiftBreakFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A break of a shift: fixed (start/end times) or flexible (duration only), paid or unpaid.
 */
#[Fillable(['name', 'type', 'start_time', 'end_time', 'duration_minutes', 'is_paid', 'sort_order'])]
class ShiftBreak extends Model
{
    /** @use HasFactory<ShiftBreakFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BreakType::class,
            'duration_minutes' => 'integer',
            'is_paid' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
