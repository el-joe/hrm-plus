<?php

use App\Actions\Disciplinary\ManageDisciplinaryAction;
use App\Actions\Disciplinary\ManageDisciplinaryCase;
use App\Enums\DisciplinaryActionType;
use App\Enums\ViolationSeverity;
use App\Models\Employee;
use App\Models\ViolationType;
use App\Services\Disciplinary\WarningEscalation;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Database\Seeders\DisciplinaryDefaultsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/*
| Progressive discipline (Phase 8a): verbal → written → final; only active (unexpired,
| approved) warnings count; a lower level than suggested needs a justification.
*/

beforeEach(function () {
    seedRoles();
    $this->seed(DisciplinaryDefaultsSeeder::class);
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-12 10:00:00'));

    $this->employee = Employee::factory()->create();
    $this->proposer = adminWithRoles('HR Manager');
    $this->approver = adminWithRoles('HR Manager');
    $this->minor = ViolationType::factory()->create(['default_action' => DisciplinaryActionType::VerbalWarning]);
    $this->escalation = app(WarningEscalation::class);
});

/**
 * Report, propose and approve a warning of the given type on the given date.
 */
function issueWarningOn(string $date, DisciplinaryActionType $type, ?string $justification = null): void
{
    test()->travelTo(CarbonImmutable::parse($date.' 10:00:00'));

    $case = app(ManageDisciplinaryCase::class)->report([
        'employee_id' => test()->employee->id, 'violation_type_id' => test()->minor->id, 'incident_date' => $date, 'description' => 'Repeated issue.',
    ], test()->proposer);
    $action = app(ManageDisciplinaryAction::class)->propose($case, [
        'type' => $type->value, 'details' => 'Warning.', 'effective_date' => $date, 'justification' => $justification,
    ], test()->proposer);
    app(ManageDisciplinaryAction::class)->decide($action, test()->approver, true);

    test()->travelTo(CarbonImmutable::parse('2026-11-12 10:00:00'));
}

it('suggests the violation default for a first offence', function () {
    $suggestion = $this->escalation->suggest($this->employee, $this->minor);

    expect($suggestion['type'])->toBe(DisciplinaryActionType::VerbalWarning)
        ->and($suggestion['level'])->toBe(1)
        ->and($suggestion['escalated'])->toBeFalse();
});

it('escalates verbal to written to final and caps at final', function () {
    issueWarningOn('2026-06-01', DisciplinaryActionType::VerbalWarning);
    expect($this->escalation->suggest($this->employee, $this->minor)['type'])->toBe(DisciplinaryActionType::WrittenWarning);

    issueWarningOn('2026-08-01', DisciplinaryActionType::WrittenWarning);
    expect($this->escalation->suggest($this->employee, $this->minor)['type'])->toBe(DisciplinaryActionType::FinalWarning);

    issueWarningOn('2026-10-01', DisciplinaryActionType::FinalWarning);
    $suggestion = $this->escalation->suggest($this->employee, $this->minor);

    expect($suggestion['type'])->toBe(DisciplinaryActionType::FinalWarning)
        ->and($suggestion['level'])->toBe(3)
        ->and($suggestion['escalated'])->toBeTrue()
        ->and($suggestion['active'])->toHaveCount(3);
});

it('ignores warnings past their validity', function () {
    // 12 months by default: a warning from October 2025 expired in October 2026.
    issueWarningOn('2025-10-01', DisciplinaryActionType::WrittenWarning);

    expect($this->escalation->activeWarnings($this->employee))->toBeEmpty()
        ->and($this->escalation->suggest($this->employee, $this->minor)['type'])->toBe(DisciplinaryActionType::VerbalWarning);
});

it('follows the warning validity setting', function () {
    app(SettingsService::class)->set('disciplinary.warning_validity_months', 24);
    issueWarningOn('2025-10-01', DisciplinaryActionType::VerbalWarning);

    expect($this->escalation->highestActiveLevel($this->employee))->toBe(1);
});

it('keeps a stronger violation default over the next warning level', function () {
    issueWarningOn('2026-09-01', DisciplinaryActionType::VerbalWarning);
    $gross = ViolationType::factory()->create(['severity' => ViolationSeverity::Gross, 'default_action' => DisciplinaryActionType::FinalWarning]);

    expect($this->escalation->suggest($this->employee, $gross)['type'])->toBe(DisciplinaryActionType::FinalWarning);
});

it('requires a justification below the suggested level and stores the suggestion', function () {
    issueWarningOn('2026-09-01', DisciplinaryActionType::VerbalWarning);

    $case = app(ManageDisciplinaryCase::class)->report([
        'employee_id' => $this->employee->id, 'violation_type_id' => $this->minor->id, 'incident_date' => '2026-11-10', 'description' => 'Again.',
    ], $this->proposer);
    $propose = fn (array $data) => app(ManageDisciplinaryAction::class)->propose($case->refresh(), [
        'type' => 'verbal_warning', 'details' => 'Warning.', 'effective_date' => '2026-11-12', ...$data,
    ], $this->proposer);

    expect(fn () => $propose([]))->toThrow(ValidationException::class);

    $action = $propose(['justification' => 'First incident in this team; manager asked for leniency.']);

    expect($action->suggested_level)->toBe(2)
        ->and($action->warning_level)->toBe(1)
        ->and($action->details)->toContain('leniency');
});
