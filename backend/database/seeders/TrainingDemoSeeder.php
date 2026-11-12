<?php

namespace Database\Seeders;

use App\Actions\Training\ManageTrainingCertificate;
use App\Actions\Training\ManageTrainingEnrolment;
use App\Actions\Training\ManageTrainingSession;
use App\Actions\Training\RecordTrainingAttendance;
use App\Actions\Training\SaveTrainingCourse;
use App\Actions\Training\SetEmployeeSkill;
use App\Enums\EmployeeStatus;
use App\Enums\TrainingEnrolmentStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Skill;
use App\Models\TrainingBudget;
use App\Models\TrainingCourse;
use App\Models\TrainingEnrolment;
use App\Models\TrainingProgramme;
use App\Models\TrainingProvider;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\Training\TrainingFileStore;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Development demo data of training, created through the real actions (approval
 * engine, capacity, attendance, completion → skills and certificates, audit) by
 * hr.manager@ (training.manage), dated as if each step happened then:
 *
 * - 4 providers, 2 programmes, 10 skills, 8 courses (safety, first aid, Excel and
 *   cloud issue certificates with a validity);
 * - past sessions: occupational safety in Nov 2025 (12-month certificates now expiring
 *   within 30 days) and Oct 2025 (certificates expired, one no-show), first aid,
 *   leadership (one participant failed for low attendance), advanced Excel (one no-show),
 *   cloud fundamentals with employee2@ (certificate valid three years);
 * - a negotiation course in progress (attendance of the first days recorded);
 * - upcoming: communication (nearly full + a manager nomination by department.manager@
 *   awaiting approval — approve as hr.manager@), cloud fundamentals with employee2@
 *   enrolled, customer service with employee1@'s own request awaiting approval, a request
 *   of employee3@ rejected and a withdrawn one, first aid (one seat left), and a cancelled
 *   leadership session;
 * - manual skill levels for many employees, an uploaded certificate (PDF) of employee1@;
 * - budgets of the current year: company-wide and four departments, sales over budget.
 */
class TrainingDemoSeeder extends Seeder
{
    private User $hr;

    /** @var array<string, Skill> */
    private array $skills = [];

    /** @var array<string, TrainingCourse> */
    private array $courses = [];

    /** @var list<int> */
    private array $used = [];

    public function run(): void
    {
        if (TrainingCourse::query()->exists() || ! Employee::query()->withoutGlobalScopes()->exists()) {
            return;
        }

        config(['queue.default' => 'sync']);
        Notification::fake();
        $this->hr = User::query()->where('email', 'hr.manager@hrmplus.test')->firstOrFail();
        auth('admin')->setUser($this->hr);
        mt_srand(20261120);

        try {
            DB::transaction(function (): void {
                $this->catalogue();
                $this->pastSessions();
                $this->currentAndUpcoming();
                $this->manualSkills();
                $this->budgets();
            });
        } finally {
            Carbon::setTestNow();
            auth('admin')->forgetUser();
        }

        $this->command?->info('Training demo: '.TrainingSession::query()->count().' sessions, '.TrainingEnrolment::query()->withoutGlobalScopes()->count().' enrolments.');
    }

