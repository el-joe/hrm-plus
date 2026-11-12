<?php

namespace App\Notifications\Assets;

use App\Models\AssetAssignment;
use App\Notifications\CentreNotification;

/**
 * Custody events to the employee (templates "asset_assigned", "asset_returned").
 */
class AssetCustodyNotification extends CentreNotification
{
    public const KINDS = ['assigned', 'returned'];

    public function __construct(public readonly AssetAssignment $assignment, public readonly string $kind) {}

    public function event(): string
    {
        return 'asset_'.$this->kind;
    }

    protected function actionLabelKey(): string
    {
        return 'assets.notifications.open';
    }

    /**
     * @return array<string, scalar|null>
     */
    public function placeholders(object $notifiable, ?string $locale): array
    {
        $assignment = $this->assignment->loadMissing(['asset' => fn ($query) => $query->withoutGlobalScopes()]);

        return [
            'asset' => $assignment->asset->label(),
            'date' => ($this->kind === 'returned' ? $assignment->returned_on : $assignment->assigned_on)?->toDateString(),
            'condition' => __('assets.enums.conditions.'.($assignment->condition_on_return ?? $assignment->condition_on_issue)->value, [], $locale),
        ];
    }

    /**
     * @return array{kind: string, asset_assignment_id: int, url: string}
     */
    public function payload(object $notifiable): array
    {
        return [
            'kind' => 'asset_'.$this->kind,
            'asset_assignment_id' => $this->assignment->id,
            'url' => route('portal.assets'),
        ];
    }
}
