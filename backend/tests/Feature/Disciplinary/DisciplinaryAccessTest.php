<?php

use App\Actions\Disciplinary\ManageDisciplinaryAction;
use App\Actions\Disciplinary\ManageDisciplinaryCase;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\DisciplinaryCase;
use App\Models\Employee;
use App\Models\User;
use App\Models\ViolationType;
use App\Services\Employees\SensitiveDataAccessLog;
use Database\Seeders\DisciplinaryDefaultsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/*
| Restricted access (Phase 8a): case files need disciplinary.view inside the data scope;
| reporters without it see only the status of what they reported; every view is logged
| as sensitive_access.disciplinary. Screens, export and violation types catalogue.
*/

beforeEach(function () {
    seedRoles();
    $this->seed(DisciplinaryDefaultsSeeder::class);
    Notification::fake();
    Storage::fake('disciplinary_files');

    $this->employee = Employee::factory()->create();
    $this->hr = adminWithRoles('HR Manager');
    $this->specialist = adminWithRoles('HR Specialist');
    $this->case = app(ManageDisciplinaryCase::class)->report([
        'employee_id' => $this->employee->id,
        'violation_type_id' => ViolationType::query()->where('code', 'CONDUCT')->value('id'),
        'incident_date' => now()->subDay()->toDateString(),
        'description' => 'Confidential incident narrative.',
    ], $this->hr, [UploadedFile::fake()->create('witness.pdf', 10, 'application/pdf')]);
});

function disciplinaryAccessLogs(User $user): int
{
    return AuditLog::query()->where('event', SensitiveDataAccessLog::DISCIPLINARY)->where('user_id', $user->id)->count();
}

it('shows the full case file to disciplinary viewers and logs the view', function () {
    $this->actingAs($this->hr, 'admin')
        ->get(route('admin.disciplinary-cases.show', $this->case))
        ->assertOk()
        ->assertSee('Confidential incident narrative.')
        ->assertSee('witness.pdf');

    expect(AuditLog::query()->where('event', SensitiveDataAccessLog::DISCIPLINARY)->where('user_id', $this->hr->id)
        ->where('auditable_id', $this->case->id)->exists())->toBeTrue();
});

it('logs every evidence download', function () {
    $evidence = $this->case->evidence()->first();
    $before = disciplinaryAccessLogs($this->hr);

    $this->actingAs($this->hr, 'admin')
        ->get(route('admin.disciplinary-cases.evidence.download', [$this->case, $evidence]))
        ->assertOk();

    expect(disciplinaryAccessLogs($this->hr))->toBe($before + 1);
});

it('logs the list view of case files', function () {
    $this->actingAs($this->hr, 'admin')->get(route('admin.disciplinary-cases.index'))->assertOk()->assertSee($this->case->reference);

    expect(disciplinaryAccessLogs($this->hr))->toBe(1);
});

it('logs opening the employee profile tab', function () {
    $this->actingAs($this->hr, 'admin')
        ->get(route('admin.employees.tab', [$this->employee, 'disciplinary']))
        ->assertOk()
        ->assertSee($this->case->reference);

    expect(AuditLog::query()->where('event', SensitiveDataAccessLog::DISCIPLINARY)->where('user_id', $this->hr->id)
        ->where('auditable_type', 'employee')->where('auditable_id', $this->employee->id)->exists())->toBeTrue();
});

it('hides case files from HR viewers without disciplinary.view', function () {
    $this->actingAs($this->specialist, 'admin')->get(route('admin.disciplinary-cases.show', $this->case))->assertForbidden();
    $this->actingAs($this->specialist, 'admin')->get(route('admin.disciplinary-cases.index'))->assertOk()->assertDontSee($this->case->reference);
    $this->actingAs($this->specialist, 'admin')->get(route('admin.employees.tab', [$this->employee, 'disciplinary']))->assertForbidden();
    $this->actingAs($this->specialist, 'admin')
        ->get(route('admin.disciplinary-cases.evidence.download', [$this->case, $this->case->evidence()->first()]))->assertForbidden();
    $this->actingAs($this->specialist, 'admin')->get(route('admin.disciplinary-cases.export'))->assertForbidden();

    expect(disciplinaryAccessLogs($this->specialist))->toBe(0);
});