    private function catalogue(): void
    {
        $providers = [];

        foreach ([
            ['ACADEMY', 'أكاديمية هورايزن', 'Horizon Academy', 'internal', 'Training desk', 'academy@hrmplus.test'],
            ['NILE-TP', 'شركاء النيل للتدريب', 'Nile Training Partners', 'external', 'Mona Fathy', 'contact@niletraining.example'],
            ['SAFEWORK', 'معهد السلامة المهنية', 'SafeWork Institute', 'external', 'Karim Adel', 'info@safework.example'],
            ['CLOUDSK', 'مهارات السحابة أونلاين', 'Cloud Skills Online', 'external', null, 'support@cloudskills.example'],
        ] as [$code, $ar, $en, $type, $contact, $email]) {
            $providers[$code] = TrainingProvider::create([
                'code' => $code, 'name_ar' => $ar, 'name_en' => $en, 'type' => $type, 'contact_name' => $contact, 'email' => $email, 'is_active' => true,
            ]);
        }

        $leadership = TrainingProgramme::create(['code' => 'LEAD', 'name_ar' => 'تطوير القيادات', 'name_en' => 'Leadership development', 'description' => 'Courses for team leads and managers.', 'is_active' => true]);
        $technical = TrainingProgramme::create(['code' => 'TECH', 'name_ar' => 'التميز الفني', 'name_en' => 'Technical excellence', 'description' => 'Tools, systems and safety.', 'is_active' => true]);

        foreach ([
            ['LEAD', 'القيادة', 'Leadership', 'soft skills'], ['COMM', 'التواصل الفعال', 'Communication', 'soft skills'],
            ['NEGOT', 'التفاوض', 'Negotiation', 'soft skills'], ['SALES', 'البيع الاستشاري', 'Consultative selling', 'sales'],
            ['CUSTSERV', 'خدمة العملاء', 'Customer service', 'sales'], ['EXCEL', 'إكسل المتقدم', 'Advanced Excel', 'technical'],
            ['CLOUD', 'الحوسبة السحابية', 'Cloud computing', 'technical'], ['PHP', 'برمجة PHP', 'PHP development', 'technical'],
            ['SAFETY', 'السلامة المهنية', 'Occupational safety', 'safety'], ['FIRSTAID', 'الإسعافات الأولية', 'First aid', 'safety'],
        ] as [$code, $ar, $en, $category]) {
            $this->skills[$code] = Skill::create(['code' => $code, 'name_ar' => $ar, 'name_en' => $en, 'category' => $category, 'is_active' => true]);
        }

        $save = app(SaveTrainingCourse::class);

        foreach ([
            ['LEAD-101', 'أساسيات القيادة', 'Leadership essentials', $leadership, 'ACADEMY', 'classroom', '21', '1500', null, ['LEAD' => 3, 'COMM' => 3]],
            ['COMM-201', 'التواصل الفعال في العمل', 'Effective workplace communication', $leadership, 'NILE-TP', 'classroom', '8', '900', null, ['COMM' => 3]],
            ['EXCEL-ADV', 'إكسل المتقدم للتقارير', 'Advanced Excel for reporting', $technical, 'NILE-TP', 'online', '12', '600', 24, ['EXCEL' => 4]],
            ['SALES-NEG', 'التفاوض في المبيعات', 'Negotiation for sales', null, 'NILE-TP', 'classroom', '16', '2500', null, ['SALES' => 4, 'NEGOT' => 3]],
            ['SAFE-OSH', 'السلامة والصحة المهنية', 'Occupational safety and health', $technical, 'SAFEWORK', 'classroom', '14', '1200', 12, ['SAFETY' => 3]],
            ['FIRST-AID', 'الإسعافات الأولية', 'First aid at work', $technical, 'SAFEWORK', 'classroom', '8', '800', 24, ['FIRSTAID' => 3]],
            ['CLOUD-FND', 'أساسيات الحوسبة السحابية', 'Cloud fundamentals', $technical, 'CLOUDSK', 'online', '16', '3000', 36, ['CLOUD' => 3]],
            ['CS-EXC', 'التميز في خدمة العملاء', 'Customer service excellence', null, 'ACADEMY', 'blended', '6', '700', null, ['CUSTSERV' => 3, 'COMM' => 2]],
        ] as [$code, $ar, $en, $programme, $provider, $mode, $hours, $cost, $validity, $skills]) {
            $this->courses[$code] = $save->handle(null, [
                'code' => $code, 'name_ar' => $ar, 'name_en' => $en, 'training_programme_id' => $programme?->id,
                'training_provider_id' => $providers[$provider]->id, 'delivery_mode' => $mode, 'duration_hours' => $hours,
                'cost_per_participant' => $cost, 'issues_certificate' => $validity !== null, 'certificate_validity_months' => $validity, 'is_active' => true,
            ], collect($skills)->mapWithKeys(fn (int $level, string $skill): array => [$this->skills[$skill]->id => $level])->all());
        }
    }

