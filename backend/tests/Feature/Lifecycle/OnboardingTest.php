<?php

use App\Actions\Onboarding\StartOnboarding;
use App\Enums\OnboardingStatus;
use App\Enums\TaskStatus;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Onboarding;
use App\Models\OnboardingTemplate;
use App\Models\User;
use App\Notifications\Lifecycle\OnboardingStarted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedRecruitment();
    $this->org = hrStructure();
    $this->employee = Employee::factory()->inPosition($this->org['position'])->create(['employment_type_id' => $this->org['type']->id]);
});

function templateWith(array $attributes, array $tasks): OnboardingTemplate
{
    $template = OnboardingTemplate::create(['name_ar' => 'قالب', 'name_en' => 'Template', 'is_active' => true, ...$attributes]);

    foreach ($tasks as $index => [$title, $assignee, $offset]) {
        $template->tasks()->create(['title_ar' => $title, 'title_en' => $title, 'assignee' => $assignee, 'due_offset_days' => $offset, 'sort_order' => $index]);
    }

    return $template;
}

it('picks the most specific active template for the new hire', function () {
    templateWith(['department_id' => $this->org['department']->id], [['Department task', 'hr', 0]]);
    templateWith(['position_id' => $this->org['position']->id], [['Position task', 'it', 0]]);
    templateWith(['position_id' => $this->org['position']->id, 'is_active' => false], [['Inactive', 'it', 0]]);
    templateWith(['employment_type_id' => EmploymentType::factory()->create()->id], [['Other type', 'it', 0]]);

    $onboarding = app(StartOnboarding::class)->handle($this->employee);

    expect($onboarding->tasks()->pluck('title_en')->all())->toBe(['Position task']);
});

it('copies the template tasks with due dates relative to the start date and notifies the employee', function () {
    Notification::fake();
    $account = User::factory()->employee()->create(['employee_id' => $this->employee->id]);
    $template = templateWith([], [['Bring documents', 'employee', -2], ['Create accounts', 'it', 0], ['First week review', 'manager', 7]]);

    $this->actingAs(adminWithRoles('HR Manager'), 'admin')->post('/admin/onboarding', [
        'employee_id' => $this->employee->id, 'onboarding_template_id' => $template->id, 'start_date' => '2026-12-01',
    ])->assertSessionHasNoErrors();

    $onboarding = Onboarding::query()->sole();
    expect($onboarding->tasks->pluck('due_date')->map->toDateString()->all())->toBe(['2026-11-29', '2026-12-01', '2026-12-08']);
    Notification::assertSentTo($account, OnboardingStarted::class);

    // Editing the template later does not change the running onboarding.
    $template->tasks()->delete();
    expect($onboarding->tasks()->count())->toBe(3);

    // A second onboarding for the same employee is refused.
    $this->actingAs(superAdmin(), 'admin')->post('/admin/onboarding', ['employee_id' => $this->employee->id, 'start_date' => '2026-12-01'])
        ->assertSessionHasErrors('employee_id');
});

it('tracks progress, completes itself when nothing is pending and reopens when a task is reset', function () {
    $onboarding = app(StartOnboarding::class)->handle($this->employee, templateWith([], [['A', 'hr', 0], ['B', 'it', 0], ['C', 'manager', 0], ['D', 'hr', 0]]), null, Carbon::parse('2026-01-01'));
    [$a, $b, $c, $d] = $onboarding->tasks->all();
    $hr = adminWithRoles('HR Manager');

    $this->actingAs($hr, 'admin')->patch("/admin/onboarding/{$onboarding->id}/tasks/{$a->id}", ['status' => 'done'])->assertSessionHasNoErrors();
    $this->actingAs($hr, 'admin')->patch("/admin/onboarding/{$onboarding->id}/tasks/{$b->id}", ['status' => 'skipped'])->assertSessionHasNoErrors();
    expect($onboarding->fresh()->progress())->toBe(50)
        ->and($a->fresh()->completed_by)->toBe($hr->id)
        ->and($onboarding->fresh()->status)->toBe(OnboardingStatus::InProgress);

    foreach ([$c, $d] as $task) {
        $this->actingAs($hr, 'admin')->patch("/admin/onboarding/{$onboarding->id}/tasks/{$task->id}", ['status' => 'done']);
    }
    expect($onboarding->fresh()->status)->toBe(OnboardingStatus::Completed)->and($onboarding->fresh()->progress())->toBe(100);

    $this->actingAs($hr, 'admin')->patch("/admin/onboarding/{$onboarding->id}/tasks/{$d->id}", ['status' => 'pending']);
    expect($onboarding->fresh()->status)->toBe(OnboardingStatus::InProgress)->and($onboarding->fresh()->completed_at)->toBeNull();
});

it('lets the employee complete only their own tasks, in the portal and through the API', function () {
    $onboarding = app(StartOnboarding::class)->handle($this->employee, templateWith([], [['Read policies', 'employee', 1], ['Laptop', 'it', 0]]));
    [$own, $it] = $onboarding->tasks->all();
    $account = User::factory()->employee()->create(['employee_id' => $this->employee->id]);
    $stranger = User::factory()->employee()->create(['employee_id' => Employee::factory()->create()->id]);

    $this->actingAs($account, 'employee')->get('/portal/onboarding')->assertOk()->assertSee('Read policies')->assertSee('Laptop');
    $this->actingAs($account, 'employee')->patch("/portal/onboarding/tasks/{$it->id}", ['status' => 'done'])->assertNotFound();
    $this->actingAs($stranger, 'employee')->patch("/portal/onboarding/tasks/{$own->id}", ['status' => 'done'])->assertNotFound();
    $this->actingAs($account, 'employee')->patch("/portal/onboarding/tasks/{$own->id}", ['status' => 'done'])->assertRedirect();
    expect($own->fresh()->status)->toBe(TaskStatus::Done);

    $own->refresh()->update(['status' => TaskStatus::Pending]);
    $token = $account->createToken('phone')->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/me/onboarding')->assertOk()
        ->assertJsonPath('data.progress', 0)
        ->assertJsonPath('data.tasks.0.can_complete', true)
        ->assertJsonPath('data.tasks.1.can_complete', false);
    $this->withToken($token)->postJson("/api/v1/me/onboarding/tasks/{$it->id}/complete")->assertNotFound();
    $this->withToken($token)->postJson("/api/v1/me/onboarding/tasks/{$own->id}/complete")->assertOk()->assertJsonPath('data.progress', 50);
});

it('answers the API with null when the employee has no onboarding', function () {
    $account = User::factory()->employee()->create(['employee_id' => $this->employee->id]);

    $this->withToken($account->createToken('phone')->plainTextToken)->getJson('/api/v1/me/onboarding')
        ->assertOk()->assertJsonPath('success', true)->assertJsonPath('data', null);
});
