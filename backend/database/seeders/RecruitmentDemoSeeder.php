<?php

namespace Database\Seeders;

use App\Actions\Offboarding\ManageOffboardingCase;
use App\Actions\Offboarding\OpenOffboardingCase;
use App\Actions\Onboarding\UpdateOnboardingTask;
use App\Actions\Recruitment\ApplyToVacancy;
use App\Actions\Recruitment\ConvertCandidateToEmployee;
use App\Actions\Recruitment\ManageInterview;
use App\Actions\Recruitment\ManageOffer;
use App\Actions\Recruitment\MoveApplication;
use App\Actions\Recruitment\SaveVacancy;
use App\Actions\Recruitment\SubmitScorecard;
use App\Enums\CandidateSource;
use App\Enums\InterviewDecision;
use App\Enums\StageCategory;
use App\Enums\TaskStatus;
use App\Enums\TerminationType;
use App\Enums\VacancyStatus;
use App\Models\ApprovalRequest;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\EvaluationForm;
use App\Models\Interview;
use App\Models\JobApplication;
use App\Models\Position;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\Approvals\ApprovalService;
use App\Services\Offboarding\ContractExpiryScanner;
use App\Services\Recruitment\Pipeline;
use App\Services\Recruitment\RecruitmentFileStore;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

/**
 * Demo recruitment, onboarding and offboarding data (development only),
 * produced through the real actions so every record is consistent:
 *
 * - 5 vacancies (4 open — 3 on the career page — and 1 draft);
 * - 25 candidates spread over the pipeline stages, with CVs, notes, tags;
 * - interviews (past ones scored, upcoming ones assigned to the recruiter,
 *   the IT department manager and employee2@hrmplus.test in the portal);
 * - one offer awaiting approval, one sent, and one accepted and hired
 *   (Accountant) — which fills its vacancy and starts an onboarding;
 * - one resignation offboarding case with the exit interview recorded, plus
 *   the "contract end" cases opened by the contract expiry scan.
 *
 * E-mails and notifications are faked while seeding. Skipped when vacancies exist.
 */
class RecruitmentDemoSeeder extends Seeder
{
    /** @var list<array{0: string|null, 1: string|null, 2: string, 3: string}> [name_ar, name_en, title, city] */
    private const PEOPLE = [
        ['نادين فاروق', 'Nadine Farouk', 'Sales Executive', 'Cairo'], ['أحمد سمير', 'Ahmed Samir', 'Account Manager', 'Giza'],
        [null, 'Laila Hassan', 'Sales Associate', 'Cairo'], ['مروان عادل', 'Marwan Adel', 'Field Sales Rep', 'Cairo'],
        ['هالة منير', 'Hala Mounir', 'Retail Sales', 'Cairo'], ['شادي رأفت', 'Shady Raafat', 'Customer Advisor', 'Giza'],
        ['ياسمين الشريف', 'Yasmine El-Sherif', 'Accountant', 'Cairo'], ['محمد الطوخي', 'Mohamed El-Tokhy', 'Junior Accountant', 'Alexandria'],
        [null, 'Rania Fouad', 'Senior Accountant', 'Cairo'], ['عمرو حلمي', 'Amr Helmy', 'Accounts Payable Clerk', 'Giza'],
        ['سلمى نبيل', 'Salma Nabil', 'Audit Associate', 'Cairo'], ['كريم عصام', 'Karim Essam', 'Backend Developer', 'Cairo'],
        ['هدى رشاد', 'Hoda Rashad', 'PHP Developer', 'Alexandria'], [null, 'Omar Khaled', 'Software Engineer', 'Cairo'],
        ['ريم عبد العزيز', 'Reem Abdelaziz', 'Laravel Developer', 'Giza'], ['يوسف مجدي', 'Youssef Magdy', 'Full-stack Developer', 'Cairo'],
        ['إسلام فتحي', 'Islam Fathy', 'DevOps Engineer', 'Cairo'], ['منة الله صبري', 'Menna Sabry', 'QA Engineer', 'Cairo'], ['أمير لطفي', 'Amir Lotfy', 'Software Developer', 'Giza'],
        ['حسام بدر', 'Hossam Badr', 'Driver', 'Alexandria'], ['سعيد عوض', 'Saeed Awad', 'Delivery Driver', 'Alexandria'],
        ['رامي جابر', 'Ramy Gaber', 'Courier', 'Alexandria'], ['وائل ممدوح', 'Wael Mamdouh', 'Truck Driver', 'Alexandria'],
        ['ماجد شعبان', 'Maged Shaaban', 'Van Driver', 'Alexandria'], ['نهى صلاح', 'Noha Salah', 'Recruitment Specialist', 'Giza'],
    ];

