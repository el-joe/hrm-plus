<?php

namespace App\Events;

use App\Models\ApprovalRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when any step rejects a request; the request is then closed.
 */
class ApprovalRequestRejected
{
    use Dispatchable;

    public function __construct(public ApprovalRequest $approvalRequest, public string $reason) {}
}