    private function pastSessions(): void
    {
        $warehouse = $this->employees(['OPS-WH', 'OPS'], 10, ['employee3@hrmplus.test']);

        // Safety, November 2025: 12-month certificates expire within the next 30 days.
        $this->runSession('SAFE-OSH', '2025-11-24', '2025-11-25', $warehouse->take(6), ['location' => 'Alexandria warehouse — training room']);
        // Safety, October 2025: certificates already expired; one no-show.
        $this->runSession('SAFE-OSH', '2025-10-13', '2025-10-14', $this->employees(['OPS', 'SAL-ALX'], 4), ['location' => 'Alexandria branch'], noShow: 1);
        $this->runSession('FIRST-AID', '2025-12-08', '2025-12-08', $this->employees(['HR', 'EXEC', 'FIN'], 5), ['location' => 'Cairo HQ — room 3']);
        $this->runSession('LEAD-101', '2026-09-14', '2026-09-16', $this->managers(6), ['location' => 'Cairo HQ — board room', 'trainer' => 'Dr. Hany Farouk'], failed: 1);
        $this->runSession('EXCEL-ADV', '2026-10-05', '2026-10-06', $this->employees(['FIN', 'FIN-PAY', 'HR'], 8), ['meeting_url' => 'https://meet.example/excel-advanced'], noShow: 1);
        $this->runSession('CLOUD-FND', '2026-06-10', '2026-06-11', $this->employees(['IT'], 4, ['employee2@hrmplus.test']), ['meeting_url' => 'https://meet.example/cloud-fnd']);

        // Negotiation, in progress: started two days ago, attendance of the days so far.
        $start = CarbonImmutable::today()->subDays(2);
        $session = $this->schedule('SALES-NEG', $start->toDateString(), $start->addDays(2)->toDateString(), ['location' => 'Cairo HQ — room 1', 'capacity' => 8], $start->subWeeks(3));
        $sales = $this->employees(['SAL-CAI', 'SAL'], 6, ['employee1@hrmplus.test']);
        $this->at($start->subWeeks(2), fn () => $sales->each(fn (Employee $employee) => app(ManageTrainingEnrolment::class)->nominate($session, $employee, $this->hr)));

        foreach ([$start, $start->addDay()] as $day) {
            $this->at($day->setTime(17, 0), fn () => $this->attendance($session, $day->toDateString(), $sales->count() > 3 ? [$sales->last()->id => 'late'] : []));
        }
    }

