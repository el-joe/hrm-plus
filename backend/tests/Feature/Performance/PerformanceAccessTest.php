<?php

use App\Actions\Performance\ManagePerformanceCycle;
use App\Actions\Performance\ManagePerformanceGoal;
use App\Actions\Performance\ManagePerformanceReview;
use App\Enums\DataScopeType;
use App\Models\Branch;
use App\Models\Competency;
use App\Models\Employee;
use App\Models\PerformanceGoal;
use App\Models\PerformanceReview;
use App\Models\RatingScale;
use App\Models\User;
use App\Services\Performance\PerformanceVisibility;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Database\Seeders\PerformanceDefaultsSeeder;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

/*
| Who sees what (Phase 8a): managers see their reporting line only (direct + indirect
| reports), HR with performance.manage everything inside the data scope, nobody their own
| review in the admin area; configuration needs performance.manage; the portal and the API
| only ever show the signed-in employee's own goals and reviews.
*/

beforeEach(function () {
    seedRoles();
    $this->seed(PerformanceDefaultsSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00:00'));
    Notification::fake();

    $this->cairo = Branch::factory()->create(['code' => 'CAI']);
    $this->alex = Branch::factory()->create(['code' => 'ALX']);
    $place = fn (Branch $branch): array => ['branch_id' => $branch->id];

    // head → lead → member (Cairo); outsider reports to nobody in this line (Alexandria).
    $this->head = Employee::factory()->create(['employee_number' => 'H-1', ...$place($this->cairo)]);
    $this->lead = Employee::factory()->create(['employee_number' => 'L-1', 'manager_id' => $this->head->id, ...$place($this->cairo)]);
    $this->member = Employee::factory()->create(['employee_number' => 'M-1', 'manager_id' => $this->lead->id, ...$place($this->cairo)]);
    $this->outsider = Employee::factory()->create(['employee_number' => 'O-1', ...$place($this->alex)]);

    $this->headUser = User::factory()->admin()->withRoles('Direct Manager')->create(['employee_id' => $this->head->id]);
    $this->leadUser = User::factory()->admin()->withRoles('Direct Manager')->create(['employee_id' => $this->lead->id]);
    $this->memberUser = User::factory()->employee()->create(['employee_id' => $this->member->id]);
    $this->outsiderUser = User::factory()->employee()->create(['employee_id' => $this->outsider->id]);
    $this->hr = adminWithRoles('HR Manager');

    $ids = Competency::query()->pluck('id', 'code');
    $this->cycle = app(ManagePerformanceCycle::class)->save(null, [
        'code' => 'H2', 'name_ar' => 'دورة', 'name_en' => 'H2', 'type' => 'semi_annual', 'period_start' => '2026-07-01', 'period_end' => '2026-12-31',
        'rating_scale_id' => RatingScale::query()->where('code', 'STD5')->value('id'), 'goals_weight' => '70', 'competencies_weight' => '30',
        'self_assessment_enabled' => true, 'competencies' => [['competency_id' => $ids['CUST'], 'weight' => '100']],
    ], $this->hr);
    app(ManagePerformanceCycle::class)->launch($this->cycle, $this->hr);

    $this->reviewOf = fn (Employee $employee): PerformanceReview => PerformanceReview::query()->withoutGlobalScopes()->where('employee_id', $employee->id)->sole();
    $this->memberGoal = app(ManagePerformanceGoal::class)->create($this->member, ['performance_cycle_id' => $this->cycle->id, 'type' => 'goal', 'title' => 'Member goal', 'weight' => '100'], $this->hr);
    $this->outsiderGoal = app(ManagePerformanceGoal::class)->create($this->outsider, ['performance_cycle_id' => $this->cycle->id, 'type' => 'kpi', 'title' => 'Outsider KPI', 'weight' => '100', 'target_value' => '10'], $this->hr);
});

describe('manager scope', function () {
    it('computes the reporting line through every level', function () {
        expect(app(PerformanceVisibility::class)->reportingLine($this->headUser))->toEqualCanonicalizing([$this->lead->id, $this->member->id])
            ->and(app(PerformanceVisibility::class)->reportingLine($this->leadUser))->toBe([$this->member->id]);
    });

    it('lists only the reporting line for a manager, and never their own review', function () {
        $this->actingAs($this->headUser, 'admin')->get(route('admin.performance-reviews.index'))
            ->assertOk()
            ->assertSee(($this->reviewOf)($this->lead)->reference)
            ->assertSee(($this->reviewOf)($this->member)->reference)
            ->assertDontSee(($this->reviewOf)($this->head)->reference)
            ->assertDontSee(($this->reviewOf)($this->outsider)->reference);

        $this->actingAs($this->leadUser, 'admin')->get(route('admin.performance-reviews.show', ($this->reviewOf)($this->member)))->assertOk();
        $this->actingAs($this->leadUser, 'admin')->get(route('admin.performance-reviews.show', ($this->reviewOf)($this->head)))->assertForbidden();
        $this->actingAs($this->leadUser, 'admin')->get(route('admin.performance-reviews.show', ($this->reviewOf)($this->outsider)))->assertForbidden();
        $this->actingAs($this->leadUser, 'admin')->get(route('admin.performance-reviews.show', ($this->reviewOf)($this->lead)))->assertForbidden();
    });

    it('limits goals the same way', function () {
        $this->actingAs($this->leadUser, 'admin')->get(route('admin.performance-goals.index'))
            ->assertOk()->assertSee('Member goal')->assertDontSee('Outsider KPI');
        $this->actingAs($this->leadUser, 'admin')->get(route('admin.performance-goals.edit', $this->outsiderGoal))->assertForbidden();

        $this->actingAs($this->leadUser, 'admin')->post(route('admin.performance-goals.store'), [
            'employee_id' => $this->outsider->id, 'type' => 'goal', 'title' => 'Not mine', 'weight' => '10',
        ])->assertForbidden();
    });

    it('lets only the assigned reviewer (or HR) assess', function () {
        $review = ($this->reviewOf)($this->member);
        app(ManagePerformanceReview::class)->skipSelf($review, $this->hr);

        // The head sees the review (indirect report) but is not its reviewer.
        $this->actingAs($this->headUser, 'admin')->post(route('admin.performance-reviews.assess', $review), ['submit' => '0'])->assertForbidden();
        $this->actingAs($this->leadUser, 'admin')->post(route('admin.performance-reviews.assess', $review), ['submit' => '0'])->assertRedirect();
        $this->actingAs($this->hr, 'admin')->post(route('admin.performance-reviews.assess', $review), ['submit' => '0'])->assertRedirect();
    });
});

describe('HR and data scope', function () {
    it('shows HR every review in the data scope', function () {
        $this->actingAs($this->hr, 'admin')->get(route('admin.performance-reviews.index'))
            ->assertOk()->assertSee(($this->reviewOf)($this->outsider)->reference)->assertSee(($this->reviewOf)($this->head)->reference);

        $scoped = adminWithRoles('HR Manager');
        $scoped->dataScopes()->create(['scope_type' => DataScopeType::Branch, 'scope_id' => $this->alex->id]);

        $this->actingAs($scoped, 'admin')->get(route('admin.performance-reviews.index'))
            ->assertOk()->assertSee(($this->reviewOf)($this->outsider)->reference)->assertDontSee(($this->reviewOf)($this->member)->reference);
        $this->actingAs($scoped, 'admin')->get(route('admin.performance-reviews.show', ($this->reviewOf)($this->member)))->assertNotFound();
    });

    it('exports the visible reviews', function () {
        $this->actingAs($this->hr, 'admin')->get(route('admin.performance-reviews.export', ['format' => 'csv']))->assertOk()->assertDownload();
        $this->actingAs($this->leadUser, 'admin')->get(route('admin.performance-reviews.export'))->assertForbidden();
    });

    it('keeps configuration to performance.manage', function () {
        foreach (['admin.performance-cycles.index', 'admin.competencies.index', 'admin.rating-scales.index', 'admin.performance-cycles.create'] as $route) {
            $this->actingAs($this->leadUser, 'admin')->get(route($route))->assertForbidden();
            $this->actingAs($this->hr, 'admin')->get(route($route))->assertOk();
        }

        $this->actingAs($this->leadUser, 'admin')->post(route('admin.performance-cycles.close', $this->cycle))->assertForbidden();
        expect($this->cycle->refresh()->isActive())->toBeTrue();
    });

    it('needs performance.view for the review list', function () {
        $this->actingAs(adminWithRoles('Payroll Accountant'), 'admin')->get(route('admin.performance-reviews.index'))->assertForbidden();
        $this->actingAs(adminWithRoles('Payroll Accountant'), 'admin')->get(route('admin.performance-goals.index'))->assertForbidden();
    });
});

describe('portal ownership', function () {
    it('shows the employee only their own goals and reviews', function () {
        $this->actingAs($this->memberUser, 'employee')->get(route('portal.performance'))
            ->assertOk()->assertSee('Member goal')->assertDontSee('Outsider KPI');

        $this->actingAs($this->memberUser, 'employee')->get(route('portal.performance.reviews.show', ($this->reviewOf)($this->member)->id))->assertOk();
        $this->actingAs($this->memberUser, 'employee')->get(route('portal.performance.reviews.show', ($this->reviewOf)($this->outsider)->id))->assertNotFound();
        $this->actingAs($this->memberUser, 'employee')->post(route('portal.performance.reviews.self', ($this->reviewOf)($this->outsider)->id), [])->assertNotFound();
        $this->actingAs($this->memberUser, 'employee')->post(route('portal.performance.reviews.acknowledge', ($this->reviewOf)($this->outsider)->id))->assertNotFound();
        $this->actingAs($this->memberUser, 'employee')->post(route('portal.performance.goals.progress', $this->outsiderGoal->id), ['progress' => '50'])->assertNotFound();
    });

    it('lets the employee add goals to an open cycle only when allowed', function () {
        $this->actingAs($this->memberUser, 'employee')->post(route('portal.performance.goals.store'), [
            'performance_cycle_id' => $this->cycle->id, 'type' => 'goal', 'title' => 'My own goal', 'weight' => '0',
        ])->assertRedirect(route('portal.performance'));

        expect($this->member->refresh()->id)->toBe(PerformanceGoal::query()->withoutGlobalScopes()->where('title', 'My own goal')->value('employee_id'));

        app(SettingsService::class)->set('performance.employees_can_add_goals', false);
        $this->actingAs($this->memberUser, 'employee')->post(route('portal.performance.goals.store'), [
            'performance_cycle_id' => $this->cycle->id, 'type' => 'goal', 'title' => 'Blocked', 'weight' => '0',
        ])->assertForbidden();
    });

    it('hides the manager part until HR approved the review', function () {
        $review = ($this->reviewOf)($this->member);
        app(ManagePerformanceReview::class)->skipSelf($review, $this->hr);
        app(ManagePerformanceReview::class)->saveAssessment($review->refresh(), [
            'goals' => [$this->memberGoal->id => ['rating' => '4', 'comment' => 'Secret manager note']],
            'competencies' => $review->competencies()->pluck('id')->mapWithKeys(fn ($id) => [$id => ['rating' => '4']])->all(),
        ], $this->leadUser, true);

        $this->actingAs($this->memberUser, 'employee')->get(route('portal.performance.reviews.show', $review->id))
            ->assertOk()->assertDontSee('Secret manager note');

        Sanctum::actingAs($this->memberUser);
        $this->getJson(route('api.v1.me.performance.reviews.show', $review->id))
            ->assertOk()->assertJsonPath('data.overall_score', null)->assertJsonPath('data.final_rating', null)
            ->assertJsonPath('data.goals.0.manager_comment', null);
    });
});

describe('mobile API', function () {
    beforeEach(fn () => Sanctum::actingAs($this->memberUser));

    it('returns the summary, goals and reviews of the token owner', function () {
        $this->getJson(route('api.v1.me.performance.summary'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.active_goals', 1)
            ->assertJsonPath('data.summary.self_assessment_due', 1)
            ->assertJsonPath('data.active_goals.0.title', 'Member goal')
            ->assertJsonStructure(['data' => ['summary' => ['average_progress', 'latest_rating'], 'reviews' => [['id', 'reference', 'cycle' => ['id', 'name'], 'status', 'can_self_assess']]]]);

        $this->getJson(route('api.v1.me.performance.goals.index'))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.weight', '100.00')->assertJsonPath('meta.pagination.total', 1);

        $this->getJson(route('api.v1.me.performance.reviews.index'))->assertOk()->assertJsonCount(1, 'data');
    });

    it('takes progress and the self-assessment, and refuses other employees\' records', function () {
        $this->postJson(route('api.v1.me.performance.goals.progress', $this->memberGoal->id), ['progress' => '35', 'note' => 'Halfway'])
            ->assertOk()->assertJsonPath('data.progress', '35.00');
        $this->postJson(route('api.v1.me.performance.goals.progress', $this->outsiderGoal->id), ['progress' => '35'])->assertNotFound();

        $review = ($this->reviewOf)($this->member);
        $competency = $review->competencies()->value('id');

        $this->getJson(route('api.v1.me.performance.reviews.show', $review->id))
            ->assertOk()->assertJsonPath('data.can_self_assess', true)->assertJsonCount(5, 'data.scale')->assertJsonPath('data.goals.0.id', $this->memberGoal->id);

        $this->postJson(route('api.v1.me.performance.reviews.self', $review->id), ['goals' => [$this->memberGoal->id => ['rating' => '9']], 'competencies' => [$competency => ['rating' => '4']]])
            ->assertUnprocessable();

        $this->postJson(route('api.v1.me.performance.reviews.self', $review->id), [
            'goals' => [$this->memberGoal->id => ['rating' => '4']], 'competencies' => [$competency => ['rating' => '3']], 'comments' => 'Done',
        ])->assertOk()->assertJsonPath('data.status', 'manager_assessment')->assertJsonPath('data.self_overall_score', '3.70');

        $this->getJson(route('api.v1.me.performance.reviews.show', ($this->reviewOf)($this->outsider)->id))->assertNotFound();
        $this->postJson(route('api.v1.me.performance.reviews.acknowledge', $review->id))->assertUnprocessable();
    });

    it('refuses admin tokens', function () {
        Sanctum::actingAs($this->hr);
        $this->getJson(route('api.v1.me.performance.summary'))->assertForbidden();
    });
});
