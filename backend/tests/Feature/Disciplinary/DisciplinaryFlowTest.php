<?php

use App\Actions\Disciplinary\ManageDisciplinaryAction;
use App\Actions\Disciplinary\ManageDisciplinaryCase;
use App\Enums\ApprovalStatus;
use App\Enums\DisciplinaryActionStatus;
use App\Enums\DisciplinaryCaseStatus;
use App\Enums\DisciplinaryNoteType;
use App\Enums\PayComponentKind;
use App\Enums\RequestStatus;
use App\Models\AuditLog;
use App\Models\DisciplinaryAction;
use App\Models\DisciplinaryCase;
use App\Models\Employee;
use App\Models\PayEntry;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Models\ViolationType;
use App\Notifications\Disciplinary\DisciplinaryNotification;
use Database\Seeders\DisciplinaryDefaultsSeeder;
use Database\Seeders\PayrollDefaultsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/*
| Disciplinary cases (Phase 8a): report → investigate → propose → approve (issued) →
| employee responds → close; rejection and withdrawal; the salary deduction pay entry.
*/

beforeEach(function () {
    seedRoles();
    $this->seed(DisciplinaryDefaultsSeeder::class);
    Notification::fake();
    Storage::fake('disciplinary_files');

    $this->employee = Employee::factory()->create();
    $this->employeeUser = User::factory()->employee()->create(['employee_id' => $this->employee->id]);
    $this->proposer = adminWithRoles('HR Manager');
    $this->approver = adminWithRoles('HR Manager');
    $this->reporter = adminWithRoles('HR Specialist');
    $this->violation = ViolationType::query()->where('code', 'LATE')->firstOrFail();
    $this->cases = app(ManageDisciplinaryCase::class);
    $this->actions = app(ManageDisciplinaryAction::class);
});

function reportDisciplinaryCase(?ViolationType $violation = null, ?User $by = null): DisciplinaryCase
{
    return app(ManageDisciplinaryCase::class)->report([
        'employee_id' => test()->employee->id,
        'violation_type_id' => ($violation ?? test()->violation)->id,
        'incident_date' => now()->subDay()->toDateString(),
        'description' => 'Late three times this week.',
    ], $by ?? test()->reporter);
}

function proposeDisciplinaryAction(DisciplinaryCase $case, array $data = []): DisciplinaryAction
{
    return app(ManageDisciplinaryAction::class)->propose($case, [
        'type' => 'verbal_warning', 'details' => 'Please respect the working hours.', 'effective_date' => now()->toDateString(), ...$data,
    ], test()->proposer);
}

it('runs a case from the report to closure with the employee response', function () {
    $case = reportDisciplinaryCase();

    expect($case->status)->toBe(DisciplinaryCaseStatus::Reported)
        ->and($case->reference)->toStartWith('DIS-')
        ->and($case->branch_id)->toBe($this->employee->branch_id)
        ->and($case->reported_by)->toBe($this->reporter->id);

    $this->cases->startInvestigation($case, $this->proposer);
    $this->cases->addNote($case, DisciplinaryNoteType::Interview, 'Employee heard.', $this->proposer);
    $this->cases->addEvidence($case, [UploadedFile::fake()->create('statement.pdf', 20, 'application/pdf')], 'Statement', $this->proposer);

    $action = proposeDisciplinaryAction($case->refresh());

    expect($case->refresh()->status)->toBe(DisciplinaryCaseStatus::PendingApproval)
        ->and($action->status)->toBe(DisciplinaryActionStatus::PendingApproval)
        ->and($action->warning_level)->toBe(1)
        ->and($action->expires_on->toDateString())->toBe(now()->addMonthsNoOverflow(12)->toDateString());

    $this->actions->decide($action, $this->approver, true, 'OK');
    $action->refresh();

    expect($action->status)->toBe(DisciplinaryActionStatus::Approved)
        ->and($action->issued_at)->not->toBeNull()
        ->and($action->response_due_on->toDateString())->toBe(now()->addDays(7)->toDateString())
        ->and($case->refresh()->status)->toBe(DisciplinaryCaseStatus::ActionIssued);

    Notification::assertSentTo($this->employeeUser, DisciplinaryNotification::class, fn ($notification) => $notification->kind === 'issued');

    $this->actions->respond($action, $this->employeeUser, 'I apologise, it will not happen again.');

    expect($action->refresh()->employee_response)->toBe('I apologise, it will not happen again.')
        ->and($action->responded_at)->not->toBeNull()
        ->and($action->acknowledged_at)->not->toBeNull();

    $this->cases->close($case->refresh(), $this->proposer, 'Done.');

    expect($case->refresh()->status)->toBe(DisciplinaryCaseStatus::Closed)
        ->and($case->evidence()->count())->toBe(1)
        ->and($case->notes()->count())->toBe(1);
});

