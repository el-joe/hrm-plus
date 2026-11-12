<?php

namespace App\Actions\Training;

use App\Enums\TrainingAttendanceStatus;
use App\Enums\TrainingEnrolmentStatus;
use App\Models\TrainingEnrolment;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Attendance of one session day for many participants at once (one row per
 * enrolment and day, updated in place). Only seated participants the admin can
 * see (data scope) are recorded; days in the future cannot be recorded.
 */
class RecordTrainingAttendance
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<int, array{status: string, note?: string|null}>  $rows  enrolment id => status / note
     *
     * @throws ValidationException
     */
    public function handle(TrainingSession $session, string $date, array $rows, User $actor): int
    {
        if (! in_array($date, $session->days(), true)) {
            throw ValidationException::withMessages(['date' => __('training.validation.not_session_day')]);
        }

        if ($date > today()->toDateString()) {
            throw ValidationException::withMessages(['date' => __('training.validation.future_day')]);
        }

        // Scoped query: an admin records only the participants they may see.
        $enrolments = TrainingEnrolment::query()->where('training_session_id', $session->id)
            ->whereIn('status', TrainingEnrolmentStatus::seated())->whereKey(array_keys($rows))->get()->keyBy('id');

        return DB::transaction(function () use ($enrolments, $rows, $date, $actor, $session): int {
            $count = 0;

            foreach ($rows as $id => $row) {
                $enrolment = $enrolments->get((int) $id);

                if ($enrolment === null || blank($row['status'] ?? null)) {
                    continue;
                }

                $enrolment->attendances()->updateOrCreate(['date' => $date], [
                    'status' => TrainingAttendanceStatus::from($row['status']),
                    'note' => $row['note'] ?? null,
                    'recorded_by' => $actor->id,
                ]);
                $count++;
            }

            $this->audit->log('training.attendance_recorded', $session, meta: ['date' => $date, 'count' => $count], actor: $actor);

            return $count;
        });
    }
}
