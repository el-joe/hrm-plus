<?php

namespace App\Models;

use App\Enums\AnnouncementTargetType;
use App\Models\Concerns\Auditable;
use Database\Factories\AnnouncementTargetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One audience target of an announcement (branch, department incl. sub-departments, employee group or
 * employment type).
 */
#[Fillable(['announcement_id', 'target_type', 'target_id'])]
class AnnouncementTarget extends Model
{
    /** @use HasFactory<AnnouncementTargetFactory> */
    use Auditable, HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['target_type' => AnnouncementTargetType::class];
    }

    /**
     * @return BelongsTo<Announcement, $this>
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }
}