it('lets the employee respond only once', function () {
    $case = reportDisciplinaryCase();
    $action = proposeDisciplinaryAction($case);
    $this->actions->decide($action, $this->approver, true);
    $this->actions->respond($action->refresh(), $this->employeeUser, 'First answer.');

    expect(fn () => $this->actions->respond($action->refresh(), $this->employeeUser, 'Second answer.'))->toThrow(ValidationException::class);
});

it('sends the case back to investigation when the action is rejected', function () {
    $case = reportDisciplinaryCase();
    $action = proposeDisciplinaryAction($case);

    $this->actions->decide($action, $this->approver, false, 'Not proportionate.');

    expect($action->refresh()->status)->toBe(DisciplinaryActionStatus::Rejected)
        ->and($action->rejection_reason)->toBe('Not proportionate.')
        ->and($action->issued_at)->toBeNull()
        ->and($case->refresh()->status)->toBe(DisciplinaryCaseStatus::Investigating);

    Notification::assertSentTo($this->proposer, DisciplinaryNotification::class, fn ($notification) => $notification->kind === 'rejected');
    Notification::assertNotSentTo($this->employeeUser, DisciplinaryNotification::class);
});

it('requires a reason to reject', function () {
    $action = proposeDisciplinaryAction(reportDisciplinaryCase());

    expect(fn () => $this->actions->decide($action, $this->approver, false, ''))->toThrow(ValidationException::class);
});

it('never lets the proposer approve their own proposal', function () {
    $action = proposeDisciplinaryAction(reportDisciplinaryCase());

    expect(fn () => $this->actions->decide($action, $this->proposer, true))->toThrow(ValidationException::class)
        ->and($action->refresh()->isPending())->toBeTrue();
});

it('never lets an approver decide an action about themselves', function () {
    $hrEmployee = Employee::factory()->create();
    $this->approver->forceFill(['employee_id' => $hrEmployee->id])->save();
    $this->employee = $hrEmployee;

    $action = proposeDisciplinaryAction(reportDisciplinaryCase());

    expect(fn () => $this->actions->decide($action, $this->approver, true))->toThrow(ValidationException::class);
});

it('allows only one pending proposal per case', function () {
    $case = reportDisciplinaryCase();
    proposeDisciplinaryAction($case);

    expect(fn () => proposeDisciplinaryAction($case->refresh()))->toThrow(ValidationException::class);
});

it('withdraws a pending proposal and reopens the investigation', function () {
    $case = reportDisciplinaryCase();
    $action = proposeDisciplinaryAction($case);

    $this->actions->cancel($action, $this->proposer, 'Wrong employee level.');

    expect($action->refresh()->status)->toBe(DisciplinaryActionStatus::Cancelled)
        ->and($action->latestApprovalRequest()->first()->status)->toBe(ApprovalStatus::Cancelled)
        ->and($case->refresh()->status)->toBe(DisciplinaryCaseStatus::Investigating);
});

