<?php

namespace App\Services\Training;

use App\Enums\TrainingEnrolmentStatus;
use App\Models\Employee;
use App\Models\TrainingCertificate;
use App\Models\TrainingEnrolment;
use App\Models\User;
use App\Services\Employees\Contracts\TimelineSource;
use Illuminate\Support\Collection;

/**
 * Training events of the employee history timeline (training.view): finished
 * courses (completed / failed) and certificates obtained.
 */
class TrainingTimeline implements TimelineSource
{
    public function entries(User $user, Employee $employee): Collection
    {
        if (! $user->can('training.view')) {
            return collect();
        }

        $courses = TrainingEnrolment::query()->where('employee_id', $employee->id)
            ->whereIn('status', [TrainingEnrolmentStatus::Completed, TrainingEnrolmentStatus::Failed])
            ->with('session.course')->get()
            ->map(fn (TrainingEnrolment $enrolment): array => [
                'date' => $enrolment->session->end_date,
                'kind' => 'event',
                'id' => $enrolment->id,
                'module' => 'training',
                'title' => __('training.timeline.course', ['course' => $enrolment->session->course->name]),
                'description' => $enrolment->score !== null ? __('training.timeline.score', ['score' => $enrolment->score]) : null,
                'url' => route('admin.training-enrolments.show', $enrolment),
                'tone' => $enrolment->status->tone(),
                'badge' => $enrolment->status->label(),
            ]);

        $certificates = TrainingCertificate::query()->where('employee_id', $employee->id)->whereNotNull('issued_on')->get()
            ->map(fn (TrainingCertificate $certificate): array => [
                'date' => $certificate->issued_on,
                'kind' => 'event',
                'id' => $certificate->id,
                'module' => 'training',
                'title' => __('training.timeline.certificate', ['name' => $certificate->name]),
                'description' => $certificate->expires_on ? __('training.timeline.expires', ['date' => $certificate->expires_on->toDateString()]) : null,
                'url' => null,
                'tone' => $certificate->isExpired() ? 'danger' : 'success',
                'badge' => $certificate->type->label(),
            ]);

        return $courses->toBase()->merge($certificates->toBase()->all())->values();
    }
}
