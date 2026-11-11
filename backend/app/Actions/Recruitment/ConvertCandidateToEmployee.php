<?php

namespace App\Actions\Recruitment;

use App\Actions\Employees\CreateEmployee;
use App\Actions\Employees\ManageEmployeeAccount;
use App\Actions\Onboarding\StartOnboarding;
use App\Enums\DocumentCategory;
use App\Enums\OfferStatus;
use App\Enums\StageCategory;
use App\Enums\VacancyStatus;
use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\Audit\AuditLogger;
use App\Services\Employees\EmployeeFileStore;
use App\Services\Recruitment\Pipeline;
use App\Services\Recruitment\RecruitmentFileStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hire a candidate whose offer was accepted:
 *
 * 1. the employee is created through CreateEmployee (number, placement with
 *    the position headcount check of the Form Request, "hire" history row,
 *    initial salary = the accepted offer's unless overridden) and keeps the lineage: employees.candidate_id and
 *    employees.job_application_id; the candidate and application point back;
 * 2. the CV is copied into the employee's documents (category "cv");
 * 3. the application moves to the "hired" stage and the vacancy's filled
 *    count goes up (the vacancy becomes "filled" when no opening is left);
 * 4. after the transaction: optional login account (set-password invitation)
 *    and the onboarding checklist (setting lifecycle.auto_start_onboarding).
 */
class ConvertCandidateToEmployee
{
    public function __construct(
        private readonly CreateEmployee $createEmployee,
        private readonly ManageEmployeeAccount $accounts,
        private readonly StartOnboarding $startOnboarding,
        private readonly MoveApplication $move,
        private readonly Pipeline $pipeline,
        private readonly RecruitmentFileStore $recruitmentFiles,
        private readonly EmployeeFileStore $employeeFiles,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated ConvertCandidateRequest input (employee fields + create_account, account_email)
     */
    public function handle(JobApplication $application, array $data, User $actor): Employee
    {
        $application->loadMissing(['candidate', 'vacancy', 'latestOffer']);
        $this->guard($application);

        $hiredStage = $this->pipeline->firstOfCategory($application->vacancy, StageCategory::Hired);

        if ($hiredStage === null) {
            throw ValidationException::withMessages(['hire_date' => __('recruitment.validation.no_hired_stage')]);
        }

        $employee = DB::transaction(function () use ($application, $data, $actor, $hiredStage): Employee {
            $vacancy = Vacancy::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($application->vacancy_id);

            // The starting salary is the approved, accepted offer's unless an
            // admin allowed to change salaries entered another one.
            if (blank($data['basic_salary'] ?? null) && $application->latestOffer?->basic_salary !== null) {
                $data['basic_salary'] = $application->latestOffer->basic_salary;
            }

            $employee = $this->createEmployee->handle($data);
            $employee->forceFill([
                'candidate_id' => $application->candidate_id,
                'job_application_id' => $application->getKey(),
            ])->save();

            $application->candidate->forceFill(['employee_id' => $employee->getKey()])->save();
            $this->move->handle($application, $hiredStage, $actor, __('recruitment.history.hired', ['number' => $employee->employee_number]), allowHired: true);
            $application->forceFill(['employee_id' => $employee->getKey(), 'hired_at' => now()])->save();

            $filled = $vacancy->filled_count + 1;
            $vacancy->forceFill([
                'filled_count' => $filled,
                ...($filled >= $vacancy->openings ? ['status' => VacancyStatus::Filled, 'closed_at' => now()] : []),
            ])->save();

            $this->copyCv($application, $employee, $actor);

            $this->audit->log('recruitment.candidate_converted', $employee, meta: [
                'candidate_id' => $application->candidate_id,
                'job_application_id' => $application->getKey(),
                'vacancy' => $vacancy->reference,
            ], actor: $actor);

            return $employee;
        });

        if (($data['create_account'] ?? false) && filled($data['account_email'] ?? null)) {
            $this->accounts->create($employee, $data['account_email'], $data['locale'] ?? $application->candidate->locale);
        }

        if (setting('lifecycle.auto_start_onboarding')) {
            $this->startOnboarding->handle($employee, null, $application, $employee->hire_date, $actor);
        }

        return $employee;
    }

    private function guard(JobApplication $application): void
    {
        if ($application->employee_id !== null || $application->candidate->employee_id !== null) {
            throw ValidationException::withMessages(['hire_date' => __('recruitment.validation.already_hired')]);
        }

        if (! $application->isActive()) {
            throw ValidationException::withMessages(['hire_date' => __('recruitment.validation.application_closed')]);
        }

        if ($application->latestOffer?->status !== OfferStatus::Accepted) {
            throw ValidationException::withMessages(['hire_date' => __('recruitment.validation.offer_not_accepted')]);
        }
    }

    private function copyCv(JobApplication $application, Employee $employee, User $actor): void
    {
        $candidate = $application->candidate;

        if (! $this->recruitmentFiles->exists($candidate->cv_path)) {
            return;
        }

        $employee->documents()->create([
            'category' => DocumentCategory::Cv,
            'title' => __('recruitment.convert.cv_document_title'),
            'visible_to_employee' => true,
            'notes' => __('recruitment.convert.cv_document_note', ['reference' => $application->vacancy->reference]),
            ...$this->employeeFiles->storeContents(
                $employee,
                (string) $this->recruitmentFiles->disk()->get($candidate->cv_path),
                $candidate->cv_name ?? 'cv',
                $candidate->cv_mime ?? 'application/octet-stream',
                'documents',
            ),
            'uploaded_by' => $actor->getKey(),
        ]);
    }
}