    private function currentAndUpcoming(): void
    {
        $manage = app(ManageTrainingEnrolment::class);
        $today = CarbonImmutable::today();

        // Communication: 10 of 12 seats, plus a nomination by the IT department manager awaiting HR.
        $communication = $this->schedule('COMM-201', $today->addDays(12)->toDateString(), $today->addDays(12)->toDateString(), ['location' => 'Cairo HQ — room 2', 'capacity' => 12, 'trainer' => 'Mona Fathy'], $today->subDays(10));
        $this->at($today->subDays(8), fn () => $this->employees(['HR', 'HR-TA', 'SAL-CAI', 'FIN'], 10)->each(fn (Employee $employee) => $manage->nominate($communication, $employee, $this->hr)));
        $itManager = User::query()->where('email', 'department.manager@hrmplus.test')->first();

        if ($itManager !== null) {
            $this->at($today->subDays(2), function () use ($manage, $communication, $itManager): void {
                auth('admin')->setUser($itManager);
                $manage->nominate($communication, $this->employees(['IT'], 1)->first(), $itManager, 'Leads the support desk hand-over meetings.');
                auth('admin')->setUser($this->hr);
            });
        }

        // Cloud fundamentals in December: employee2@ enrolled by HR.
        $cloud = $this->schedule('CLOUD-FND', $today->addDays(25)->toDateString(), $today->addDays(26)->toDateString(), ['meeting_url' => 'https://meet.example/cloud-dec', 'capacity' => 10], $today->subDays(5));
        $employee2 = $this->accountEmployee('employee2@hrmplus.test');
        $this->at($today->subDays(4), function () use ($manage, $cloud, $employee2): void {
            if ($employee2 !== null) {
                $manage->nominate($cloud, $employee2, $this->hr, 'Second level of the cloud track.');
            }
            $this->employees(['IT'], 3)->each(fn (Employee $employee) => $manage->nominate($cloud, $employee, $this->hr));
        });

        // Customer service: own requests — employee1@ waiting, employee3@ rejected, one withdrawn.
        $service = $this->schedule('CS-EXC', $today->addDays(32)->toDateString(), $today->addDays(32)->toDateString(), ['location' => 'Cairo HQ — room 1', 'meeting_url' => 'https://meet.example/cs-excellence', 'capacity' => 15], $today->subDays(6));
        $this->at($today->subDays(3), function () use ($manage, $service): void {
            foreach (['employee3@hrmplus.test' => 'reject', 'employee1@hrmplus.test' => 'wait'] as $email => $outcome) {
                $user = User::query()->where('email', $email)->first();
                $employee = $user ? Employee::query()->withoutGlobalScopes()->find($user->employee_id) : null;

                if ($employee === null) {
                    continue;
                }

                $enrolment = $manage->request($service, $employee, $user, 'I deal with customers every day and want to improve.');

                if ($outcome === 'reject') {
                    $manage->decide($enrolment, $this->hr, false, 'This course is planned for front-office staff this quarter; please apply for the March session.');
                }
            }

            $withdrawn = $this->employees(['SAL-CAI'], 1)->first();
            $enrolment = $manage->nominate($service, $withdrawn, $this->hr);
            $manage->cancel($enrolment, $this->hr, 'Clashes with the end-of-year sales push.');
        });

        // First aid in January: one seat left.
        $firstAid = $this->schedule('FIRST-AID', $today->addDays(60)->toDateString(), $today->addDays(60)->toDateString(), ['location' => 'Giza — Smart Village', 'capacity' => 8], $today->subDays(7));
        $this->at($today->subDays(6), fn () => $this->employees(['OPS', 'IT', 'SAL-ALX'], 7)->each(fn (Employee $employee) => $manage->nominate($firstAid, $employee, $this->hr)));

        // A leadership session cancelled with its participants.
        $cancelled = $this->schedule('LEAD-101', $today->addDays(18)->toDateString(), $today->addDays(20)->toDateString(), ['location' => 'Cairo HQ — board room', 'capacity' => 10], $today->subDays(20));
        $this->at($today->subDays(15), fn () => $this->employees(['EXEC', 'SAL', 'OPS'], 3)->each(fn (Employee $employee) => $manage->nominate($cancelled, $employee, $this->hr)));
        $this->at($today->subDays(1), fn () => app(ManageTrainingSession::class)->cancel($cancelled, $this->hr, 'The trainer is unavailable; the session moves to next quarter.'));

        // A certificate employee1@ uploaded himself (licence, with a PDF).
        $employee1User = User::query()->where('email', 'employee1@hrmplus.test')->first();

        if ($employee1User?->employee_id !== null) {
            $files = app(TrainingFileStore::class);
            $certificate = app(ManageTrainingCertificate::class)->store(Employee::query()->withoutGlobalScopes()->find($employee1User->employee_id), [
                'type' => 'licence', 'name' => 'Professional driving licence', 'issuer' => 'Traffic department', 'certificate_number' => 'DL-2023-48812',
                'issued_on' => '2023-02-14', 'expires_on' => $today->addDays(20)->toDateString(),
            ], null, $employee1User);
            $certificate->forceFill($files->storeContents($certificate->employee_id, $this->pdf('Professional driving licence'), 'driving-licence.pdf', 'application/pdf'))->save();
        }
    }

    private function manualSkills(): void
    {
        $set = app(SetEmployeeSkill::class);
        $codes = array_keys($this->skills);

        Employee::query()->withoutGlobalScopes()->where('status', EmployeeStatus::Active)->orderBy('id')->get()
            ->each(function (Employee $employee, int $index) use ($set, $codes): void {
                if ($index % 3 === 2) {
                    return;
                }

                foreach (array_slice($codes, $index % count($codes), 2) as $offset => $code) {
                    if (! $employee->exists || $this->hasSkill($employee, $code)) {
                        continue;
                    }

                    $set->handle($employee, $this->skills[$code], 1 + (($index + $offset) % 4), $this->hr);
                }
            });
    }

