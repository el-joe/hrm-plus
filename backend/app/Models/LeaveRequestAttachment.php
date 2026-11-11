<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A supporting file of a leave request (medical certificate, ...), stored on
 * the private employee files disk under "{employee}/leave". Only streamed
 * through authorised download routes.
 */
#[Fillable(['leave_request_id', 'file_path', 'file_name', 'file_mime', 'file_size', 'uploaded_by'])]
class LeaveRequestAttachment extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<LeaveRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_request_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
