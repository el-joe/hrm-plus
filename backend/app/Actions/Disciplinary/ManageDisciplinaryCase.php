<?php

namespace App\Actions\Disciplinary;

use App\Enums\DisciplinaryCaseStatus;
use App\Enums\DisciplinaryNoteType;
use App\Models\DisciplinaryCase;
use App\Models\DisciplinaryCaseNote;
use App\Models\Employee;
use App\Models\User;
use App\Models\ViolationType;
use App\Notifications\Disciplinary\DisciplinaryNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Disciplinary\DisciplinaryFileStore;
use App\Services\Disciplinary\DisciplinaryRecipients;
use App\Support\ReferenceNumbers;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The life of a disciplinary case up to (and after) its outcome:
 *
 * report():             an incident about an employee in the reporter's data scope (never themselves);
 *                       disciplinary viewers in scope are told;
 * startInvestigation(): reported → investigating, with an investigator;
 * addNote() / addEvidence() / recordFindings(): the investigation file (open cases only);
 * dismiss():            no action (reported / investigating → dismissed);
 * close():              after the action was issued (action_issued → closed).
 *
 * Proposing and deciding the action is App\Actions\Disciplinary\ManageDisciplinaryAction.
 */
class ManageDisciplinaryCase
{
    public function __construct(
        private readonly ReferenceNumbers $references,
        private readonly DisciplinaryFileStore $files,
        private readonly DisciplinaryRecipients $recipients,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{employee_id: int|string, violation_type_id: int|string, incident_date: string, location?: ?string, description: string}  $data
     * @param  list<UploadedFile>  $evidence
     *
     * @throws ValidationException
     */
    public function report(array $data, User $reporter, array $evidence = []): DisciplinaryCase
    {
        // Inside the admin area the employee lookup is limited to the reporter's data scope.
        $employee = Employee::query()->find($data['employee_id']);

        if ($employee === null || $employee->isTerminated()) {
            throw ValidationException::withMessages(['employee_id' => __('disciplinary.validation.employee')]);
        }

        if ($reporter->employee_id !== null && $reporter->employee_id === $employee->id) {
            throw ValidationException::withMessages(['employee_id' => __('disciplinary.validation.own_case')]);
        }

        $violation = ViolationType::query()->active()->find($data['violation_type_id']);

        if ($violation === null) {
            throw ValidationException::withMessages(['violation_type_id' => __('disciplinary.validation.violation_type')]);
        }

        $case = DB::transaction(function () use ($data, $reporter, $employee, $violation, $evidence): DisciplinaryCase {
            $case = DisciplinaryCase::create([
                'reference' => $this->references->next('DIS'),
                'employee_id' => $employee->id,
                'violation_type_id' => $violation->id,
                'branch_id' => $employee->branch_id,
                'department_id' => $employee->department_id,
                'incident_date' => $data['incident_date'],
                'location' => $data['location'] ?? null,
                'description' => $data['description'],
                'status' => DisciplinaryCaseStatus::Reported,
                'reported_by' => $reporter->id,
            ]);

            foreach ($evidence as $file) {
                $case->evidence()->create([...$this->files->store($case, $file), 'uploaded_by' => $reporter->id]);
            }

            $this->audit->log('disciplinary.case_reported', $case, meta: ['violation' => $violation->code, 'evidence' => count($evidence)], actor: $reporter);

            return $case;
        });

        foreach ($this->recipients->viewersOf($case, $reporter) as $viewer) {
            $viewer->notify(new DisciplinaryNotification($case, 'reported'));
        }

        return $case;
    }

    /**
     * @throws ValidationException
     */
    public function startInvestigation(DisciplinaryCase $case, User $actor, ?int $investigatorId = null): DisciplinaryCase
    {
        if ($case->status !== DisciplinaryCaseStatus::Reported) {
            throw ValidationException::withMessages(['status' => __('disciplinary.validation.not_reported')]);
        }

        $investigator = $investigatorId === null ? $actor : User::query()->admins()->find($investigatorId);

        if ($investigator === null || ! $investigator->isActive() || ! $investigator->can('disciplinary.view')
            || ($investigator->employee_id !== null && $investigator->employee_id === $case->employee_id)) {
            throw ValidationException::withMessages(['investigator_id' => __('disciplinary.validation.investigator')]);
        }

        $case->forceFill(['status' => DisciplinaryCaseStatus::Investigating, 'investigator_id' => $investigator->id])->save();
        $this->audit->log('disciplinary.investigation_started', $case, meta: ['investigator' => $investigator->id], actor: $actor);

        return $case;
    }

    /**
     * @throws ValidationException
     */
    public function addNote(DisciplinaryCase $case, DisciplinaryNoteType $type, string $body, User $actor): DisciplinaryCaseNote
    {
        $this->ensureOpen($case);

        $note = $case->notes()->create(['type' => $type, 'body' => $body, 'created_by' => $actor->id]);
        $this->audit->log('disciplinary.note_added', $case, meta: ['type' => $type->value], actor: $actor);

        return $note;
    }

    /**
     * @param  list<UploadedFile>  $files
     *
     * @throws ValidationException
     */
    public function addEvidence(DisciplinaryCase $case, array $files, ?string $description, User $actor): void
    {
        $this->ensureOpen($case);

        foreach ($files as $file) {
            $case->evidence()->create([...$this->files->store($case, $file), 'description' => $description, 'uploaded_by' => $actor->id]);
        }

        $this->audit->log('disciplinary.evidence_added', $case, meta: ['count' => count($files)], actor: $actor);
    }

    /**
     * @throws ValidationException
     */
    public function recordFindings(DisciplinaryCase $case, string $findings, User $actor): DisciplinaryCase
    {
        $this->ensureOpen($case);

        $case->forceFill(['findings' => $findings])->save();

        return $case;
    }

    /**
     * @throws ValidationException
     */
    public function dismiss(DisciplinaryCase $case, User $actor, string $note): DisciplinaryCase
    {
        if (! $case->status->acceptsProposal()) {
            throw ValidationException::withMessages(['closure_note' => __('disciplinary.validation.not_dismissable')]);
        }

        $case->forceFill([
            'status' => DisciplinaryCaseStatus::Dismissed, 'closure_note' => $note, 'closed_at' => now(), 'closed_by' => $actor->id,
        ])->save();
        $this->audit->log('disciplinary.case_dismissed', $case, actor: $actor);

        return $case;
    }

    /**
     * @throws ValidationException
     */
    public function close(DisciplinaryCase $case, User $actor, ?string $note): DisciplinaryCase
    {
        if ($case->status !== DisciplinaryCaseStatus::ActionIssued) {
            throw ValidationException::withMessages(['closure_note' => __('disciplinary.validation.not_closable')]);
        }

        $case->forceFill([
            'status' => DisciplinaryCaseStatus::Closed, 'closure_note' => $note, 'closed_at' => now(), 'closed_by' => $actor->id,
        ])->save();
        $this->audit->log('disciplinary.case_closed', $case, actor: $actor);

        return $case;
    }

    /**
     * @throws ValidationException
     */
    private function ensureOpen(DisciplinaryCase $case): void
    {
        if (! $case->isOpen()) {
            throw ValidationException::withMessages(['status' => __('disciplinary.validation.case_closed')]);
        }
    }
}