it('dismisses a case without action', function () {
    $case = reportDisciplinaryCase();

    $this->cases->dismiss($case, $this->proposer, 'No violation.');

    expect($case->refresh()->status)->toBe(DisciplinaryCaseStatus::Dismissed)
        ->and($case->closed_at)->not->toBeNull()
        ->and(fn () => $this->cases->addNote($case, DisciplinaryNoteType::Note, 'x', $this->proposer))->toThrow(ValidationException::class);
});

it('refuses a report about yourself', function () {
    $this->reporter->forceFill(['employee_id' => $this->employee->id])->save();

    expect(fn () => reportDisciplinaryCase())->toThrow(ValidationException::class);
});

it('validates suspension dates and deduction amounts', function () {
    $case = reportDisciplinaryCase();

    expect(fn () => proposeDisciplinaryAction($case, ['type' => 'suspension']))->toThrow(ValidationException::class)
        ->and(fn () => proposeDisciplinaryAction($case, ['type' => 'suspension', 'suspension_start' => '2026-12-10', 'suspension_end' => '2026-12-01']))->toThrow(ValidationException::class)
        ->and(fn () => proposeDisciplinaryAction($case, ['type' => 'salary_deduction']))->toThrow(ValidationException::class)
        ->and(fn () => proposeDisciplinaryAction($case, ['type' => 'salary_deduction', 'deduction_amount' => '0']))->toThrow(ValidationException::class);
});

describe('salary deduction', function () {
    beforeEach(function () {
        $this->seed(PayrollDefaultsSeeder::class);
        $this->penalty = SalaryComponent::factory()->deduction()->variable()->needsApproval()->create(['code' => 'PENALTY']);
    });

    it('files a pending deduction pay entry with the action as its source', function () {
        $case = reportDisciplinaryCase(ViolationType::query()->where('code', 'ASSETS')->firstOrFail());
        $action = proposeDisciplinaryAction($case, ['type' => 'salary_deduction', 'deduction_amount' => '750.50']);

        $this->actions->decide($action, $this->approver, true);
        $action->refresh();

        $entry = PayEntry::query()->withoutGlobalScopes()->findOrFail($action->pay_entry_id);

        expect($entry->kind)->toBe(PayComponentKind::Deduction)
            ->and($entry->salary_component_id)->toBe($this->penalty->id)
            ->and($entry->employee_id)->toBe($this->employee->id)
            ->and($entry->amount)->toBe('750.50')
            ->and($entry->recurrence->value)->toBe('one_time')
            ->and($entry->status)->toBe(RequestStatus::Pending)
            ->and($entry->source_type)->toBe('disciplinary_action')
            ->and($entry->source_id)->toBe($action->id)
            ->and($entry->latestApprovalRequest()->first()->workflow->key)->toBe('payroll_deduction');
    });

    it('records an unpaid suspension amount as a deduction too', function () {
        $action = proposeDisciplinaryAction(reportDisciplinaryCase(), [
            'type' => 'suspension', 'suspension_start' => now()->addDay()->toDateString(), 'suspension_end' => now()->addDays(2)->toDateString(),
            'suspension_paid' => false, 'deduction_amount' => '400',
        ]);

        $this->actions->decide($action, $this->approver, true);

        expect($action->refresh()->pay_entry_id)->not->toBeNull()
            ->and($action->warning_level)->toBeNull();
    });

    it('issues the action without a pay entry when the component is missing', function () {
        $this->penalty->forceFill(['is_active' => false])->save();
        $action = proposeDisciplinaryAction(reportDisciplinaryCase(), ['type' => 'salary_deduction', 'deduction_amount' => '100']);

        $this->actions->decide($action, $this->approver, true);

        expect($action->refresh()->status)->toBe(DisciplinaryActionStatus::Approved)
            ->and($action->pay_entry_id)->toBeNull()
            ->and(AuditLog::query()->where('event', 'disciplinary.deduction_skipped')->exists())->toBeTrue();
    });
});

it('notifies approvers when an action is proposed', function () {
    proposeDisciplinaryAction(reportDisciplinaryCase());

    Notification::assertSentTo($this->approver, DisciplinaryNotification::class, fn ($notification) => $notification->kind === 'awaiting_approval');
});
