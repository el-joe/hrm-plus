<?php

namespace App\Services\Training;

use App\Models\Employee;
use App\Models\EmployeeSkill;
use App\Models\TrainingCertificate;
use App\Models\TrainingEnrolment;
use App\Models\User;
use App\Services\Employees\Contracts\ProfileTab;

/**
 * "Training" tab of the admin employee profile (training.view): enrolments,
 * certificates (with validity) and skills.
 */
class EmployeeTrainingTab implements ProfileTab
{
    public function visibleTo(User $user, Employee $employee): bool
    {
        return $user->can('training.view');
    }

    /**
     * @return array<string, mixed>
     */
    public function data(User $user, Employee $employee): array
    {
        return [
            'trainingEnrolments' => TrainingEnrolment::query()->where('employee_id', $employee->id)->with(['session.course', 'attendances'])
                ->latest('id')->limit(30)->get(),
            'trainingCertificates' => TrainingCertificate::query()->where('employee_id', $employee->id)->with('course')
                ->orderByRaw('expires_on is null')->orderBy('expires_on')->get(),
            'employeeSkills' => EmployeeSkill::query()->where('employee_id', $employee->id)->with('skill')->orderByDesc('level')->get(),
            'reminderDays' => (int) setting('training.certificate_reminder_days'),
        ];
    }
}
