<?php

namespace App\Services\Recruitment;

use App\Enums\AccountType;
use App\Enums\CandidateSource;
use App\Enums\InterviewMode;
use App\Enums\InterviewType;
use App\Enums\StageCategory;
use App\Enums\UserStatus;
use App\Enums\VacancyStatus;
use App\Models\Branch;
use App\Models\Department;
use App\Models\EmploymentType;
use App\Models\EvaluationForm;
use App\Models\JobTitle;
use App\Models\Position;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Database\Eloquent\Collection;

/**
 * Choices offered by the recruitment screens. Eloquent queries, so data
 * scopes apply; inactive records are only listed when already in use.
 */
class RecruitmentOptions
{
    /**
     * @return array<string, mixed>
     */
    public function vacancyForm(?Vacancy $vacancy): array
    {
        $keep = fn (string $column) => $vacancy?->getAttribute($column);

        return [
            'branches' => Branch::query()->activeOrKeep($keep('branch_id'))->orderBy('code')->get(),
            'departments' => Department::query()->activeOrKeep($keep('department_id'))->with('branch')->orderBy('code')->get(),
            'positions' => Position::query()->activeOrKeep($keep('position_id'))->orderBy('code')->get(),
            'jobTitles' => JobTitle::query()->activeOrKeep($keep('job_title_id'))->orderBy('code')->get(),
            'employmentTypes' => EmploymentType::query()->activeOrKeep($keep('employment_type_id'))->orderBy('code')->get(),
            'evaluationForms' => EvaluationForm::query()->activeOrKeep($keep('evaluation_form_id'))->orderBy(EvaluationForm::localizedColumn())->get(),
            'hiringManagers' => User::query()->admins()->where('status', UserStatus::Active)->orderBy('name')->get(['id', 'name', 'email']),
            'statuses' => VacancyStatus::cases(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function vacancyFilters(): array
    {
        return [
            'branches' => Branch::query()->orderBy('code')->get(),
            'departments' => Department::query()->orderBy('code')->get(),
            'statuses' => VacancyStatus::cases(),
        ];
    }

    /**
     * Vacancies a candidate can be added to.
     *
     * @return Collection<int, Vacancy>
     */
    public function openVacancies(): Collection
    {
        return Vacancy::query()->open()->orderBy('reference')->get();
    }

    /**
     * @return list<CandidateSource>
     */
    public function sources(): array
    {
        return CandidateSource::cases();
    }

    /**
     * @return array<string, mixed>
     */
    public function interviewForm(): array
    {
        return [
            'types' => InterviewType::cases(),
            'modes' => InterviewMode::cases(),
            'evaluationForms' => EvaluationForm::query()->active()->orderBy(EvaluationForm::localizedColumn())->get(),
            'interviewers' => $this->interviewers(),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'defaultTimezone' => (string) setting('localisation.timezone'),
        ];
    }

    /**
     * Active admin users and employee accounts (labelled with their employee number).
     *
     * @return Collection<int, User>
     */
    public function interviewers(): Collection
    {
        return User::query()->where('status', UserStatus::Active)
            ->whereIn('account_type', [AccountType::Admin, AccountType::Employee])
            ->with(['employee' => fn ($query) => $query->withoutGlobalScopes()])
            ->orderBy('account_type')->orderBy('name')
            ->get();
    }

    /**
     * @return list<StageCategory>
     */
    public function stageCategories(): array
    {
        return StageCategory::cases();
    }
}
