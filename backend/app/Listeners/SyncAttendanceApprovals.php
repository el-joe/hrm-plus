<?php

namespace App\Listeners;

use App\Actions\Attendance\ManageAttendanceCorrection;
use App\Actions\Attendance\ManageManualBatch;
use App\Actions\Attendance\ManageScheduleChange;
use App\Events\ApprovalRequestApproved;
use App\Events\ApprovalRequestRejected;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceManualBatch;
use App\Models\ScheduleChangeRequest;
use App\Models\User;
use App\Services\Authorization\DataScopeManager;

/**
 * Applies approval outcomes of the attendance workflows: corrections, manual
 * batches / imports and schedule changes.
 */
class SyncAttendanceApprovals
{
    public function __construct(private readonly DataScopeManager $scopes) {}

    public function handle(ApprovalRequestApproved|ApprovalRequestRejected $event): void
    {
        $request = $event->approvalRequest;
        $record = $this->scopes->withoutScoping(fn () => $request->approvable()->getResults());
        $approved = $event instanceof ApprovalRequestApproved;
        $decidedBy = User::query()->find($request->actions()->reorder()->latest('id')->value('acted_by'));

        $this->scopes->withoutScoping(function () use ($record, $approved, $decidedBy): void {
            match (true) {
                $record instanceof AttendanceCorrection => app(ManageAttendanceCorrection::class)->applyApprovalOutcome($record, $approved, $decidedBy),
                $record instanceof AttendanceManualBatch => app(ManageManualBatch::class)->applyApprovalOutcome($record, $approved, $decidedBy),
                $record instanceof ScheduleChangeRequest => app(ManageScheduleChange::class)->applyApprovalOutcome($record, $approved, $decidedBy),
                default => null,
            };
        });
    }
}
