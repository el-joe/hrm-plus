<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file on an HR request (private disk "hr_request_files"), streamed only after the ownership / policy check.
 */
#[Fillable(['hr_request_id', 'hr_request_message_id', 'file_path', 'file_name', 'file_mime', 'file_size', 'is_internal', 'is_letter', 'uploaded_by'])]
class HrRequestAttachment extends Model
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_internal' => 'boolean', 'is_letter' => 'boolean', 'file_size' => 'integer'];
    }

    /**
     * @return BelongsTo<HrRequest, $this>
     */
    public function hrRequest(): BelongsTo
    {
        return $this->belongsTo(HrRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }
}
