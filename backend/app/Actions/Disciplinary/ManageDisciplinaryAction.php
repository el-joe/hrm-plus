<?php

namespace App\Actions\Disciplinary;

use App\Actions\Payroll\ManagePayEntry;
use App\Enums\ApprovalActionType;
use App\Enums\ApprovalStatus;
use App\Enums\DisciplinaryActionStatus;
use App\Enums\DisciplinaryActionType;
use App\Enums\DisciplinaryCaseStatus;
use App\Enums\PayComponentKind;
use App\Exceptions\ApprovalException;
use App\Models\DisciplinaryAction;
use App\Models\DisciplinaryCase;
use App\Models\PayEntry;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Notifications\Disciplinary\DisciplinaryNotification;
use App\Services\Approvals\ApprovalRecipients;
use App\Services\Approvals\ApprovalService;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\DataScopeManager;
use App\Services\Disciplinary\DisciplinaryRecipients;
use App\Services\Disciplinary\WarningEscalation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The outcome of a disciplinary case.
 *
 * propose():  one action per decision (warning, suspension, salary deduction, termination
 *             recommendation) with the escalation suggestion stored; a warning below the
 *             suggestion needs a justification. The case waits in "pending_approval" and the
 *             action goes through the "disciplinary_action" workflow.
 * decide():   one approval step (never on an action about yourself);
 *             applyApprovalOutcome() (listener SyncDisciplinaryApprovals) issues the approved
 *             action to the employee (response deadline from settings) and, for a deduction,
 *             files a pending deduction pay entry (source = the action) in the existing
 *             "payroll_deduction" workflow. A rejection sends the case back to investigating.
 * cancel():   withdraw a pending proposal.
 * acknowledge() / respond(): the employee's side (portal).
 */