    private int $person = 0;

    public function __construct(
        private readonly SaveVacancy $vacancies,
        private readonly ApplyToVacancy $apply,
        private readonly MoveApplication $move,
        private readonly ManageInterview $interviews,
        private readonly SubmitScorecard $scorecards,
        private readonly ManageOffer $offers,
        private readonly ConvertCandidateToEmployee $convert,
        private readonly Pipeline $pipeline,
        private readonly RecruitmentFileStore $files,
    ) {}

    public function run(): void
    {
        if (Vacancy::query()->exists() || Position::query()->doesntExist()) {
            return;
        }

        $recruiter = User::query()->where('email', 'recruiter@hrmplus.test')->first();
        $hrManager = User::query()->where('email', 'hr.manager@hrmplus.test')->first();

        if ($recruiter === null || $hrManager === null) {
            return;
        }

        Notification::fake();
        Auth::setUser($recruiter);
        mt_srand(4040);

        $form = EvaluationForm::query()->first();
        $fullTime = EmploymentType::query()->where('code', 'FT')->first() ?? EmploymentType::query()->first();
        $itManager = User::query()->where('email', 'department.manager@hrmplus.test')->first();
        $engineer = User::query()->where('email', 'employee2@hrmplus.test')->first();

        $sales = $this->vacancy('SALR-CAI', 'مندوب مبيعات - القاهرة', 'Sales Representative - Cairo', 1, true, $fullTime, $form, $recruiter, '8000', '12000');
        $accountant = $this->vacancy('ACC', 'محاسب', 'Accountant', 1, true, $fullTime, $form, $recruiter, '12000', '16000');
        $backend = $this->vacancy(null, 'مهندس أنظمة خلفية (Laravel)', 'Backend Engineer (Laravel)', 2, true, $fullTime, $form, $recruiter, '25000', '35000', 'IT');
        $driver = $this->vacancy('DRV', 'سائق توصيل - الإسكندرية', 'Delivery Driver - Alexandria', 1, false, $fullTime, $form, $recruiter, null, null);
        $this->vacancy(null, 'منسق موارد بشرية', 'HR Coordinator', 1, false, $fullTime, $form, $recruiter, null, null, 'HR', VacancyStatus::Draft);

        // Sales: 6 applicants across the early stages, one rejected, one in interviews.
        $salesApps = $this->applicants($sales, 6, $recruiter);
        $this->moveTo($salesApps[1], 'cv_review', $recruiter);
        $this->moveTo($salesApps[2], 'shortlisted', $recruiter);
        $this->moveTo($salesApps[3], 'rejected', $recruiter, 'Not enough field sales experience.');
        $this->scheduleAndScore($salesApps[4], [$recruiter], $recruiter, past: true, scores: [[4, 4, 3, 4]]);
        $this->moveTo($salesApps[4], 'hr_interview', $recruiter);
        $this->interviews->schedule($salesApps[5], $this->slot(2, '11:00', [$recruiter->id]), $recruiter);

        // Accountant: one hired end to end (scored interviews, approved offer, accepted, converted).
        $accApps = $this->applicants($accountant, 5, $recruiter);
        $this->moveTo($accApps[1], 'cv_review', $recruiter);
        $this->moveTo($accApps[2], 'withdrawn', $recruiter);
        $this->scheduleAndScore($accApps[3], [$recruiter, $hrManager], $recruiter, past: true, scores: [[3, 3, 3, 2], [3, 2, 3, 3]]);
        $hire = $accApps[0];
        $this->scheduleAndScore($hire, [$recruiter, $hrManager], $recruiter, past: true, scores: [[5, 4, 4, 5], [4, 5, 4, 4]]);
        $offer = $this->offers->create($hire, [
            'position_id' => $accountant->position_id, 'job_title_id' => $accountant->job_title_id, 'employment_type_id' => $fullTime->id,
            'basic_salary' => '14500.00', 'start_date' => today()->subDays(3)->toDateString(), 'expires_on' => today()->subDays(8)->toDateString(),
            'probation_months' => 3, 'benefits' => "Medical insurance\nAnnual bonus",
        ], $recruiter);
        $this->offers->submit($offer, $recruiter);
        app(ApprovalService::class)->approve(ApprovalRequest::query()->latest('id')->firstOrFail(), $hrManager, 'Within the grade band.');
        $offer->refresh()->forceFill(['expires_on' => today()->addDays(5)])->save();
        $this->offers->send($offer, $recruiter);
        $this->offers->respond($offer->refresh(), true, $recruiter, 'Accepted by phone.');
        $candidate = $hire->candidate;
        $this->convert->handle($hire->refresh(), [
            'name_ar' => $candidate->name_ar ?? $candidate->name_en, 'name_en' => $candidate->name_en ?? $candidate->name_ar,
            'email' => 'yasmine.elsherif@hrmplus.test', 'phone' => $candidate->phone,
            'hire_date' => today()->subDays(3)->toDateString(),
            'branch_id' => $accountant->branch_id, 'department_id' => $accountant->department_id, 'position_id' => $accountant->position_id,
            'employment_type_id' => $fullTime->id, 'basic_salary' => '14500.00', 'create_account' => false,
        ], $hrManager);
        $onboarding = $hire->refresh()->employee->onboardings()->withoutGlobalScopes()->first();
        $onboarding?->tasks()->orderBy('sort_order')->limit(4)->get()
            ->each(fn ($task) => app(UpdateOnboardingTask::class)->handle($task, TaskStatus::Done, $hrManager));

        // Backend engineer: interviews with the IT manager and an employee interviewer, one offer awaiting approval, one sent.
        $backendApps = $this->applicants($backend, 8, $recruiter);
        $this->moveTo($backendApps[1], 'shortlisted', $recruiter);
        $panel = array_values(array_filter([$recruiter, $itManager, $engineer]));
        $this->scheduleAndScore($backendApps[2], $panel, $recruiter, past: true, scores: [[4, 3, 5, 4], [4, 4, 4, 3]]);
        $this->moveTo($backendApps[2], 'technical_interview', $recruiter);
        $this->interviews->schedule($backendApps[3], $this->slot(1, '10:00', array_map(fn (User $user) => $user->id, $panel)), $recruiter);
        $this->interviews->schedule($backendApps[4], $this->slot(3, '13:30', array_map(fn (User $user) => $user->id, $panel), online: true), $recruiter);
        $this->scheduleAndScore($backendApps[5], $panel, $recruiter, past: true, scores: [[5, 4, 5, 4], [5, 5, 4, 4]]);
        $pending = $this->offers->create($backendApps[5], [
            'job_title_id' => $backend->job_title_id, 'employment_type_id' => $fullTime->id, 'basic_salary' => '31000.00',
            'start_date' => today()->addDays(30)->toDateString(), 'expires_on' => today()->addDays(7)->toDateString(), 'probation_months' => 3,
        ], $recruiter);
        $this->offers->submit($pending, $recruiter);
        $this->scheduleAndScore($backendApps[6], $panel, $recruiter, past: true, scores: [[4, 4, 4, 4], [3, 4, 4, 4]]);
        $sent = $this->offers->create($backendApps[6], [
            'job_title_id' => $backend->job_title_id, 'employment_type_id' => $fullTime->id, 'basic_salary' => '28000.00',
            'start_date' => today()->addDays(21)->toDateString(), 'expires_on' => today()->addDays(6)->toDateString(), 'probation_months' => 3,
        ], $recruiter);
        $this->offers->submit($sent, $recruiter);
        app(ApprovalService::class)->approve(ApprovalRequest::query()->latest('id')->firstOrFail(), $hrManager);
        $this->offers->send($sent->refresh(), $recruiter);

        // Driver: a vacancy with its own pipeline (copy of the default one).
        $this->pipeline->customise($driver);
        $driverApps = $this->applicants($driver->refresh(), 5, $recruiter);
        $this->moveTo($driverApps[1], 'shortlisted', $recruiter);
        $this->moveTo($driverApps[2], 'cv_review', $recruiter);

        // Talent pool candidate (no application).
        $this->candidate(CandidateSource::Referral, $recruiter);

        $this->offboarding($hrManager);
        app(ContractExpiryScanner::class)->run();

        Auth::forgetUser();
    }

