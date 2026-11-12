<?php

namespace App\Actions\Assets;

use App\Enums\AssetCondition;
use App\Enums\AssetEventType;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Status changes of an asset that is not with an employee: maintenance
 * (start / complete), damaged, lost and retirement. An assigned asset must
 * be returned first (or reported lost through its assignment).
 */
class ChangeAssetStatus
{
    /**
     * Allowed transitions: action => [from statuses, to status, event].
     *
     * @var array<string, array{0: list<AssetStatus>, 1: AssetStatus, 2: AssetEventType}>
     */
    private const TRANSITIONS = [
        'maintenance' => [[AssetStatus::Available, AssetStatus::Damaged], AssetStatus::InMaintenance, AssetEventType::MaintenanceStarted],
        'maintenance_done' => [[AssetStatus::InMaintenance], AssetStatus::Available, AssetEventType::MaintenanceCompleted],
        'damaged' => [[AssetStatus::Available, AssetStatus::InMaintenance], AssetStatus::Damaged, AssetEventType::MarkedDamaged],
        'lost' => [[AssetStatus::Available, AssetStatus::InMaintenance, AssetStatus::Damaged], AssetStatus::Lost, AssetEventType::MarkedLost],
        'retire' => [[AssetStatus::Available, AssetStatus::InMaintenance, AssetStatus::Damaged, AssetStatus::Lost], AssetStatus::Retired, AssetEventType::Retired],
    ];

    public const ACTIONS = ['maintenance', 'maintenance_done', 'damaged', 'lost', 'retire'];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Actions the asset's current status allows.
     *
     * @return list<string>
     */
    public function available(Asset $asset): array
    {
        return array_values(array_filter(self::ACTIONS, fn (string $action): bool => in_array($asset->status, self::TRANSITIONS[$action][0], true)));
    }

    /**
     * @throws ValidationException
     */
    public function handle(Asset $asset, string $action, User $user, ?string $note = null, ?string $condition = null, ?string $date = null): Asset
    {
        [$from, $to, $event] = self::TRANSITIONS[$action] ?? throw ValidationException::withMessages(['action' => __('assets.validation.action')]);

        return DB::transaction(function () use ($asset, $action, $from, $to, $event, $user, $note, $condition, $date): Asset {
            $asset = Asset::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($asset->id);

            if (! in_array($asset->status, $from, true)) {
                throw ValidationException::withMessages(['action' => __('assets.validation.transition', ['status' => $asset->status->label()])]);
            }

            $newCondition = match (true) {
                $condition !== null => AssetCondition::from($condition),
                $to === AssetStatus::Damaged => AssetCondition::Damaged,
                default => $asset->condition,
            };

            $asset->forceFill(['status' => $to, 'condition' => $newCondition])->save();
            $asset->events()->create([
                'type' => $event, 'condition' => $newCondition, 'note' => $note,
                'occurred_on' => $date ?? today()->toDateString(), 'created_by' => $user->id,
            ]);
            $this->audit->log('assets.'.$action, $asset, actor: $user);

            return $asset;
        });
    }
}
