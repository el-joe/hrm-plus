<?php

namespace App\Actions\Assets;

use App\Actions\Onboarding\UpdateOnboardingTask;
use App\Enums\AssetAssignmentStatus;
use App\Enums\AssetCondition;
use App\Enums\AssetEventType;
use App\Enums\AssetStatus;
use App\Enums\OffboardingStatus;
use App\Enums\TaskStatus;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Models\OffboardingItem;
use App\Models\OnboardingTask;
use App\Models\User;
use App\Notifications\Assets\AssetCustodyNotification;
use App\Services\Assets\AssetClearanceHandover;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Custody of an asset by an employee:
 *
 * - assign(): only an available asset; writes the handover (date, condition, notes),
 *   optionally completes the onboarding task that asked for it, adds a clearance
 *   item when the employee already has an open offboarding case, and asks the
 *   employee to acknowledge (notification);
 * - acknowledge(): the employee confirms receipt in the portal / app (or HR records
 *   a paper acknowledgement);
 * - returnAsset(): condition check on return; a damaged return leaves the asset
 *   "damaged", otherwise it is available again; completes the linked offboarding
 *   clearance items;
 * - markLost(): an assigned asset reported lost closes the assignment as lost.
 *
 * Every change writes an AssetEvent (with the employee) and an audit entry.
 */