    private function vacancy(?string $positionCode, string $titleAr, string $titleEn, int $openings, bool $public, EmploymentType $type, ?EvaluationForm $form,
        User $actor, ?string $min, ?string $max, ?string $departmentCode = null, VacancyStatus $status = VacancyStatus::Open): Vacancy
    {
        $position = $positionCode ? Position::query()->where('code', $positionCode)->first() : null;
        $department = $position?->department ?? Department::query()->where('code', $departmentCode)->first() ?? Department::query()->first();

        return $this->vacancies->handle(null, [
            'title_ar' => $titleAr, 'title_en' => $titleEn,
            'description_ar' => 'نبحث عن '.$titleAr.' للانضمام إلى فريق '.$department->name_ar.'.',
            'description_en' => 'We are looking for a '.$titleEn.' to join our '.$department->name_en.' team.',
            'requirements_ar' => "خبرة لا تقل عن سنتين\nمهارات تواصل ممتازة\nإجادة اللغة الإنجليزية",
            'requirements_en' => "At least two years of experience\nExcellent communication skills\nGood command of English",
            'branch_id' => $department->branch_id ?? $position?->department?->branch_id,
            'department_id' => $department->id, 'position_id' => $position?->id, 'employment_type_id' => $type->id,
            'openings' => $openings, 'salary_min' => $min, 'salary_max' => $max, 'status' => $status, 'is_public' => $public,
            'publish_at' => today()->subDays(20)->toDateString(), 'application_deadline' => today()->addDays(25)->toDateString(),
            'evaluation_form_id' => $form?->id, 'hiring_manager_id' => $actor->id,
        ], $actor);
    }

