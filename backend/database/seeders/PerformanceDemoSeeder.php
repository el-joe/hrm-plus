<?php

namespace Database\Seeders;

use App\Actions\Performance\ManagePerformanceCycle;
use App\Actions\Performance\ManagePerformanceGoal;
use App\Actions\Performance\ManagePerformanceReview;
use App\Enums\GoalStatus;
use App\Models\Competency;
use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceGoal;
use App\Models\PerformanceReview;
use App\Models\RatingScale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * Development demo data of performance management, through the real actions
 * (validation, scoring, approval engine, audit), dated as if it happened then:
 *
 * - ANN-2025: annual cycle 2025, company-wide, launched 2025-12-01 and closed
 *   2026-02-01. Every review has three goals, a self-assessment, a manager
 *   assessment by HR (hr.manager@), calibration by super.admin@ (a few calibrated)
 *   and the employee's acknowledgement — varied final ratings. The HR director's
 *   own review (EMP-00002) is cancelled ("reviewed by the board").
 * - H2-2026: semi-annual cycle July–December 2026, launched 2026-10-01, every review
 *   with goals / KPIs and progress updates. States to try:
 *     employee2@ (EMP-00015) self-assessment open (portal / API);
 *     employee1@ (EMP-00024) self-assessment submitted → manager assessment by
 *       direct.manager@ (his reviewer, EMP-00023) → approve as hr.manager@ → acknowledge as employee1@;
 *     EMP-00025 manager assessment (direct.manager@), EMP-00026 in HR calibration
 *       (approve as hr.manager@), EMP-00027 awaiting acknowledgement, EMP-00028 completed;
 *     EMP-00016 manager assessment for department.manager@ (IT);
 *     everybody else at self-assessment.
 * - PRB-2027Q1: a draft probation cycle (not launched).
 */
class PerformanceDemoSeeder extends Seeder
{
    private User $hr;

    private User $super;

    private ManagePerformanceGoal $goals;

    private ManagePerformanceReview $reviews;

    public function run(): void
    {
        if (PerformanceCycle::query()->exists() || ! Employee::query()->withoutGlobalScopes()->exists()) {
            return;
        }

        config(['queue.default' => 'sync']);
        Notification::fake();
        mt_srand(20261119);

        $this->hr = User::query()->where('email', 'hr.manager@hrmplus.test')->firstOrFail();
        $this->super = User::query()->where('email', 'super.admin@hrmplus.test')->firstOrFail();
        $this->goals = app(ManagePerformanceGoal::class);
        $this->reviews = app(ManagePerformanceReview::class);
        $scale = RatingScale::query()->where('code', 'STD5')->firstOrFail();

        $this->annual2025($scale);
        $this->halfYear2026($scale);
        $this->draftProbation($scale);

        Carbon::setTestNow();
        $this->command?->info('Performance demo: '.PerformanceCycle::query()->count().' cycles, '.PerformanceReview::query()->count().' reviews, '
            .PerformanceGoal::query()->count().' goals.');
    }

    private function annual2025(RatingScale $scale): void
    {
        $this->at('2025-11-20 10:00');
        $cycle = app(ManagePerformanceCycle::class)->save(null, [
            'code' => 'ANN-2025', 'name_ar' => 'التقييم السنوي 2025', 'name_en' => 'Annual review 2025', 'type' => 'annual',
            'period_start' => '2025-01-01', 'period_end' => '2025-12-31', 'rating_scale_id' => $scale->id,
            'goals_weight' => '70', 'competencies_weight' => '30', 'self_assessment_enabled' => true,
            'self_assessment_due' => '2025-12-20', 'manager_assessment_due' => '2026-01-15',
            'description' => 'Company-wide annual review of 2025.',
            'competencies' => $this->competencies(['CUST' => '25', 'TEAM' => '25', 'COMM' => '20', 'INTEG' => '30']),
        ], $this->hr);

        $this->at('2025-12-01 09:00');
        app(ManagePerformanceCycle::class)->launch($cycle, $this->hr);

        $titles = [
            ['تحقيق خطة العمل السنوية', 'Deliver the annual work plan'],
            ['تحسين جودة المخرجات', 'Improve the quality of deliverables'],
            ['إكمال خطة التطوير الشخصي', 'Complete the personal development plan'],
        ];

        foreach (PerformanceReview::query()->where('performance_cycle_id', $cycle->id)->with('employee')->orderBy('id')->get() as $index => $review) {
            $this->at('2025-12-02 09:00');

            foreach ([['40', 0], ['35', 1], ['25', 2]] as [$weight, $title]) {
                $goal = $this->goals->create($review->employee, [
                    'performance_cycle_id' => $cycle->id, 'type' => 'goal', 'title' => $titles[$title][1], 'weight' => $weight,
                    'start_date' => '2025-01-01', 'due_date' => '2025-12-31',
                ], $this->hr);
                $this->goals->recordProgress($goal, (string) mt_rand(70, 100), null, null, $this->hr);
            }

            if ($review->employee_id === $this->hr->employee_id) {
                $this->reviews->cancel($review->refresh(), $this->super, 'Reviewed by the board of directors outside the system.');

                continue;
            }

            // Ratings around a per-employee level so final ratings vary (1 … 5, mostly 3–4).
            $base = [2, 3, 3, 3, 4, 4, 4, 5, 3, 4, 2, 5][$index % 12];
            $this->at('2025-12-15 11:00');
            $this->reviews->submitSelf($review->refresh(), $this->ratings($review, min(5, $base + mt_rand(0, 1))), $this->employeeUser($review->employee_id));

            $this->at('2026-01-08 14:00');
            $this->reviews->saveAssessment($review->refresh(), [
                ...$this->ratings($review, $base),
                'strengths' => 'Reliable delivery and good cooperation with the team.',
                'improvements' => $base <= 2 ? 'Needs closer follow-up of deadlines and quality.' : 'Take more ownership of cross-team work.',
                'comments' => 'Discussed in the year-end meeting.',
            ], $this->hr, true);

            $this->at('2026-01-20 10:00');
            $review->refresh();
            $calibrate = $index % 9 === 4 ? $scale->levels()->where('value', min(5, $base + 1))->value('id') : null;
            $this->reviews->decide($review, $this->super, true, $calibrate ? 'Calibrated upwards in the HR calibration meeting (team results).' : null, $calibrate);

            $this->at('2026-01-27 09:30');
            $this->reviews->acknowledge($review->refresh(), $this->employeeUser($review->employee_id), $index % 5 === 0 ? 'Thank you for the feedback.' : null);
        }

        $this->at('2026-02-01 08:00');
        app(ManagePerformanceCycle::class)->close($cycle, $this->hr);
    }