class ManageAssetCustody
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly UpdateOnboardingTask $onboardingTasks,
        private readonly AssetClearanceHandover $clearance,
    ) {}

    /**
     * @param  array{assigned_on: string, expected_return_on?: string|null, condition_on_issue: string, handover_notes?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function assign(Asset $asset, Employee $employee, array $data, User $user, ?OnboardingTask $task = null): AssetAssignment
    {
        if ($employee->isTerminated()) {
            throw ValidationException::withMessages(['employee_id' => __('assets.validation.employee_terminated')]);
        }

        $assignedOn = CarbonImmutable::parse($data['assigned_on'])->startOfDay();

        if ($assignedOn->lt($employee->hire_date ?? $assignedOn)) {
            throw ValidationException::withMessages(['assigned_on' => __('assets.validation.before_hire')]);
        }

        if (filled($data['expected_return_on'] ?? null) && CarbonImmutable::parse($data['expected_return_on'])->lt($assignedOn)) {
            throw ValidationException::withMessages(['expected_return_on' => __('assets.validation.return_before_issue')]);
        }

        if ($task !== null && $task->onboarding()->withoutGlobalScopes()->value('employee_id') !== $employee->id) {
            throw ValidationException::withMessages(['onboarding_task_id' => __('assets.validation.task_employee')]);
        }

        $assignment = DB::transaction(function () use ($asset, $employee, $data, $user, $task, $assignedOn): AssetAssignment {
            $asset = Asset::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($asset->id);

            if (! $asset->isAvailable()) {
                throw ValidationException::withMessages(['asset_id' => __('assets.validation.not_available', ['status' => $asset->status->label()])]);
            }

            $condition = AssetCondition::from($data['condition_on_issue']);

            $assignment = AssetAssignment::create([
                'asset_id' => $asset->id,
                'employee_id' => $employee->id,
                'assigned_on' => $assignedOn->toDateString(),
                'expected_return_on' => $data['expected_return_on'] ?? null,
                'condition_on_issue' => $condition,
                'handover_notes' => $data['handover_notes'] ?? null,
                'issued_by' => $user->id,
                'onboarding_task_id' => $task?->id,
            ]);

            $asset->forceFill(['status' => AssetStatus::Assigned, 'condition' => $condition])->save();
            $this->event($assignment, AssetEventType::Assigned, $condition, $data['handover_notes'] ?? null, $assignedOn->toDateString(), $user);

            if ($task !== null && $task->status === TaskStatus::Pending) {
                $this->onboardingTasks->handle($task, TaskStatus::Done, $user, __('assets.custody.task_note', ['asset' => $asset->asset_tag], (string) setting('localisation.default_locale')));
            }

            $this->addToOpenOffboardingCase($assignment);
            $this->audit->log('assets.assigned', $assignment, meta: ['asset' => $asset->asset_tag, 'employee' => $employee->employee_number], actor: $user);

            return $assignment;
        });

        $this->notifyEmployee($assignment, 'assigned', $user);

        return $assignment;
    }

    /**
     * @param  string  $via  portal | api | admin
     *
     * @throws ValidationException
     */
    public function acknowledge(AssetAssignment $assignment, string $via, User $user, ?string $note = null): AssetAssignment
    {
        if (! $assignment->isActive() || $assignment->isAcknowledged()) {
            throw ValidationException::withMessages(['assignment' => __('assets.validation.not_acknowledgeable')]);
        }

        return DB::transaction(function () use ($assignment, $via, $user, $note): AssetAssignment {
            $assignment->forceFill(['acknowledged_at' => now(), 'acknowledged_via' => $via])->save();
            $this->event($assignment, AssetEventType::Acknowledged, null, $note ?: __('assets.custody.acknowledged_via.'.$via, [], (string) setting('localisation.default_locale')), today()->toDateString(), $user);
            $this->audit->log('assets.acknowledged', $assignment, meta: ['via' => $via], actor: $user);

            return $assignment;
        });
    }

    /**
     * @param  array{returned_on: string, condition_on_return: string, return_notes?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function returnAsset(AssetAssignment $assignment, array $data, User $user): AssetAssignment
    {
        if (! $assignment->isActive()) {
            throw ValidationException::withMessages(['returned_on' => __('assets.validation.not_active')]);
        }

        $returnedOn = CarbonImmutable::parse($data['returned_on'])->startOfDay();

        if ($returnedOn->lt($assignment->assigned_on)) {
            throw ValidationException::withMessages(['returned_on' => __('assets.validation.return_before_issue')]);
        }

        $condition = AssetCondition::from($data['condition_on_return']);

        $assignment = DB::transaction(function () use ($assignment, $data, $user, $returnedOn, $condition): AssetAssignment {
            $assignment->forceFill([
                'status' => AssetAssignmentStatus::Returned,
                'returned_on' => $returnedOn->toDateString(),
                'condition_on_return' => $condition,
                'return_notes' => $data['return_notes'] ?? null,
                'received_by' => $user->id,
            ])->save();

            $asset = Asset::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($assignment->asset_id);
            $asset->forceFill([
                'status' => $condition === AssetCondition::Damaged ? AssetStatus::Damaged : AssetStatus::Available,
                'condition' => $condition,
            ])->save();

            $this->event($assignment, AssetEventType::Returned, $condition, $data['return_notes'] ?? null, $returnedOn->toDateString(), $user);
            $this->completeClearanceItems($assignment, $user, $returnedOn, $condition);
            $this->audit->log('assets.returned', $assignment, meta: ['condition' => $condition->value], actor: $user);

            return $assignment;
        });

        $this->notifyEmployee($assignment, 'returned', $user);

        return $assignment;
    }

    /**
     * An assigned asset reported lost: the assignment closes as lost, the asset is "lost".
     * Linked offboarding items stay open for HR to settle (recover the value or waive).
     */
    public function markLost(AssetAssignment $assignment, string $date, ?string $note, User $user): AssetAssignment
    {
        if (! $assignment->isActive()) {
            throw ValidationException::withMessages(['note' => __('assets.validation.not_active')]);
        }

        return DB::transaction(function () use ($assignment, $date, $note, $user): AssetAssignment {
            $assignment->forceFill(['status' => AssetAssignmentStatus::Lost, 'return_notes' => $note, 'received_by' => $user->id])->save();
            Asset::query()->withoutGlobalScopes()->whereKey($assignment->asset_id)->firstOrFail()->forceFill(['status' => AssetStatus::Lost])->save();
            $this->event($assignment, AssetEventType::MarkedLost, null, $note, $date, $user);

            OffboardingItem::query()->whereMorphedTo('itemable', $assignment)->where('status', TaskStatus::Pending)->get()
                ->each(fn (OffboardingItem $item) => $item->forceFill([
                    'note' => __('assets.custody.lost_item_note', ['date' => $date], (string) setting('localisation.default_locale')),
                ])->save());

            $this->audit->log('assets.lost', $assignment, actor: $user);

            return $assignment;
        });
    }

    private function addToOpenOffboardingCase(AssetAssignment $assignment): void
    {
        $case = OffboardingCase::query()->withoutGlobalScopes()
            ->where('employee_id', $assignment->employee_id)->where('status', OffboardingStatus::Open)->first();

        if ($case === null) {
            return;
        }

        $item = $this->clearance->itemFor($assignment);
        $case->items()->create([...$item, 'status' => TaskStatus::Pending, 'sort_order' => (int) $case->items()->max('sort_order') + 10]);
    }

    private function completeClearanceItems(AssetAssignment $assignment, User $user, CarbonImmutable $date, AssetCondition $condition): void
    {
        $locale = (string) setting('localisation.default_locale');

        OffboardingItem::query()->whereMorphedTo('itemable', $assignment)->where('status', TaskStatus::Pending)
            ->whereHas('offboardingCase', fn ($query) => $query->withoutGlobalScopes()->where('status', OffboardingStatus::Open))
            ->get()
            ->each(fn (OffboardingItem $item) => $item->forceFill([
                'status' => TaskStatus::Done,
                'completed_at' => now(),
                'completed_by' => $user->id,
                'note' => __('assets.custody.returned_item_note', ['date' => $date->toDateString(), 'condition' => __('assets.enums.conditions.'.$condition->value, [], $locale)], $locale),
            ])->save());
    }

    private function event(AssetAssignment $assignment, AssetEventType $type, ?AssetCondition $condition, ?string $note, string $date, User $user): void
    {
        $assignment->asset()->withoutGlobalScopes()->firstOrFail()->events()->create([
            'asset_assignment_id' => $assignment->id,
            'employee_id' => $assignment->employee_id,
            'type' => $type,
            'condition' => $condition,
            'note' => $note,
            'occurred_on' => $date,
            'created_by' => $user->id,
        ]);
    }

    private function notifyEmployee(AssetAssignment $assignment, string $kind, User $actor): void
    {
        User::query()->where('employee_id', $assignment->employee_id)->whereKeyNot($actor->id)->get()
            ->each(fn (User $user) => $user->notify(new AssetCustodyNotification($assignment, $kind)));
    }
}
