<?php

namespace App\Actions\HrRequests;

use App\Enums\HrProfileChangeStatus;
use App\Enums\HrRequestStatus;
use App\Models\HrProfileChange;
use App\Models\HrRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\HrRequests\HrRequestHistory;
use App\Services\HrRequests\ProfileChangeFields;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * HR's decision on a profile change request: apply every requested value through the employee actions
 * (UpdateEmployee / SaveEmployeeBankAccount — audited like an edit on the admin profile) and resolve the
 * request, or reject it with a reason. Identity numbers need employee_sensitive.update, bank details
 * employee_financials.update, everything employees.update — for the employee, inside the data scope.
 */
class DecideProfileChanges
{
    public function __construct(
        private readonly ProfileChangeFields $fields,
        private readonly ManageHrRequest $manage,
        private readonly HrRequestHistory $history,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws ValidationException
     */
    public function apply(HrRequest $request, User $actor, ?string $note = null): HrRequest
    {
        $changes = $this->pending($request);
        $employee = $request->employee()->firstOrFail();

        if ($actor->employee_id !== null && $actor->employee_id === $employee->id) {
            throw ValidationException::withMessages(['changes' => __('hr_requests.validation.own_request')]);
        }

        $forbidden = $changes->reject(fn (HrProfileChange $change): bool => $this->fields->canApply($actor, $employee, $this->fields->group($change->field)));

        if ($forbidden->isNotEmpty()) {
            throw ValidationException::withMessages(['changes' => __('hr_requests.validation.cannot_apply', [
                'fields' => $forbidden->map(fn (HrProfileChange $change): string => $this->fields->label($change->field))->implode(', '),
            ])]);
        }

        DB::transaction(function () use ($request, $actor, $changes, $employee): void {
            $this->fields->apply($employee, $changes->mapWithKeys(fn (HrProfileChange $change): array => [$change->field => (string) $change->new_value])->all());

            foreach ($changes as $change) {
                $change->forceFill(['status' => HrProfileChangeStatus::Applied, 'decided_at' => now(), 'decided_by' => $actor->id])->save();
            }

            $this->history->record($request, 'changes_applied', $actor, null, (string) $changes->count());
            $this->audit->log('hr_request.profile_changes_applied', $request, meta: ['fields' => $changes->pluck('field')->all()], actor: $actor);
        });

        if ($request->status === HrRequestStatus::Open) {
            $this->manage->transition($request, HrRequestStatus::InProgress, $actor);
        }

        return $this->manage->resolve($request->refresh(), $actor, $note ?: __('hr_requests.profile_changes.applied_note'));
    }

    /**
     * @throws ValidationException
     */
    public function reject(HrRequest $request, User $actor, string $reason): HrRequest
    {
        $changes = $this->pending($request);

        DB::transaction(function () use ($request, $actor, $changes): void {
            foreach ($changes as $change) {
                $change->forceFill(['status' => HrProfileChangeStatus::Rejected, 'decided_at' => now(), 'decided_by' => $actor->id])->save();
            }

            $this->history->record($request, 'changes_rejected', $actor, null, (string) $changes->count());
        });

        return $this->manage->reject($request->refresh(), $actor, $reason);
    }

    /**
     * @return Collection<int, HrProfileChange>
     *
     * @throws ValidationException
     */
    private function pending(HrRequest $request): Collection
    {
        if (! $request->category->isProfileChange() || ! $request->status->isHandled()) {
            throw ValidationException::withMessages(['changes' => __('hr_requests.validation.not_handled')]);
        }

        $changes = $request->profileChanges()->where('status', HrProfileChangeStatus::Pending)->get();

        if ($changes->isEmpty()) {
            throw ValidationException::withMessages(['changes' => __('hr_requests.validation.no_pending_changes')]);
        }

        return $changes->toBase();
    }
}
