<?php

namespace App\Actions\Assets;

use App\Enums\AssetEventType;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Register a new asset in the catalogue or edit its details. Status is never
 * changed here (see ManageAssetCustody / ChangeAssetStatus).
 */
class SaveAsset
{
    /**
     * @param  array<string, mixed>  $data  asset_tag, asset_category_id, name, brand, model, serial_number, purchase_date,
     *                                      purchase_cost, supplier, warranty_expires_on, branch_id, condition, notes
     *
     * @throws ValidationException
     */
    public function handle(?Asset $asset, array $data, User $user): Asset
    {
        $category = AssetCategory::query()->find($data['asset_category_id'] ?? null);

        if ($category === null) {
            throw ValidationException::withMessages(['asset_category_id' => __('assets.validation.category')]);
        }

        if ($category->requires_serial && blank($data['serial_number'] ?? null)) {
            throw ValidationException::withMessages(['serial_number' => __('assets.validation.serial_required', ['category' => $category->name])]);
        }

        $attributes = [
            ...$data,
            'asset_tag' => strtoupper(trim((string) $data['asset_tag'])),
            'serial_number' => filled($data['serial_number'] ?? null) ? trim((string) $data['serial_number']) : null,
            'purchase_cost' => filled($data['purchase_cost'] ?? null) ? bcadd((string) $data['purchase_cost'], '0', 2) : null,
        ];

        return DB::transaction(function () use ($asset, $attributes, $user): Asset {
            if ($asset === null) {
                $asset = Asset::create([...$attributes, 'created_by' => $user->id]);
                $this->event($asset, AssetEventType::Registered, $user);

                return $asset;
            }

            $asset->fill($attributes);

            if ($asset->isDirty()) {
                $changed = array_keys($asset->getDirty());
                $asset->save();
                $this->event($asset, AssetEventType::Updated, $user, implode(', ', array_map(fn (string $field): string => __('assets.fields.'.$field), $changed)));
            }

            return $asset;
        });
    }

    private function event(Asset $asset, AssetEventType $type, User $user, ?string $note = null): void
    {
        $asset->events()->create([
            'type' => $type, 'condition' => $asset->condition, 'note' => $note, 'occurred_on' => today()->toDateString(), 'created_by' => $user->id,
        ]);
    }
}
