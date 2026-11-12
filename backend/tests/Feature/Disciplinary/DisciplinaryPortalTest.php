<?php

use App\Actions\Disciplinary\ManageDisciplinaryAction;
use App\Actions\Disciplinary\ManageDisciplinaryCase;
use App\Enums\DisciplinaryNoteType;
use App\Models\DisciplinaryAction;
use App\Models\Employee;
use App\Models\User;
use App\Models\ViolationType;
use App\Notifications\Disciplinary\DisciplinaryNotification;
use Database\Seeders\DisciplinaryDefaultsSeeder;
use Illuminate\Support\Facades\Notification;

/*
| Portal "Disciplinary notices" (Phase 8a): own issued actions only — never pending
| proposals, investigation notes or the reporter; acknowledgement and one response.
*/

beforeEach(function () {
    seedRoles();
    $this->seed(DisciplinaryDefaultsSeeder::class);
    Notification::fake();

    $this->employee = Employee::factory()->create();
    $this->user = User::factory()->employee()->create(['employee_id' => $this->employee->id]);
    $this->hr = adminWithRoles('HR Manager');
    $this->approver = adminWithRoles('HR Manager');
});

function portalNotice(Employee $employee, bool $approve = true): DisciplinaryAction
{
    $case = app(ManageDisciplinaryCase::class)->report([
        'employee_id' => $employee->id, 'violation_type_id' => ViolationType::query()->where('code', 'LATE')->value('id'),
        'incident_date' => now()->subDay()->toDateString(), 'description' => 'Secret investigation narrative.',
    ], test()->hr);
    app(ManageDisciplinaryCase::class)->addNote($case, DisciplinaryNoteType::Note, 'Internal investigation note.', test()->hr);
    $action = app(ManageDisciplinaryAction::class)->propose($case->refresh(), [
        'type' => 'verbal_warning', 'details' => 'Please respect the working hours.', 'effective_date' => now()->toDateString(),
    ], test()->hr);

    if ($approve) {
        app(ManageDisciplinaryAction::class)->decide($action, test()->approver, true);
    }

    return $action->refresh();
}

it('lists and shows the employee\'s issued notices without investigation details', function () {
    $notice = portalNotice($this->employee);

    $this->actingAs($this->user, 'employee')->get(route('portal.disciplinary'))
        ->assertOk()->assertSee($notice->disciplinaryCase->reference);

    $this->actingAs($this->user, 'employee')->get(route('portal.disciplinary.show', $notice->id))
        ->assertOk()
        ->assertSee('Please respect the working hours.')
        ->assertDontSee('Secret investigation narrative.')
        ->assertDontSee('Internal investigation note.')
        ->assertDontSee($this->hr->name);
});

it('never shows proposals that are not issued', function () {
    $pending = portalNotice($this->employee, approve: false);

    $this->actingAs($this->user, 'employee')->get(route('portal.disciplinary.show', $pending->id))->assertNotFound();
    $this->actingAs($this->user, 'employee')->get(route('portal.disciplinary'))->assertDontSee($pending->disciplinaryCase->reference);
});

it('never shows another employee\'s notice', function () {
    $other = portalNotice(Employee::factory()->create());

    $this->actingAs($this->user, 'employee')->get(route('portal.disciplinary.show', $other->id))->assertNotFound();
    $this->actingAs($this->user, 'employee')->post(route('portal.disciplinary.respond', $other->id), ['response' => 'Not mine'])->assertNotFound();

    expect($other->refresh()->employee_response)->toBeNull();
});

it('records the acknowledgement and one response and tells HR', function () {
    $notice = portalNotice($this->employee);

    $this->actingAs($this->user, 'employee')->post(route('portal.disciplinary.acknowledge', $notice->id))->assertRedirect();
    expect($notice->refresh()->acknowledged_at)->not->toBeNull();

    $this->actingAs($this->user, 'employee')
        ->post(route('portal.disciplinary.respond', $notice->id), ['response' => 'I understand and will improve.'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($notice->refresh()->employee_response)->toBe('I understand and will improve.');
    Notification::assertSentTo($this->hr, DisciplinaryNotification::class, fn ($notification) => $notification->kind === 'responded');

    $this->actingAs($this->user, 'employee')
        ->post(route('portal.disciplinary.respond', $notice->id), ['response' => 'Changed my mind.'])
        ->assertSessionHasErrors('response');

    expect($notice->refresh()->employee_response)->toBe('I understand and will improve.');
});

it('shows the portal menu entry only to employees with a notice', function () {
    expect($this->user->can('viewOwnDisciplinary'))->toBeFalse();

    portalNotice($this->employee);

    expect($this->user->fresh()->can('viewOwnDisciplinary'))->toBeTrue();
});