    /**
     * @return list<JobApplication>
     */
    private function applicants(Vacancy $vacancy, int $count, User $actor): array
    {
        $sources = [CandidateSource::CareerPage, CandidateSource::LinkedIn, CandidateSource::Referral, CandidateSource::JobBoard];
        $applications = [];

        for ($index = 0; $index < $count; $index++) {
            $source = $sources[$index % count($sources)];
            $applications[] = $this->apply->handle($this->candidate($source, $actor), $vacancy, $source, null, $source === CandidateSource::CareerPage ? null : $actor);
        }

        return $applications;
    }

    private function candidate(CandidateSource $source, User $actor): Candidate
    {
        [$nameAr, $nameEn, $title, $city] = self::PEOPLE[$this->person % count(self::PEOPLE)];
        $this->person++;
        $slug = strtolower(str_replace([' ', '-'], '.', $nameEn ?? 'candidate'.$this->person));
        $phone = '+20 10'.str_pad((string) (10000000 + $this->person * 7919), 8, '0', STR_PAD_LEFT);

        $candidate = new Candidate([
            'name_ar' => $nameAr, 'name_en' => $nameEn, 'email' => $slug.'@example.test', 'phone' => $phone, 'city' => $city,
            'current_job_title' => $title, 'current_employer' => ['Nile Trading', 'Delta Foods', 'Cairo Logistics', 'Pyramid Systems'][$this->person % 4],
            'experience_years' => (string) (1 + $this->person % 9), 'skills' => ['Communication', 'MS Excel', 'Teamwork', 'English'],
            'expected_salary' => (string) (8000 + ($this->person % 10) * 1500), 'source' => $source,
        ]);
        $candidate->forceFill([
            'phone_normalized' => Candidate::normalisePhone($phone), 'locale' => $nameAr ? 'ar' : 'en',
            'consent_at' => $source === CandidateSource::CareerPage ? now() : null, 'created_by' => $source === CandidateSource::CareerPage ? null : $actor->id,
        ])->save();

        $this->files->storeCv($candidate, UploadedFile::fake()->createWithContent($slug.'-cv.pdf', $this->pdf($nameEn ?? $nameAr ?? 'Candidate', $title)));

        if ($this->person % 3 === 0) {
            $candidate->notes()->create(['body' => 'Strong profile; follow up on availability.', 'user_id' => $actor->id]);
        }

        return $candidate;
    }