class ManageDisciplinaryAction
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly ApprovalRecipients $approvalRecipients,
        private readonly DisciplinaryRecipients $recipients,
        private readonly WarningEscalation $escalation,
        private readonly DataScopeManager $scopes,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{type: string, details: string, effective_date: string, justification?: ?string, suspension_start?: ?string,
     *     suspension_end?: ?string, suspension_paid?: mixed, deduction_amount?: mixed}  $data
     *
     * @throws ValidationException
     */
    public function propose(DisciplinaryCase $case, array $data, User $proposer): DisciplinaryAction
    {
        if (! $case->status->acceptsProposal() || $case->pendingAction() !== null) {
            throw ValidationException::withMessages(['type' => __('disciplinary.validation.not_proposable')]);
        }

        if ($proposer->employee_id !== null && $proposer->employee_id === $case->employee_id) {
            throw ValidationException::withMessages(['type' => __('disciplinary.validation.own_case')]);
        }

        $type = DisciplinaryActionType::from($data['type']);
        $employee = $case->employee()->withoutGlobalScopes()->firstOrFail();
        $suggestion = $this->escalation->suggest($employee, $case->violationType, $data['effective_date']);
        $justification = trim((string) ($data['justification'] ?? ''));

        if ($this->escalation->isBelowSuggestion($type, $suggestion['level']) && $justification === '') {
            throw ValidationException::withMessages(['justification' => __('disciplinary.validation.justification_required', [
                'suggested' => $suggestion['type']->label(),
            ])]);
        }

        $attributes = $this->attributes($type, $data);
        $details = trim($data['details']);

        if ($justification !== '') {
            $details .= "\n\n".__('disciplinary.actions.justification_prefix', [], (string) setting('localisation.default_locale')).' '.$justification;
        }

        $action = DB::transaction(function () use ($case, $proposer, $type, $suggestion, $attributes, $details, $data): DisciplinaryAction {
            $action = DisciplinaryAction::create([
                ...$attributes,
                'disciplinary_case_id' => $case->id,
                'employee_id' => $case->employee_id,
                'type' => $type,
                'warning_level' => $type->warningLevel(),
                'suggested_level' => $suggestion['level'],
                'details' => $details,
                'effective_date' => $data['effective_date'],
                'expires_on' => $type->isWarning() ? $this->escalation->expiryFor($data['effective_date']) : null,
                'proposed_by' => $proposer->id,
            ]);

            try {
                $this->approvals->submit($action, DisciplinaryAction::WORKFLOW, $proposer);
            } catch (ApprovalException $exception) {
                throw ValidationException::withMessages(['type' => $exception->getMessage()]);
            }

            $case->forceFill(['status' => DisciplinaryCaseStatus::PendingApproval])->save();

            $this->audit->log('disciplinary.action_proposed', $action, meta: [
                'case' => $case->reference, 'type' => $type->value, 'suggested_level' => $suggestion['level'],
                'below_suggestion' => $this->escalation->isBelowSuggestion($type, $suggestion['level']),
            ], actor: $proposer);

            return $action;
        });

        $approval = $action->latestApprovalRequest()->first();

        if ($approval !== null) {
            foreach ($this->approvalRecipients->for($approval)->reject(fn (User $user): bool => $user->employee_id === $case->employee_id) as $approver) {
                $approver->notify(new DisciplinaryNotification($case, 'awaiting_approval', $action));
            }
        }

        return $action->refresh();
    }

    /**
     * @throws ValidationException
     */
    public function decide(DisciplinaryAction $action, User $approver, bool $approve, ?string $reason = null): void
    {
        $approval = $action->latestApprovalRequest;

        if ($approval === null || ! $approval->isPending() || ! $action->isPending()) {
            throw ValidationException::withMessages(['reason' => __('disciplinary.validation.not_pending')]);
        }

        if ($approver->employee_id !== null && $approver->employee_id === $action->employee_id) {
            throw ValidationException::withMessages(['reason' => __('disciplinary.validation.own_case')]);
        }

        if (! $approve && blank($reason)) {
            throw ValidationException::withMessages(['reason' => __('disciplinary.validation.reason_required')]);
        }

        try {
            $approve ? $this->approvals->approve($approval, $approver, $reason) : $this->approvals->reject($approval, $approver, (string) $reason);
        } catch (ApprovalException $exception) {
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }
    }

    public function applyApprovalOutcome(DisciplinaryAction $action, bool $approved, ?User $decidedBy, ?string $reason): void
    {
        if (! $action->isPending()) {
            return;
        }

        $case = $action->disciplinaryCase()->withoutGlobalScopes()->firstOrFail();

        if (! $approved) {
            DB::transaction(function () use ($action, $case, $decidedBy, $reason): void {
                $action->forceFill([
                    'status' => DisciplinaryActionStatus::Rejected, 'decided_at' => now(), 'decided_by' => $decidedBy?->id, 'rejection_reason' => $reason,
                ])->save();
                $case->forceFill(['status' => DisciplinaryCaseStatus::Investigating])->save();
                $this->audit->log('disciplinary.action_rejected', $action, meta: ['case' => $case->reference], actor: $decidedBy);
            });

            $this->notifyProposer($action, $case, 'rejected', $decidedBy, $reason);

            return;
        }

        DB::transaction(function () use ($action, $case, $decidedBy): void {
            $action->forceFill([
                'status' => DisciplinaryActionStatus::Approved,
                'decided_at' => now(),
                'decided_by' => $decidedBy?->id,
                'issued_at' => now(),
                'response_due_on' => now()->addDays((int) setting('disciplinary.response_days'))->toDateString(),
            ])->save();
            $case->forceFill(['status' => DisciplinaryCaseStatus::ActionIssued])->save();
            $this->audit->log('disciplinary.action_issued', $action, meta: ['case' => $case->reference, 'type' => $action->type->value], actor: $decidedBy);
        });

        if ($action->deduction_amount !== null && bccomp((string) $action->deduction_amount, '0', 2) > 0) {
            $this->fileDeduction($action->refresh(), $case, $decidedBy);
        }

        $this->notifyProposer($action, $case, 'approved', $decidedBy);

        foreach (User::query()->where('employee_id', $action->employee_id)->get() as $user) {
            $user->notify(new DisciplinaryNotification($case, 'issued', $action));
        }
    }

    /**
     * @throws ValidationException
     */
    public function cancel(DisciplinaryAction $action, User $user, ?string $reason): void
    {
        if (! $action->isPending()) {
            throw ValidationException::withMessages(['reason' => __('disciplinary.validation.not_pending')]);
        }

        DB::transaction(function () use ($action, $user, $reason): void {
            $approval = $action->latestApprovalRequest()->first();

            if ($approval !== null && $approval->isPending()) {
                if ($approval->requested_by === $user->id) {
                    $this->approvals->cancel($approval, $user, $reason);
                } else {
                    $approval->actions()->create([
                        'approval_workflow_step_id' => $approval->current_step_id, 'step_sequence' => $approval->currentStep?->sequence,
                        'step_name' => $approval->currentStep?->name, 'action' => ApprovalActionType::Cancelled, 'acted_by' => $user->id, 'reason' => $reason,
                    ]);
                    $approval->update(['status' => ApprovalStatus::Cancelled, 'current_step_id' => null, 'completed_at' => now()]);
                }
            }

            $action->forceFill(['status' => DisciplinaryActionStatus::Cancelled, 'rejection_reason' => $reason])->save();
            $action->disciplinaryCase()->withoutGlobalScopes()->first()?->forceFill(['status' => DisciplinaryCaseStatus::Investigating])->save();
            $this->audit->log('disciplinary.action_cancelled', $action, actor: $user);
        });
    }

    /**
     * The employee confirms they received the notice (portal).
     *
     * @throws ValidationException
     */
    public function acknowledge(DisciplinaryAction $action, User $employeeUser): DisciplinaryAction
    {
        $this->ensureOwnIssued($action, $employeeUser);

        if ($action->acknowledged_at === null) {
            $action->forceFill(['acknowledged_at' => now()])->save();
            $this->audit->log('disciplinary.action_acknowledged', $action, actor: $employeeUser);
        }

        return $action;
    }

    /**
     * The employee's written response (once); disciplinary viewers in scope are told.
     *
     * @throws ValidationException
     */
    public function respond(DisciplinaryAction $action, User $employeeUser, string $response): DisciplinaryAction
    {
        $this->ensureOwnIssued($action, $employeeUser);

        if ($action->hasResponded()) {
            throw ValidationException::withMessages(['response' => __('disciplinary.validation.already_responded')]);
        }

        $action->forceFill([
            'employee_response' => $response,
            'responded_at' => now(),
            'acknowledged_at' => $action->acknowledged_at ?? now(),
        ])->save();
        $this->audit->log('disciplinary.employee_responded', $action, meta: ['late' => $action->response_due_on?->isPast() && ! $action->response_due_on->isToday()], actor: $employeeUser);

        $case = $action->disciplinaryCase()->withoutGlobalScopes()->firstOrFail();

        foreach ($this->recipients->viewersOf($case) as $viewer) {
            $viewer->notify(new DisciplinaryNotification($case, 'responded', $action));
        }

        return $action;
    }

    /**
     * Columns that depend on the action type.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function attributes(DisciplinaryActionType $type, array $data): array
    {
        $attributes = ['suspension_start' => null, 'suspension_end' => null, 'suspension_paid' => null, 'deduction_amount' => null];

        if ($type === DisciplinaryActionType::Suspension) {
            if (blank($data['suspension_start'] ?? null) || blank($data['suspension_end'] ?? null)) {
                throw ValidationException::withMessages(['suspension_start' => __('disciplinary.validation.suspension_dates')]);
            }

            if (CarbonImmutable::parse($data['suspension_end'])->lt(CarbonImmutable::parse($data['suspension_start']))) {
                throw ValidationException::withMessages(['suspension_end' => __('disciplinary.validation.suspension_order')]);
            }

            $paid = filter_var($data['suspension_paid'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $attributes = [
                'suspension_start' => $data['suspension_start'], 'suspension_end' => $data['suspension_end'], 'suspension_paid' => $paid,
                'deduction_amount' => ! $paid && filled($data['deduction_amount'] ?? null) ? $this->amount($data['deduction_amount']) : null,
            ];
        }

        if ($type === DisciplinaryActionType::SalaryDeduction) {
            if (blank($data['deduction_amount'] ?? null) || bccomp((string) $data['deduction_amount'], '0', 2) <= 0) {
                throw ValidationException::withMessages(['deduction_amount' => __('disciplinary.validation.amount_required')]);
            }

            $attributes['deduction_amount'] = $this->amount($data['deduction_amount']);
        }

        return $attributes;
    }

    /**
     * @throws ValidationException
     */
    private function amount(mixed $value): string
    {
        if (! is_numeric($value) || bccomp((string) $value, '0', 2) <= 0) {
            throw ValidationException::withMessages(['deduction_amount' => __('disciplinary.validation.amount_required')]);
        }

        return bcadd((string) $value, '0', 2);
    }

    /**
     * A one-time deduction entry for the employee (setting disciplinary.deduction_component),
     * pending in the payroll_deduction workflow when the component requires approval.
     * Missing component or a refused entry is logged and audited, never fatal.
     */
    private function fileDeduction(DisciplinaryAction $action, DisciplinaryCase $case, ?User $decidedBy): ?PayEntry
    {
        $author = $decidedBy ?? User::query()->find($action->proposed_by);
        $code = (string) setting('disciplinary.deduction_component');
        $component = $code === '' ? null : SalaryComponent::query()->active()->forEntries(PayComponentKind::Deduction)->where('code', $code)->first();

        if ($component === null || $author === null) {
            Log::warning('Disciplinary deduction not filed: deduction component missing or inactive.', ['action' => $action->id, 'component' => $code]);
            $this->audit->log('disciplinary.deduction_skipped', $action, meta: ['component' => $code, 'reason' => 'component_missing'], actor: $decidedBy);

            return null;
        }

        try {
            $entry = $this->scopes->withoutScoping(fn (): PayEntry => app(ManagePayEntry::class)->create(PayComponentKind::Deduction, [
                'salary_component_id' => $component->id,
                'target_type' => 'employee',
                'target_id' => $action->employee_id,
                'calculation' => 'fixed',
                'amount' => (string) $action->deduction_amount,
                'recurrence' => 'one_time',
                'start_date' => $action->effective_date->toDateString(),
                'reason' => __('disciplinary.actions.deduction_reason', ['reference' => $case->reference, 'type' => $action->type->label()], (string) setting('localisation.default_locale')),
            ], $author, source: $action));
        } catch (ValidationException $exception) {
            Log::warning('Disciplinary deduction not filed.', ['action' => $action->id, 'errors' => $exception->errors()]);
            $this->audit->log('disciplinary.deduction_skipped', $action, meta: ['component' => $code, 'reason' => 'refused'], actor: $decidedBy);

            return null;
        }

        $action->forceFill(['pay_entry_id' => $entry->id])->save();
        $this->audit->log('disciplinary.deduction_filed', $action, meta: ['pay_entry' => $entry->reference], actor: $decidedBy);

        return $entry;
    }

    private function notifyProposer(DisciplinaryAction $action, DisciplinaryCase $case, string $kind, ?User $actor, ?string $reason = null): void
    {
        $proposer = User::query()->find($action->proposed_by);

        if ($proposer !== null && $proposer->id !== $actor?->id && $proposer->isAdmin()) {
            $proposer->notify(new DisciplinaryNotification($case, $kind, $action, $reason));
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensureOwnIssued(DisciplinaryAction $action, User $user): void
    {
        if ($user->employee_id === null || $user->employee_id !== $action->employee_id || ! $action->isIssued()) {
            throw ValidationException::withMessages(['response' => __('disciplinary.validation.not_issued')]);
        }
    }
}
