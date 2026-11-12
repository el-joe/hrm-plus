<?php

namespace App\Models;

use Database\Factories\DashboardPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin's dashboard layout: widget order and hidden widgets (Phase 8c).
 *
 * @property list<string>|null $order
 * @property list<string>|null $hidden
 */
#[Fillable(['user_id', 'order', 'hidden'])]
class DashboardPreference extends Model
{
    /** @use HasFactory<DashboardPreferenceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'array',
            'hidden' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
