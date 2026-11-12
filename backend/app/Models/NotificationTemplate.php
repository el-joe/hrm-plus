<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The text of one notification event for one channel and language (Phase 8b).
 * Edited through App\Services\Notifications\NotificationTemplates (placeholder whitelist enforced there).
 */
#[Fillable(['event', 'channel', 'locale', 'subject', 'body', 'is_customised', 'updated_by'])]
class NotificationTemplate extends Model
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_customised' => 'boolean'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }
}