    private function budgets(): void
    {
        $year = (int) now()->format('Y');
        $departments = Department::query()->withoutGlobalScopes()->pluck('id', 'code');

        TrainingBudget::create(['year' => $year, 'department_id' => null, 'amount' => '120000.00', 'notes' => 'Approved in the annual plan.', 'created_by' => $this->hr->id]);

        foreach (['SAL-CAI' => '9000.00', 'IT' => '25000.00', 'FIN' => '8000.00', 'OPS-WH' => '12000.00'] as $code => $amount) {
            if (isset($departments[$code])) {
                TrainingBudget::create(['year' => $year, 'department_id' => $departments[$code], 'amount' => $amount, 'created_by' => $this->hr->id]);
            }
        }
    }

    /**
     * Schedule, enrol (HR), record attendance for every day and the outcomes of a past session.
     *
     * @param  Collection<int, Employee>  $participants
     * @param  array<string, mixed>  $extra
     */
    private function runSession(string $course, string $start, string $end, Collection $participants, array $extra, int $noShow = 0, int $failed = 0): void
    {
        $from = CarbonImmutable::parse($start);
        $session = $this->schedule($course, $start, $end, ['capacity' => max(8, $participants->count() + 2), ...$extra], $from->subWeeks(4));
        $manage = app(ManageTrainingEnrolment::class);

        $this->at($from->subWeeks(3), fn () => $participants->each(fn (Employee $employee) => $manage->nominate($session, $employee, $this->hr)));

        $noShows = $participants->take(-$noShow - $failed)->take($noShow)->pluck('id')->all();
        $failures = $participants->take(-$failed)->pluck('id')->all();
        $failures = $failed > 0 ? $failures : [];

        foreach ($session->days() as $index => $day) {
            $absent = array_fill_keys($noShows, 'absent');

            foreach ($failures as $id) {
                $absent[$id] = $index === 0 ? 'present' : 'absent';
            }

            $this->at(CarbonImmutable::parse($day)->setTime(17, 0), fn () => $this->attendance($session, $day, $absent));
        }

        $this->at(CarbonImmutable::parse($end)->addDay(), function () use ($session, $manage, $noShows, $failures): void {
            TrainingEnrolment::query()->withoutGlobalScopes()->where('training_session_id', $session->id)->get()
                ->each(function (TrainingEnrolment $enrolment) use ($manage, $noShows, $failures): void {
                    $outcome = match (true) {
                        in_array($enrolment->employee_id, $noShows, true) => TrainingEnrolmentStatus::NoShow,
                        in_array($enrolment->employee_id, $failures, true) => TrainingEnrolmentStatus::Failed,
                        default => TrainingEnrolmentStatus::Completed,
                    };
                    $score = $outcome === TrainingEnrolmentStatus::Completed ? (string) (70 + mt_rand(0, 28)) : null;
                    $manage->recordOutcome($enrolment, $outcome, $score, null, $this->hr);
                });

            app(ManageTrainingSession::class)->complete($session->refresh(), $this->hr);
        });
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function schedule(string $course, string $start, string $end, array $extra, CarbonImmutable $createdAt): TrainingSession
    {
        $courseModel = $this->courses[$course];
        $session = null;

        $this->at($createdAt, function () use (&$session, $courseModel, $start, $end, $extra): void {
            $session = app(ManageTrainingSession::class)->create([
                'training_course_id' => $courseModel->id, 'start_date' => $start, 'end_date' => $end, 'start_time' => '09:00', 'end_time' => '16:00',
                'delivery_mode' => $courseModel->delivery_mode->value, 'capacity' => 12, 'trainer' => null, ...$extra,
            ], $this->hr);
        });

        return $session;
    }

    /**
     * @param  array<int, string>  $exceptions  employee id => status (everyone else present)
     */
    private function attendance(TrainingSession $session, string $day, array $exceptions): void
    {
        $rows = TrainingEnrolment::query()->withoutGlobalScopes()->where('training_session_id', $session->id)
            ->where('status', TrainingEnrolmentStatus::Enrolled)->get()
            ->mapWithKeys(fn (TrainingEnrolment $enrolment): array => [$enrolment->id => ['status' => $exceptions[$enrolment->employee_id] ?? 'present']])
            ->all();

        app(RecordTrainingAttendance::class)->handle($session, $day, $rows, $this->hr);
    }

    /**
     * Active employees of the departments not used yet (accounts named first).
     *
     * @param  list<string>  $departments
     * @param  list<string>  $accounts
     * @return Collection<int, Employee>
     */
    private function employees(array $departments, int $count, array $accounts = []): Collection
    {
        $ids = Department::query()->withoutGlobalScopes()->whereIn('code', $departments)->pluck('id');
        $first = collect($accounts)->map(fn (string $email) => $this->accountEmployee($email))->filter();

        $others = Employee::query()->withoutGlobalScopes()->where('status', EmployeeStatus::Active)->whereIn('department_id', $ids)
            ->whereNotIn('id', [...$this->used, ...$first->pluck('id')])->orderBy('employee_number')->limit(max(0, $count - $first->count()))->get();

        if ($first->count() + $others->count() < $count) {
            // Not enough left in those departments: top up with any active employee not used yet.
            $others = $others->merge(Employee::query()->withoutGlobalScopes()->where('status', EmployeeStatus::Active)
                ->whereNotIn('id', [...$this->used, ...$first->pluck('id'), ...$others->pluck('id')])->orderBy('employee_number')
                ->limit($count - $first->count() - $others->count())->get());
        }

        if ($first->count() + $others->count() < $count) {
            // Everybody had a course already: people take several courses, so reuse them.
            $others = $others->merge(Employee::query()->withoutGlobalScopes()->where('status', EmployeeStatus::Active)
                ->whereNotIn('id', [...$first->pluck('id'), ...$others->pluck('id')])->orderByDesc('employee_number')
                ->limit($count - $first->count() - $others->count())->get());
        }

        $picked = $first->merge($others)->values();
        $this->used = [...$this->used, ...$picked->pluck('id')->all()];

        return $picked;
    }

    /**
     * @return Collection<int, Employee>
     */
    private function managers(int $count): Collection
    {
        $managerIds = Employee::query()->withoutGlobalScopes()->whereNotNull('manager_id')->distinct()->pluck('manager_id');

        $picked = Employee::query()->withoutGlobalScopes()->where('status', EmployeeStatus::Active)->whereIn('id', $managerIds)
            ->whereNotIn('id', $this->used)->orderBy('employee_number')->limit($count)->get();
        $this->used = [...$this->used, ...$picked->pluck('id')->all()];

        return $picked;
    }

    private function accountEmployee(string $email): ?Employee
    {
        $id = User::query()->where('email', $email)->value('employee_id');

        return $id ? Employee::query()->withoutGlobalScopes()->find($id) : null;
    }

    private function hasSkill(Employee $employee, string $code): bool
    {
        return $employee->getConnection()->table('employee_skills')->where('employee_id', $employee->id)->where('skill_id', $this->skills[$code]->id)->exists();
    }

    private function at(CarbonImmutable $moment, callable $callback): void
    {
        Carbon::setTestNow($moment->hour === 0 ? $moment->setTime(10, 0) : $moment);

        try {
            $callback();
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * A one-page PDF stating it is demo data.
     */
    private function pdf(string $title): string
    {
        $text = 'HRM Plus demo document: '.$title;
        $stream = 'BT /F1 14 Tf 72 720 Td ('.str_replace(['(', ')'], ['\(', '\)'], $text).') Tj ET';

        return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Resources<</Font<</F1 4 0 R>>>>/Contents 5 0 R>>endobj\n"
            ."4 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\n5 0 obj<</Length ".strlen($stream).">>stream\n".$stream."\nendstream endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
    }
}