    private function halfYear2026(RatingScale $scale): void
    {
        $this->at('2026-09-20 10:00');
        $cycle = app(ManagePerformanceCycle::class)->save(null, [
            'code' => 'H2-2026', 'name_ar' => 'تقييم النصف الثاني 2026', 'name_en' => 'H2 2026 review', 'type' => 'semi_annual',
            'period_start' => '2026-07-01', 'period_end' => '2026-12-31', 'rating_scale_id' => $scale->id,
            'goals_weight' => '70', 'competencies_weight' => '30', 'self_assessment_enabled' => true,
            'self_assessment_due' => '2026-11-30', 'manager_assessment_due' => '2026-12-20',
            'description' => 'Second-half review of 2026 with KPIs for sales and operations.',
            'competencies' => $this->competencies(['CUST' => '20', 'TEAM' => '20', 'PROB' => '20', 'TECH' => '20', 'PLAN' => '20']),
        ], $this->hr);

        $this->at('2026-10-01 09:00');
        app(ManagePerformanceCycle::class)->launch($cycle, $this->hr);

        $reviews = PerformanceReview::query()->where('performance_cycle_id', $cycle->id)->with('employee')->orderBy('id')->get()->keyBy('employee_id');

        foreach ($reviews as $review) {
            $this->at('2026-10-02 10:00');
            $this->goalsFor($review);
        }

        $direct = User::query()->where('email', 'direct.manager@hrmplus.test')->first();
        $itManager = User::query()->where('email', 'department.manager@hrmplus.test')->first();

        // Self-assessments submitted (now at the manager assessment).
        foreach ([24, 25, 26, 27, 28, 16] as $employeeId) {
            if ($review = $reviews->get($this->employeeId($employeeId))) {
                $this->at('2026-11-05 12:00');
                $this->reviews->submitSelf($review->refresh(), [...$this->ratings($review, 4), 'comments' => 'Good half-year; the Q4 targets are on track.'], $this->employeeUser($review->employee_id));
            }
        }

        // Assessed by the direct manager: 26 waits for HR calibration, 27 approved (acknowledgement), 28 completed.
        foreach ([26 => 3, 27 => 4, 28 => 5] as $employeeId => $rating) {
            $review = $reviews->get($this->employeeId($employeeId));

            if ($review === null || $direct === null) {
                continue;
            }

            $this->at('2026-11-08 15:00');
            $this->reviews->saveAssessment($review->refresh(), [
                ...$this->ratings($review, $rating),
                'strengths' => 'Strong customer relationships.',
                'improvements' => 'Pipeline reporting could be more regular.',
            ], $direct, true);

            if ($employeeId === 26) {
                continue;
            }

            $this->at('2026-11-10 10:00');
            $this->reviews->decide($review->refresh(), $this->hr, true);

            if ($employeeId === 28) {
                $this->at('2026-11-11 09:00');
                $this->reviews->acknowledge($review->refresh(), $this->employeeUser($review->employee_id), 'Agreed, thanks.');
            }
        }

        // A draft assessment by the IT manager for EMP-00016 (still at the manager step).
        if (($review = $reviews->get($this->employeeId(16))) && $itManager !== null) {
            $this->at('2026-11-09 16:00');
            $this->reviews->saveAssessment($review->refresh(), $this->ratings($review, 4, partial: true), $itManager, false);
        }
    }

