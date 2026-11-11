<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A supporting document of an allowance / deduction entry, stored on the
 * private employee files disk under "payroll/entries" (App\Services\Payroll\PayrollFileStore).
 * Only streamed through the authorised download route.
 */
#[Fillable(['pay_entry_id', 'file_path', 'file_name', 'file_mime', 'file_size', 'uploaded_by'])]
class PayEntryAttachment extends Model
{
    /**
     * @return BelongsTo<PayEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(PayEntry::class, 'pay_entry_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
