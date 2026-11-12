<?php

namespace App\Services\Reports\Definitions\Leave;

/**
 * Reuses the Phase 6 "requests" report: requests overlapping the period, any status.
 */
class LeaveRequestsReport extends LegacyLeaveReport
{
    public function key(): string
    {
        return 'leave_requests';
    }

    protected function legacyKey(): string
    {
        return 'requests';
    }

    public function extraFilters(): array
    {
        return [...parent::extraFilters(), ...$this->statusFilter()];
    }
}
