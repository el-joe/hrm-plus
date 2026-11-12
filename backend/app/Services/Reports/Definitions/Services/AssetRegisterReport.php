<?php

namespace App\Services\Reports\Definitions\Services;

use App\Enums\AssetAssignmentStatus;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\User;
use App\Services\Reports\Column;
use App\Services\Reports\Report;
use Illuminate\Database\Eloquent\Builder;

/**
 * Asset register (spec module 12; beyond the module 16 list): every asset of the branches in scope
 * (the asset data scope is its branch) with category, status, condition, holder, purchase cost
 * (bcmath totals) and warranty. "Overdue" = active custody past its expected return.
 */
class AssetRegisterReport extends Report
{
    public function key(): string
    {
        return 'asset_register';
    }

    public function area(): string
    {
        return 'assets';
    }

    public function filters(): array
    {
        return ['branch'];
    }

    public function defaults(): array
    {
        return [];
    }

    public function extraFilters(): array
    {
        return [
            'asset_category_id' => ['label' => __('reports.filters.category'), 'options' => AssetCategory::query()->orderBy('code')->get()->mapWithKeys(fn (AssetCategory $category): array => [$category->id => $category->name])->all()],
            'asset_status' => ['label' => __('reports.filters.asset_status'), 'options' => collect(AssetStatus::cases())->mapWithKeys(fn (AssetStatus $status): array => [$status->value => $status->label()])->all()],
        ];
    }

    public function columns(): array
    {
        return [
            'asset_tag' => Column::code($this->label('asset_tag')),
            'name' => Column::text($this->label('name')),
            'category' => Column::text($this->label('category')),
            'branch' => Column::text($this->label('branch')),
            'status' => Column::text($this->label('status')),
            'condition' => Column::text($this->label('condition')),
            'holder' => Column::text($this->label('holder')),
            'expected_return' => Column::date($this->label('expected_return')),
            'overdue' => Column::text($this->label('overdue')),
            'purchase_date' => Column::date($this->label('purchase_date')),
            'purchase_cost' => Column::amount($this->label('purchase_cost')),
            'warranty' => Column::date($this->label('warranty')),
        ];
    }

    public function sortable(): array
    {
        return ['asset_tag' => 'assets.asset_tag', 'purchase_cost' => 'assets.purchase_cost', 'purchase_date' => 'assets.purchase_date', 'status' => 'assets.status'];
    }

    public function query(array $filters, User $user): Builder
    {
        return Asset::query()
            ->with(['category', 'branch', 'currentAssignment.employee'])
            ->when($filters['branch_id'] ?? null, fn (Builder $query, $id) => $query->where('assets.branch_id', $id))
            ->when($filters['asset_category_id'] ?? null, fn (Builder $query, $id) => $query->where('assets.asset_category_id', $id))
            ->when($filters['asset_status'] ?? null, fn (Builder $query, string $status) => $query->where('assets.status', $status))
            ->orderBy('assets.asset_tag');
    }

    public function row(mixed $record, array $filters): array
    {
        /** @var Asset $record */
        $custody = $record->currentAssignment;
        $active = $custody !== null && $custody->status === AssetAssignmentStatus::Active;

        return [
            'asset_tag' => $record->asset_tag,
            'name' => $record->name,
            'category' => $record->category?->name,
            'branch' => $record->branch?->name,
            'status' => $record->status?->label(),
            'condition' => $record->condition?->label(),
            'holder' => $active ? $custody->employee?->label() : null,
            'expected_return' => $active ? $custody->expected_return_on : null,
            'overdue' => $active && $custody->expected_return_on !== null && $custody->expected_return_on->lt(today()) ? __('reports.ui.yes') : '',
            'purchase_date' => $record->purchase_date,
            'purchase_cost' => $record->purchase_cost === null ? null : (string) $record->getRawOriginal('purchase_cost'),
            'warranty' => $record->warranty_expires_on,
        ];
    }
}
