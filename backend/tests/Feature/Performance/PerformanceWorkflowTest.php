<?php

use App\Actions\Performance\ManagePerformanceCycle;
use App\Actions\Performance\ManagePerformanceGoal;
use App\Actions\Performance\ManagePerformanceReview;
use App\Enums\ApprovalStatus;
use App\Enums\GoalStatus;
use App\Enums\PerformanceCycleStatus;
use App\Enums\PerformanceReviewStatus;
use App\Models\Competency;
use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceGoal;
use App\Models\PerformanceReview;
use App\Models\RatingScale;
use App\Models\User;
use App\Notifications\Performance\PerformanceReviewNotification;
use App\Services\Performance\AcknowledgementReminders;
use Carbon\CarbonImmutable;
use Database\Seeders\PerformanceDefaultsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/*
| Performance reviews (Phase 8a): cycle launch, goals and KPIs, self-assessment (portal),
| manager assessment (admin), HR calibration through the approval engine (override of the
| final band), rejection back to the manager, acknowledgement, reminders.
*/

/**
 * A cycle through the real action: STD5 scale, 70 % goals / 30 % competencies CUST 50 + TEAM 50.
 *
 * @param  array<string, mixed>  $attributes
 */
function performanceCycle(array $attributes = [], ?array $competencies = null): PerformanceCycle
{
    $ids = Competency::query()->pluck('id', 'code');

    return app(ManagePerformanceCycle::class)->save(null, [
        'code' => 'CYC-'.uniqid(), 'name_ar' => 'دورة', 'name_en' => 'Cycle', 'type' => 'semi_annual',
        'period_start' => '2026-07-01', 'period_end' => '2026-12-31',
        'rating_scale_id' => RatingScale::query()->where('code', 'STD5')->value('id'),
        'goals_weight' => '70', 'competencies_weight' => '30', 'self_assessment_enabled' => true,
        'competencies' => $competencies ?? [['competency_id' => $ids['CUST'], 'weight' => '50'], ['competency_id' => $ids['TEAM'], 'weight' => '50']],
        ...$attributes,
    ], test()->hr);
}

/**
 * @param  array<int, string>  $goalRatings  goal id => rating
 * @param  array<string, string>  $competencyRatings  competency code => rating
 * @return array<string, mixed>
 */
function reviewRatings(PerformanceReview $review, array $goalRatings, array $competencyRatings): array
{
    return [
        'goals' => collect($goalRatings)->map(fn (string $rating): array => ['rating' => $rating, 'comment' => 'ok'])->all(),
        'competencies' => $review->competencies()->with('competency')->get()
            ->mapWithKeys(fn ($row): array => [$row->id => ['rating' => $competencyRatings[$row->competency->code] ?? null, 'comment' => null]])->all(),
    ];
}