    private function draftProbation(RatingScale $scale): void
    {
        $this->at('2026-11-12 09:00');
        app(ManagePerformanceCycle::class)->save(null, [
            'code' => 'PRB-2027Q1', 'name_ar' => 'تقييمات فترة الاختبار - الربع الأول 2027', 'name_en' => 'Probation reviews Q1 2027', 'type' => 'probation',
            'period_start' => '2027-01-01', 'period_end' => '2027-03-31', 'rating_scale_id' => $scale->id,
            'goals_weight' => '50', 'competencies_weight' => '50', 'self_assessment_enabled' => false,
            'competencies' => $this->competencies(['TEAM' => '30', 'TECH' => '40', 'INTEG' => '30']),
        ], $this->hr);
    }

    /**
     * Goals of the H2 2026 review: sales staff get KPIs, others plain goals; progress updates on each.
     */
    private function goalsFor(PerformanceReview $review): void
    {
        $employee = $review->employee;
        $sales = in_array($employee->department?->code ?? Employee::query()->withoutGlobalScopes()->find($employee->id)?->department()->withoutGlobalScopes()->value('code'), ['SAL', 'SAL-CAI', 'SAL-ALX'], true);
        $creator = $this->hr;
        $rows = $sales ? [
            ['kpi', 'Sales revenue (EGP thousands)', '50', '600', 'EGP k', [180, 320, 410]],
            ['kpi', 'New customers signed', '30', '12', 'customers', [3, 6, 8]],
            ['goal', 'Keep the CRM pipeline up to date', '20', null, null, [40, 70]],
        ] : [
            ['goal', 'Deliver the H2 team objectives', '50', null, null, [30, 55, 70]],
            ['kpi', 'Tasks completed on time (%)', '30', '95', '%', [82, 88]],
            ['goal', 'Complete one training course', '20', null, null, [0, 50]],
        ];

        foreach ($rows as [$type, $title, $weight, $target, $unit, $updates]) {
            $goal = $this->goals->create($employee, [
                'performance_cycle_id' => $review->performance_cycle_id, 'type' => $type, 'title' => $title, 'weight' => $weight,
                'target_value' => $target, 'unit' => $unit, 'start_date' => '2026-07-01', 'due_date' => '2026-12-31',
            ], $creator);

            foreach ($updates as $step => $value) {
                $this->at(CarbonImmutable::parse('2026-10-05 10:00')->addWeeks($step * 2)->toDateTimeString());
                $type === 'kpi'
                    ? $this->goals->recordProgress($goal, null, (string) $value, $step === 0 ? 'Monthly update' : null, $creator)
                    : $this->goals->recordProgress($goal, (string) $value, null, null, $creator);
            }
        }
    }

    /**
     * @param  array<string, string>  $weights  competency code => weight
     * @return list<array{competency_id: int, weight: string}>
     */
    private function competencies(array $weights): array
    {
        $ids = Competency::query()->whereIn('code', array_keys($weights))->pluck('id', 'code');

        return collect($weights)->map(fn (string $weight, string $code): array => ['competency_id' => (int) $ids[$code], 'weight' => $weight])->values()->all();
    }

    /**
     * Ratings of every counted goal and competency around $base (±1, within 1–5).
     *
     * @return array{goals: array<int, array{rating: string, comment: ?string}>, competencies: array<int, array{rating: string, comment: ?string}>}
     */
    private function ratings(PerformanceReview $review, int $base, bool $partial = false): array
    {
        $rate = fn (): string => (string) max(1, min(5, $base + mt_rand(-1, 1) * (mt_rand(0, 2) === 0 ? 1 : 0)));
        $goals = [];
        $competencies = [];

        foreach ($review->goalsQuery()->where('status', '!=', GoalStatus::Cancelled->value)->get() as $index => $goal) {
            if ($partial && $index > 0) {
                break;
            }

            $goals[$goal->id] = ['rating' => $rate(), 'comment' => null];
        }

        foreach ($review->competencies()->get() as $index => $row) {
            if ($partial && $index > 1) {
                break;
            }

            $competencies[$row->id] = ['rating' => $rate(), 'comment' => null];
        }

        return ['goals' => $goals, 'competencies' => $competencies];
    }

    /**
     * The employee's portal account, or a transient stand-in (most demo employees have no login).
     */
    private function employeeUser(int $employeeId): User
    {
        return User::query()->where('employee_id', $employeeId)->first() ?? (new User)->forceFill(['employee_id' => $employeeId, 'name' => 'Employee']);
    }

    /**
     * Demo employee id from the employee number (EMP-000NN), so the demo does not depend on auto-increment ids.
     */
    private function employeeId(int $number): ?int
    {
        return Employee::query()->withoutGlobalScopes()->where('employee_number', 'EMP-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT))->value('id');
    }

    private function at(string $moment): void
    {
        Carbon::setTestNow(CarbonImmutable::parse($moment, (string) setting('localisation.timezone')));
    }
}
