<?php

namespace App\Services\Reports\Definitions\Leave;

use App\Enums\LeaveRequestStatus;

/**
 * Reuses the Phase 6 "requests" report with the status fixed to {Approved}.
 */
class ApprovedLeavesReport extends LegacyLeaveReport
{
    public function key(): string
    {
        return 'approved_leaves';
    }

    protected function legacyKey(): string
    {
        return 'requests';
    }

    protected function forced(): array
    {
        return ['status' => LeaveRequestStatus::Approved->value];
    }
}