beforeEach(function () {
    seedRoles();
    $this->seed(PerformanceDefaultsSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00:00'));
    Notification::fake();

    $this->manager = Employee::factory()->create(['employee_number' => 'M-1']);
    $this->employee = Employee::factory()->create([
        'employee_number' => 'E-1', 'manager_id' => $this->manager->id, 'branch_id' => $this->manager->branch_id, 'department_id' => $this->manager->department_id,
    ]);
    $this->managerUser = User::factory()->admin()->withRoles('Direct Manager')->create(['employee_id' => $this->manager->id]);
    $this->employeeUser = User::factory()->employee()->create(['employee_id' => $this->employee->id]);
    $this->hr = adminWithRoles('HR Manager');
});

it('launches a cycle: one review per active employee, reviewer = manager, competencies copied', function () {
    Employee::factory()->create(['status' => 'terminated', 'branch_id' => $this->manager->branch_id]);
    $cycle = performanceCycle(['branch_id' => $this->manager->branch_id]);

    $count = app(ManagePerformanceCycle::class)->launch($cycle, $this->hr);
    $review = PerformanceReview::query()->where('employee_id', $this->employee->id)->sole();

    expect($count)->toBe(2)
        ->and($cycle->refresh()->status)->toBe(PerformanceCycleStatus::Active)
        ->and($review->status)->toBe(PerformanceReviewStatus::SelfAssessment)
        ->and($review->reviewer_id)->toBe($this->manager->id)
        ->and($review->reference)->toStartWith('PRV-2026-')
        ->and($review->competencies()->pluck('weight')->all())->toBe(['50.00', '50.00']);

    Notification::assertSentTo($this->employeeUser, PerformanceReviewNotification::class, fn ($notification) => $notification->kind === 'launched');
    expect(fn () => app(ManagePerformanceCycle::class)->launch($cycle->refresh(), $this->hr))->toThrow(ValidationException::class);
});

it('refuses a split or competency weights that do not total 100', function () {
    $ids = Competency::query()->pluck('id', 'code');

    expect(fn () => performanceCycle(['goals_weight' => '60', 'competencies_weight' => '30']))->toThrow(ValidationException::class)
        ->and(fn () => performanceCycle([], [['competency_id' => $ids['CUST'], 'weight' => '60'], ['competency_id' => $ids['TEAM'], 'weight' => '30']]))->toThrow(ValidationException::class);
});

it('reviews only employees whose probation ends inside a probation cycle', function () {
    $onProbation = Employee::factory()->create(['probation_end_date' => '2026-08-15', 'branch_id' => $this->manager->branch_id]);
    $cycle = performanceCycle(['type' => 'probation', 'branch_id' => $this->manager->branch_id]);

    expect(app(ManagePerformanceCycle::class)->participants($cycle)->pluck('id')->all())->toBe([$onProbation->id]);
});

describe('the review workflow', function () {
    beforeEach(function () {
        $this->cycle = performanceCycle(['branch_id' => $this->manager->branch_id]);
        app(ManagePerformanceCycle::class)->launch($this->cycle, $this->hr);
        $this->review = PerformanceReview::query()->where('employee_id', $this->employee->id)->sole();
        $goals = app(ManagePerformanceGoal::class);
        $this->goalA = $goals->create($this->employee, ['performance_cycle_id' => $this->cycle->id, 'type' => 'goal', 'title' => 'Deliver', 'weight' => '60'], $this->managerUser);
        $this->goalB = $goals->create($this->employee, ['performance_cycle_id' => $this->cycle->id, 'type' => 'kpi', 'title' => 'Revenue', 'weight' => '40', 'target_value' => '100', 'unit' => 'k'], $this->managerUser);
    });

    it('runs self → manager → calibration (override) → acknowledgement', function () {
        $this->actingAs($this->employeeUser, 'employee')
            ->post(route('portal.performance.reviews.self', $this->review->id), [
                ...reviewRatings($this->review, [$this->goalA->id => '4', $this->goalB->id => '4'], ['CUST' => '4', 'TEAM' => '4']),
                'comments' => 'A good half-year.',
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->review->refresh();
        expect($this->review->status)->toBe(PerformanceReviewStatus::ManagerAssessment)
            ->and($this->review->self_overall_score)->toBe('4.00');
        Notification::assertSentTo($this->managerUser, PerformanceReviewNotification::class, fn ($notification) => $notification->kind === 'self_submitted');

        // goals (4×60 + 3×40)/100 = 3.60; competencies (4 + 5)/2 = 4.50; overall 3.60×70% + 4.50×30% = 3.87 → level 4.
        $this->actingAs($this->managerUser, 'admin')
            ->post(route('admin.performance-reviews.assess', $this->review), [
                ...reviewRatings($this->review, [$this->goalA->id => '4', $this->goalB->id => '3'], ['CUST' => '4', 'TEAM' => '5']),
                'strengths' => 'Ownership', 'submit' => '1',
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->review->refresh();
        expect($this->review->status)->toBe(PerformanceReviewStatus::PendingApproval)
            ->and($this->review->goals_score)->toBe('3.60')
            ->and($this->review->competencies_score)->toBe('4.50')
            ->and($this->review->overall_score)->toBe('3.87')
            ->and($this->review->finalRating->value)->toBe('4.00')
            ->and($this->review->latestApprovalRequest->status)->toBe(ApprovalStatus::Pending);
        Notification::assertSentTo($this->hr, PerformanceReviewNotification::class, fn ($notification) => $notification->kind === 'awaiting_approval');

        $top = $this->cycle->ratingScale->levels()->where('value', '5')->value('id');

        // A changed band needs a note.
        $this->actingAs($this->hr, 'admin')
            ->post(route('admin.performance-reviews.decide', [$this->review, 'approve']), ['calibrated_level_id' => $top])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->hr, 'admin')
            ->post(route('admin.performance-reviews.decide', [$this->review, 'approve']), ['calibrated_level_id' => $top, 'reason' => 'Team results'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->review->refresh();
        expect($this->review->status)->toBe(PerformanceReviewStatus::Acknowledgement)
            ->and($this->review->calibrated)->toBeTrue()
            ->and($this->review->calibration_note)->toBe('Team results')
            ->and($this->review->finalRating->value)->toBe('5.00')
            ->and($this->review->overall_score)->toBe('3.87')
            ->and($this->review->approved_by)->toBe($this->hr->id);
        Notification::assertSentTo($this->employeeUser, PerformanceReviewNotification::class, fn ($notification) => $notification->kind === 'approved');

        $this->actingAs($this->employeeUser, 'employee')
            ->post(route('portal.performance.reviews.acknowledge', $this->review->id), ['comment' => 'Thanks'])
            ->assertRedirect();

        expect($this->review->refresh()->status)->toBe(PerformanceReviewStatus::Completed)
            ->and($this->review->employee_comment)->toBe('Thanks')
            ->and($this->review->acknowledged_at)->not->toBeNull();
    });

    it('returns a rejected review to the manager with the reason', function () {
        $manage = app(ManagePerformanceReview::class);
        $manage->skipSelf($this->review, $this->hr);
        $manage->saveAssessment($this->review->refresh(), reviewRatings($this->review, [$this->goalA->id => '3', $this->goalB->id => '3'], ['CUST' => '3', 'TEAM' => '3']), $this->managerUser, true);

        $this->actingAs($this->hr, 'admin')
            ->post(route('admin.performance-reviews.decide', [$this->review, 'reject']), ['reason' => 'Add evidence'])
            ->assertRedirect();

        $this->review->refresh();
        expect($this->review->status)->toBe(PerformanceReviewStatus::ManagerAssessment)
            ->and($this->review->approvalRequests()->latest('id')->first()->status)->toBe(ApprovalStatus::Rejected);
        Notification::assertSentTo($this->managerUser, PerformanceReviewNotification::class, fn ($notification) => $notification->kind === 'returned' && $notification->reason === 'Add evidence');

        // Resubmission starts a new approval run.
        $manage->saveAssessment($this->review, reviewRatings($this->review, [$this->goalA->id => '4', $this->goalB->id => '3'], ['CUST' => '3', 'TEAM' => '3']), $this->managerUser, true);
        expect($this->review->refresh()->status)->toBe(PerformanceReviewStatus::PendingApproval)
            ->and($this->review->approvalRequests()->count())->toBe(2);
    });

    it('requires every item rated and goal weights totalling 100 to submit', function () {
        $manage = app(ManagePerformanceReview::class);
        $manage->skipSelf($this->review, $this->hr);

        expect(fn () => $manage->saveAssessment($this->review->refresh(), reviewRatings($this->review, [$this->goalA->id => '4'], ['CUST' => '4', 'TEAM' => '4']), $this->managerUser, true))
            ->toThrow(ValidationException::class);

        $this->goalB->update(['weight' => '30']);
        expect(fn () => $manage->saveAssessment($this->review->refresh(), reviewRatings($this->review, [$this->goalA->id => '4', $this->goalB->id => '4'], ['CUST' => '4', 'TEAM' => '4']), $this->managerUser, true))
            ->toThrow(ValidationException::class);

        // A value that is not a level of the scale.
        $this->goalB->update(['weight' => '40']);
        expect(fn () => $manage->saveAssessment($this->review->refresh(), reviewRatings($this->review, [$this->goalA->id => '4.5', $this->goalB->id => '4'], ['CUST' => '4', 'TEAM' => '4']), $this->managerUser, true))
            ->toThrow(ValidationException::class)
            ->and($this->review->refresh()->status)->toBe(PerformanceReviewStatus::ManagerAssessment);
    });

    it('leaves cancelled goals out of weights and scores', function () {
        $manage = app(ManagePerformanceReview::class);
        $manage->skipSelf($this->review, $this->hr);
        $extra = app(ManagePerformanceGoal::class)->create($this->employee, ['performance_cycle_id' => $this->cycle->id, 'type' => 'goal', 'title' => 'Dropped', 'weight' => '20'], $this->managerUser);
        app(ManagePerformanceGoal::class)->changeStatus($extra, GoalStatus::Cancelled, $this->managerUser);

        $manage->saveAssessment($this->review->refresh(), reviewRatings($this->review, [$this->goalA->id => '5', $this->goalB->id => '5'], ['CUST' => '5', 'TEAM' => '5']), $this->managerUser, true);

        expect($this->review->refresh()->goals_score)->toBe('5.00');
    });

    it('never lets the submitting manager or the employee approve', function () {
        // One account per employee (users.employee_id is unique): the admin account replaces the portal one.
        $this->employeeUser->forceFill(['employee_id' => null])->save();
        $hrEmployee = User::factory()->admin()->withRoles('HR Manager')->create(['employee_id' => $this->employee->id]);
        $manage = app(ManagePerformanceReview::class);
        $manage->skipSelf($this->review, $this->hr);
        $manage->saveAssessment($this->review->refresh(), reviewRatings($this->review, [$this->goalA->id => '3', $this->goalB->id => '3'], ['CUST' => '3', 'TEAM' => '3']), $this->hr, true);

        // The submitter (self-approval off) and the reviewed employee get a 403.
        $this->actingAs($this->hr, 'admin')->post(route('admin.performance-reviews.decide', [$this->review, 'approve']))->assertForbidden();
        $this->actingAs($hrEmployee, 'admin')->post(route('admin.performance-reviews.decide', [$this->review, 'approve']))->assertForbidden();

        expect($this->review->refresh()->status)->toBe(PerformanceReviewStatus::PendingApproval);
    });

    it('locks the goals of a review once submitted', function () {
        $manage = app(ManagePerformanceReview::class);
        $manage->skipSelf($this->review, $this->hr);
        $manage->saveAssessment($this->review->refresh(), reviewRatings($this->review, [$this->goalA->id => '3', $this->goalB->id => '3'], ['CUST' => '3', 'TEAM' => '3']), $this->managerUser, true);

        expect(fn () => app(ManagePerformanceGoal::class)->update($this->goalA->refresh(), ['type' => 'goal', 'title' => 'Changed', 'weight' => '60'], $this->hr))
            ->toThrow(ValidationException::class);
    });

    it('records KPI progress from the actual value with history', function () {
        app(ManagePerformanceGoal::class)->recordProgress($this->goalB, null, '45', 'October', $this->managerUser);

        expect($this->goalB->refresh()->progress)->toBe('45.00')
            ->and($this->goalB->current_value)->toBe('45.00')
            ->and($this->goalB->updates()->sole()->note)->toBe('October');

        app(ManagePerformanceGoal::class)->recordProgress($this->goalA, '100', null, null, $this->managerUser);
        expect($this->goalA->refresh()->status)->toBe(GoalStatus::Completed);
    });

    it('completes the cycle goals when the cycle closes', function () {
        app(ManagePerformanceCycle::class)->close($this->cycle->refresh(), $this->hr);

        expect($this->cycle->refresh()->status)->toBe(PerformanceCycleStatus::Closed)
            ->and(PerformanceGoal::query()->where('performance_cycle_id', $this->cycle->id)->pluck('status')->unique()->all())->toBe([GoalStatus::Completed])
            ->and(fn () => app(ManagePerformanceGoal::class)->create($this->employee, ['performance_cycle_id' => $this->cycle->id, 'type' => 'goal', 'title' => 'Late', 'weight' => '10'], $this->hr))
            ->toThrow(ValidationException::class);
    });

    it('reminds the employee to acknowledge every N days after approval', function () {
        $approver = adminWithRoles('HR Manager');
        $manage = app(ManagePerformanceReview::class);
        $manage->skipSelf($this->review, $this->hr);
        $manage->saveAssessment($this->review->refresh(), reviewRatings($this->review, [$this->goalA->id => '3', $this->goalB->id => '3'], ['CUST' => '3', 'TEAM' => '3']), $this->hr, true);
        $manage->decide($this->review->refresh(), $approver, true);
        Notification::fake();

        expect(app(AcknowledgementReminders::class)->send(CarbonImmutable::parse('2026-11-25')))->toBe(0)
            ->and(app(AcknowledgementReminders::class)->send(CarbonImmutable::parse('2026-11-26')))->toBe(1);
        Notification::assertSentTo($this->employeeUser, PerformanceReviewNotification::class, fn ($notification) => $notification->kind === 'acknowledgement_reminder');
    });
});
