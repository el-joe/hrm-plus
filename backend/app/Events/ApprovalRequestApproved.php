<?php

namespace App\Events;

use App\Models\ApprovalRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once, after the final step of a request is approved. Modules listen
 * for it to apply the approved change (e.g. deduct the leave balance).
 */
class ApprovalRequestApproved
{
    use Dispatchable;

    public function __construct(public ApprovalRequest $approvalRequest) {}
}
