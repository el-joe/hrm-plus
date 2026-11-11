<?php

namespace App\Actions\Attendance;

use App\Enums\ManualBatchStatus;
use App\Models\AttendanceManualBatch;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\Attendance\AttendanceRecalculation;
use App\Services\Attendance\BulkAttendance;
use App\Services\Attendance\ManualAttendanceWriter;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Life of a bulk manual-attendance batch or an import after the draft:
 * confirm (from the confirmation screen) -> applied, or pending approval
 * when an attendance policy requires it -> applied / rejected by the
 * approval engine (workflow "attendance_manual_batch"). Every step is
 * written to the audit log with the batch summary.
 */
class ManageManualBatch
{
    public function __construct(
        private readonly BulkAttendance $bulk,
        private readonly ManualAttendanceWriter $writer,
        private readonly AttendanceRecalculation $recalculation,
        private readonly ApprovalService $approvals,
        private readonly AuditLogger $audit,
    ) {}

    public function confirm(AttendanceManualBatch $batch, User $user): AttendanceManualBatch
    {
        $this->ensureStatus($batch, ManualBatchStatus::Draft);

        if ($this->bulk->requiresApproval($batch)) {
            DB::transaction(function () use ($batch, $user): void {
                $batch->forceFill(['status' => ManualBatchStatus::PendingApproval, 'submitted_at' => now()])->save();
                $this->approvals->submit($batch, AttendanceManualBatch::WORKFLOW, $user);
                $this->audit->log('attendance.batch_submitted', $batch, meta: $this->meta($batch), actor: $user);
            });

            return $batch;
        }

        $batch->forceFill(['submitted_at' => now()])->save();

        return $this->apply($batch, $user);
    }

    public function apply(AttendanceManualBatch $batch, ?User $user): AttendanceManualBatch
    {
        $reference = $batch->reference();

        DB::transaction(function () use ($batch, $user, $reference): void {
            $dates = [];

            foreach ($batch->entries()->orderBy('id')->get() as $entry) {
                $this->writer->applyEntry($entry, $user, $batch->source, $reference);
                $dates[$entry->employee_id][] = $entry->work_date->format('Y-m-d');
            }

            $batch->forceFill(['status' => ManualBatchStatus::Applied, 'applied_at' => now()])->save();

            foreach ($dates as $employeeId => $employeeDates) {
                $this->recalculation->now($employeeId, $employeeDates);
            }

            $this->audit->log('attendance.batch_applied', $batch, meta: $this->meta($batch), actor: $user);
        });

        return $batch;
    }

    /**
     * Outcome of the approval engine.
     */
    public function applyApprovalOutcome(AttendanceManualBatch $batch, bool $approved, ?User $decidedBy): void
    {
        if ($batch->status !== ManualBatchStatus::PendingApproval) {
            return;
        }

        if ($approved) {
            $this->apply($batch, $decidedBy);

            return;
        }

        $batch->forceFill(['status' => ManualBatchStatus::Rejected])->save();
        $this->audit->log('attendance.batch_rejected', $batch, meta: $this->meta($batch), actor: $decidedBy);
    }

    /**
     * Throw away a draft that was never confirmed.
     */
    public function discard(AttendanceManualBatch $batch, User $user): void
    {
        $this->ensureStatus($batch, ManualBatchStatus::Draft);

        DB::transaction(function () use ($batch, $user): void {
            $this->audit->log('attendance.batch_discarded', $batch, meta: $this->meta($batch), actor: $user);
            $batch->forceFill(['status' => ManualBatchStatus::Cancelled])->save();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(AttendanceManualBatch $batch): array
    {
        return [
            'reference' => $batch->reference(),
            'source' => $batch->source->value,
            'work_date' => $batch->work_date?->format('Y-m-d'),
            'filters' => $batch->filters,
            'summary' => $batch->summary,
            'entries' => $batch->entries()->count(),
        ];
    }

    private function ensureStatus(AttendanceManualBatch $batch, ManualBatchStatus $status): void
    {
        if ($batch->status !== $status) {
            throw ValidationException::withMessages(['batch' => __('attendance.bulk.not_draft')]);
        }
    }
}