it('shows a reporter only the status of the cases they reported', function () {
    $this->actingAs($this->specialist, 'admin')
        ->post(route('admin.disciplinary-cases.store'), [
            'employee_id' => $this->employee->id,
            'violation_type_id' => ViolationType::query()->where('code', 'LATE')->value('id'),
            'incident_date' => now()->toDateString(),
            'description' => 'Reporter narrative that stays confidential.',
        ])->assertRedirect();

    $own = DisciplinaryCase::query()->where('reported_by', $this->specialist->id)->sole();

    $this->actingAs($this->specialist, 'admin')
        ->get(route('admin.disciplinary-cases.show', $own))
        ->assertOk()
        ->assertSee($own->reference)
        ->assertSee(__('disciplinary.cases.summary_notice'))
        ->assertDontSee('Reporter narrative that stays confidential.');

    $this->actingAs($this->specialist, 'admin')
        ->get(route('admin.disciplinary-cases.index'))
        ->assertSee($own->reference)
        ->assertDontSee($this->case->reference);
});

it('keeps case files inside the data scope', function () {
    $other = Branch::factory()->create();
    $this->hr->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => $other->id]);

    $this->actingAs($this->hr->refresh(), 'admin')->get(route('admin.disciplinary-cases.show', $this->case))->assertNotFound();
});

it('never shows an admin a case about themselves', function () {
    $this->hr->forceFill(['employee_id' => $this->employee->id])->save();

    $this->actingAs($this->hr, 'admin')->get(route('admin.disciplinary-cases.show', $this->case))->assertForbidden();
});

it('lets only approvers decide and only update holders propose', function () {
    $this->actingAs($this->specialist, 'admin')->get(route('admin.disciplinary-cases.actions.create', $this->case))->assertForbidden();

    $this->actingAs($this->hr, 'admin')
        ->post(route('admin.disciplinary-cases.actions.store', $this->case), [
            'type' => 'written_warning', 'details' => 'Written warning.', 'effective_date' => now()->toDateString(),
        ])->assertRedirect(route('admin.disciplinary-cases.show', $this->case));

    $action = $this->case->actions()->sole();

    // The proposer cannot approve their own proposal; another HR Manager can.
    $this->actingAs($this->hr, 'admin')->post(route('admin.disciplinary-cases.actions.decide', [$this->case, $action, 'approve']))->assertForbidden();
    $this->actingAs(adminWithRoles('HR Manager'), 'admin')
        ->post(route('admin.disciplinary-cases.actions.decide', [$this->case, $action, 'approve']))->assertRedirect();

    expect($action->refresh()->isIssued())->toBeTrue();
});

it('exports case lists for export holders and logs it', function () {
    $this->actingAs($this->hr, 'admin')->get(route('admin.disciplinary-cases.export', ['format' => 'csv']))->assertOk();

    expect(disciplinaryAccessLogs($this->hr))->toBe(1);
});

it('reserves the violation catalogue to disciplinary.manage', function () {
    $this->actingAs($this->specialist, 'admin')->get(route('admin.violation-types.index'))->assertForbidden();

    $this->actingAs($this->hr, 'admin')
        ->post(route('admin.violation-types.store'), [
            'code' => 'fraud', 'name_ar' => 'احتيال', 'name_en' => 'Fraud', 'severity' => 'gross', 'default_action' => 'termination', 'is_active' => 1,
        ])->assertRedirect(route('admin.violation-types.index'));

    expect(ViolationType::query()->where('code', 'FRAUD')->exists())->toBeTrue();

    $used = ViolationType::query()->where('code', 'CONDUCT')->firstOrFail();
    $this->actingAs($this->hr, 'admin')->delete(route('admin.violation-types.destroy', $used))->assertSessionHasErrors('record');
    expect($used->fresh())->not->toBeNull();
});

it('withholds disciplinary entries from the history timeline without disciplinary.view', function () {
    $action = app(ManageDisciplinaryAction::class)->propose($this->case, [
        'type' => 'written_warning', 'details' => 'Written.', 'effective_date' => now()->toDateString(),
    ], $this->hr);
    app(ManageDisciplinaryAction::class)->decide($action, adminWithRoles('HR Manager'), true);

    $this->actingAs($this->specialist, 'admin')
        ->get(route('admin.employees.tab', [$this->employee, 'history']))
        ->assertOk()
        ->assertDontSee('data-history-module="disciplinary"', false);

    $this->actingAs($this->hr, 'admin')
        ->get(route('admin.employees.tab', [$this->employee, 'history']))
        ->assertOk()
        ->assertSee('data-history-module="disciplinary"', false);
});
