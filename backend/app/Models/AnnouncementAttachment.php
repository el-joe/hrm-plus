<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\AnnouncementAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file attached to an announcement, on the private disk "announcement_files".
 */
#[Fillable(['announcement_id', 'file_path', 'file_name', 'file_mime', 'file_size', 'uploaded_by'])]
class AnnouncementAttachment extends Model
{
    /** @use HasFactory<AnnouncementAttachmentFactory> */
    use Auditable, HasFactory;

    /**
     * @return BelongsTo<Announcement, $this>
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }
}
