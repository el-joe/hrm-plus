<?php

namespace Database\Seeders;

use App\Enums\ApproverType;
use App\Models\ApprovalWorkflow;
use App\Models\LeaveRequest;
use Illuminate\Database\Seeder;

/**
 * Leave reference data for every environment: the approval workflows a
 * leave type can use (editable afterwards). Leave types and policies are
 * company data and are not seeded in production.
 *
 * - leave_request: two levels — the manager (leaves.approve, within the
 *   manager's data scope), then HR (leaves.manage);
 * - leave_request_hr: one level, HR (leaves.manage) — e.g. sick or maternity leave;
 * - leave_cancellation: cancelling approved leave that has started, HR (leaves.manage).
 */
class LeaveDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['leave_request', 'Leave approval (manager, then HR)', 'Leave requests: the direct / department / branch manager first, then HR.', [
                ['Manager approval', 'leaves.approve'], ['HR approval', 'leaves.manage'],
            ]],
            ['leave_request_hr', 'Leave approval (HR only)', 'Leave requests decided by HR in one step.', [['HR approval', 'leaves.manage']]],
            [LeaveRequest::CANCELLATION_WORKFLOW, 'Leave cancellation approval', 'Cancelling approved leave that has already started.', [['HR approval', 'leaves.manage']]],
        ] as [$key, $name, $description, $steps]) {
            $workflow = ApprovalWorkflow::query()->firstOrCreate(['key' => $key], ['name' => $name, 'description' => $description, 'is_active' => true]);

            if ($workflow->steps()->doesntExist()) {
                foreach ($steps as $index => [$stepName, $permission]) {
                    $workflow->steps()->create([
                        'sequence' => $index + 1, 'name' => $stepName, 'approver_type' => ApproverType::Permission, 'approver_permission' => $permission,
                    ]);
                }
            }
        }
    }
}
