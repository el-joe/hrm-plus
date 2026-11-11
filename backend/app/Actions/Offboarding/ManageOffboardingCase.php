<?php

namespace App\Actions\Offboarding;

use App\Actions\Employees\TerminateEmployee;
use App\Enums\OffboardingStatus;
use App\Enums\TaskStatus;
use App\Models\OffboardingCase;
use App\Models\OffboardingItem;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Work on an open offboarding case: handover items, extra items, the exit
 * interview, cancelling, and completion.
 *
 * Completion requires every handover item done or waived, then terminates
 * the employee through TerminateEmployee on the last working date (today,
 * past, or scheduled): history, documents, contracts and salary are kept,
 * the open contract is closed, and every linked login (employee and admin
 * account) is switched off when the termination takes effect.
 */
class ManageOffboardingCase
{
    public function __construct(private readonly TerminateEmployee $terminate, private readonly AuditLogger $audit) {}

    public function updateItem(OffboardingItem $item, TaskStatus $status, User $actor, ?string $note = null): OffboardingItem
    {
        $this->ensureOpen($item->offboardingCase);

        $item->forceFill([
            'status' => $status,
            'note' => $note ?? $item->note,
            'completed_at' => $status->isFinished() ? now() : null,
            'completed_by' => $status->isFinished() ? $actor->getKey() : null,
        ])->save();

        return $item;
    }

    /**
     * @param  array{category: string, assignee: string, title: string, description?: string|null}  $data
     */
    public function addItem(OffboardingCase $case, array $data): OffboardingItem
    {
        $this->ensureOpen($case);

        return $case->items()->create([...$data, 'status' => TaskStatus::Pending, 'sort_order' => (int) $case->items()->max('sort_order') + 10]);
    }

    /**
     * @param  array<string, mixed>  $answers  keys of config('lifecycle.exit_interview')
     */
    public function recordExitInterview(OffboardingCase $case, array $answers, ?string $notes, User $actor): OffboardingCase
    {
        $this->ensureOpen($case);

        $case->forceFill([
            'exit_interview_at' => now(),
            'exit_interviewed_by' => $actor->getKey(),
            'exit_interview_answers' => $answers,
            'exit_interview_notes' => $notes,
        ])->save();

        return $case;
    }

    /**
     * @param  array{type?: string, last_working_date?: string, reason?: string|null, eligible_for_rehire?: bool, notice_date?: string|null}  $data
     */
    public function update(OffboardingCase $case, array $data): OffboardingCase
    {
        $this->ensureOpen($case);
        $case->update($data);

        return $case;
    }

    public function complete(OffboardingCase $case, User $actor): OffboardingCase
    {
        $this->ensureOpen($case);

        if ($case->hasPendingItems()) {
            throw ValidationException::withMessages(['items' => __('onboarding.validation.items_pending')]);
        }

        $employee = $case->employee()->withoutGlobalScopes()->firstOrFail();

        if ($employee->isTerminated()) {
            throw ValidationException::withMessages(['items' => __('onboarding.validation.employee_terminated')]);
        }

        if ($case->last_working_date->lt($employee->hire_date)) {
            throw ValidationException::withMessages(['last_working_date' => __('onboarding.validation.before_hire')]);
        }

        return DB::transaction(function () use ($case, $employee, $actor): OffboardingCase {
            $assignment = $this->terminate->handle($employee, [
                'termination_date' => $case->last_working_date->toDateString(),
                'termination_type' => $case->type->value,
                'reason' => $case->reason ?: $case->type->label(),
                'eligible_for_rehire' => $case->eligible_for_rehire,
            ]);

            $case->forceFill([
                'status' => OffboardingStatus::Completed,
                'completed_at' => now(),
                'completed_by' => $actor->getKey(),
                'employee_assignment_id' => $assignment->getKey(),
            ])->save();

            $this->audit->log('offboarding.completed', $case, meta: ['scheduled' => $assignment->isPending()], actor: $actor);

            return $case;
        });
    }

    public function cancel(OffboardingCase $case, User $actor): OffboardingCase
    {
        $this->ensureOpen($case);
        $case->forceFill(['status' => OffboardingStatus::Cancelled])->save();
        $this->audit->log('offboarding.cancelled', $case, actor: $actor);

        return $case;
    }

    private function ensureOpen(OffboardingCase $case): void
    {
        if (! $case->isOpen()) {
            throw ValidationException::withMessages(['status' => __('onboarding.validation.case_closed')]);
        }
    }
}