    private function moveTo(JobApplication $application, string $code, User $actor, ?string $reason = null): void
    {
        $stage = $this->pipeline->stagesFor($application->vacancy)->firstWhere('code', $code);

        if ($stage !== null && $stage->category !== StageCategory::Hired) {
            $this->move->handle($application->refresh(), $stage, $actor, null, $reason, notifyCandidate: false);
        }
    }

    /**
     * @param  list<User>  $panel
     * @param  list<list<int>>  $scores  one row of criterion scores per interviewer
     */
    private function scheduleAndScore(JobApplication $application, array $panel, User $organiser, bool $past, array $scores): Interview
    {
        $interview = $this->interviews->schedule($application->refresh(), $this->slot($past ? -3 : 2, '10:30', array_map(fn (User $user) => $user->id, $panel)), $organiser);
        $criteria = $interview->evaluationForm?->criteria ?? collect();

        foreach (array_slice($panel, 0, count($scores)) as $index => $interviewer) {
            $row = $scores[$index];
            $this->scorecards->handle($interview->refresh()->load('interviewers'), $interviewer, [
                'recommendation' => array_sum($row) / max(1, count($row)) >= 3.5 ? 'yes' : 'no',
                'comments' => 'Structured interview; notes attached to the scorecard.',
                'scores' => $criteria->values()->mapWithKeys(fn ($criterion, $position) => [$criterion->id => $row[$position] ?? 3])->all(),
            ]);
        }

        $this->interviews->decide($interview->refresh(), array_sum($scores[0]) >= 14 ? InterviewDecision::Advance : InterviewDecision::Hold);

        return $interview;
    }

    /**
     * @param  list<int>  $interviewers
     * @return array<string, mixed>
     */
    private function slot(int $days, string $time, array $interviewers, bool $online = false): array
    {
        return [
            'type' => $online ? 'technical' : 'hr', 'date' => today()->addDays($days)->toDateString(), 'time' => $time,
            'timezone' => (string) setting('localisation.timezone'), 'duration_minutes' => 45,
            'mode' => $online ? 'online' : 'in_person', 'location' => $online ? null : 'Head office, meeting room 2',
            'meeting_url' => $online ? 'https://meet.example.test/hrm-interview' : null,
            'interviewer_ids' => $interviewers, 'notify_candidate' => true,
        ];
    }

    private function offboarding(User $hr): void
    {
        $accounts = User::query()->whereNotNull('employee_id')->pluck('employee_id');
        $employee = Employee::query()->active()->whereNotIn('id', $accounts)->whereNull('candidate_id')
            ->whereHas('department', fn ($query) => $query->where('code', 'SAL-CAI'))->orderByDesc('id')->first()
            ?? Employee::query()->active()->whereNotIn('id', $accounts)->orderByDesc('id')->first();

        if ($employee === null) {
            return;
        }

        $case = app(OpenOffboardingCase::class)->handle($employee, [
            'type' => TerminationType::Resignation->value, 'notice_date' => today()->subDays(10)->toDateString(),
            'last_working_date' => today()->addDays(20)->toDateString(), 'reason' => 'Relocating to another city.', 'eligible_for_rehire' => true,
        ], $hr);

        $manage = app(ManageOffboardingCase::class);
        $case->items()->orderBy('sort_order')->limit(2)->get()->each(fn ($item) => $manage->updateItem($item, TaskStatus::Done, $hr));
        $manage->recordExitInterview($case, [
            'primary_reason' => 'relocation', 'rating_management' => 4, 'rating_environment' => 4, 'rating_compensation' => 3,
            'rating_growth' => 3, 'would_recommend' => true, 'would_return' => true,
        ], 'Positive experience; leaving for family reasons.', $hr);
    }

    /**
     * A tiny valid one-page PDF with the candidate's name (demo CV).
     */
    private function pdf(string $name, string $title): string
    {
        $text = preg_replace('/[^A-Za-z0-9 .\-]/', '', $name.' - '.$title.' - Curriculum vitae');
        $stream = "BT /F1 18 Tf 72 720 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
