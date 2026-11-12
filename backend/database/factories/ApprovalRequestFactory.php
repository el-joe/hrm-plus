<?php

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\LeaveRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending approval of a leave request. Tests of the approval flow should go through
 * App\Services\Approvals instead.
 *
 * @extends Factory<ApprovalRequest>
 */
class ApprovalRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'approval_workflow_id' => ApprovalWorkflow::factory(),
            'approvable_type' => 'leave_request',
            'approvable_id' => LeaveRequest::factory(),
            'status' => ApprovalStatus::Pending,
        ];
    }
}
