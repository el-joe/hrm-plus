<?php

namespace App\Services\Training;

use App\Enums\TrainingEnrolmentStatus;
use App\Models\Employee;
use App\Models\EmployeeSkill;
use App\Models\TrainingCertificate;
use App\Models\TrainingEnrolment;
use App\Models\TrainingSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The employee's own training for the portal and the mobile API: always the
 * signed-in employee's records, admin data scopes ignored.
 */
class PortalTraining
{
    /**
     * @return Builder<TrainingEnrolment>
     */
    public function enrolments(Employee $employee): Builder
    {
        return TrainingEnrolment::query()->withoutGlobalScopes()->where('employee_id', $employee->id)
            ->with(['session.course.provider', 'attendances', 'latestApprovalRequest.currentStep', 'certificate'])
            ->join('training_sessions', 'training_sessions.id', '=', 'training_enrolments.training_session_id')
            ->select('training_enrolments.*')
            ->orderByDesc('training_sessions.start_date')->orderByDesc('training_enrolments.id');
    }

    public function findEnrolment(Employee $employee, int $id): ?TrainingEnrolment
    {
        return TrainingEnrolment::query()->withoutGlobalScopes()->where('employee_id', $employee->id)
            ->with(['session.course.provider', 'attendances', 'latestApprovalRequest.currentStep', 'approvalRequests.actions', 'certificate'])
            ->find($id);
    }

    /**
     * Upcoming scheduled sessions with free seats the employee is not on (or was cancelled / rejected from).
     *
     * @return Collection<int, TrainingSession>
     */
    public function openSessions(Employee $employee): Collection
    {
        $taken = TrainingEnrolment::query()->withoutGlobalScopes()->where('employee_id', $employee->id)
            ->whereNotIn('status', [TrainingEnrolmentStatus::Cancelled, TrainingEnrolmentStatus::Rejected])->pluck('training_session_id');

        return TrainingSession::query()->upcoming()->whereNotIn('id', $taken)
            ->where(fn (Builder $query) => $query->whereNull('branch_id')->orWhere('branch_id', $employee->branch_id))
            ->whereHas('course', fn (Builder $query) => $query->where('is_active', true))
            ->with('course.provider')->withCount(['enrolments as seats_taken' => fn (Builder $query) => $query->withoutGlobalScopes()
            ->whereIn('status', TrainingEnrolmentStatus::seated())])
            ->orderBy('start_date')->get()
            ->filter(fn (TrainingSession $session): bool => $session->seats_taken < $session->capacity)->values();
    }

    /**
     * @return Builder<TrainingCertificate>
     */
    public function certificates(Employee $employee): Builder
    {
        return TrainingCertificate::query()->withoutGlobalScopes()->where('employee_id', $employee->id)
            ->with('course')->orderByRaw('expires_on is null')->orderBy('expires_on')->orderByDesc('id');
    }

    public function findCertificate(Employee $employee, int $id): ?TrainingCertificate
    {
        return $this->certificates($employee)->find($id);
    }

    /**
     * @return Collection<int, EmployeeSkill>
     */
    public function skills(Employee $employee): Collection
    {
        return EmployeeSkill::query()->withoutGlobalScopes()->where('employee_id', $employee->id)
            ->with('skill')->orderByDesc('level')->get();
    }
}
