<?php

namespace App\Models;

use App\Enums\AssetAssignmentStatus;
use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDataScope;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A company asset in the register. Status and condition change only through
 * App\Actions\Assets\* (each change writes an AssetEvent); the data scope is
 * the branch the asset belongs to (assets without a branch are visible to
 * unrestricted admins only).
 */
#[Fillable([
    'asset_tag', 'asset_category_id', 'name', 'brand', 'model', 'serial_number', 'purchase_date', 'purchase_cost',
    'supplier', 'warranty_expires_on', 'branch_id', 'condition', 'notes', 'created_by',
])]
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use Auditable, HasDataScope, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'available', 'condition' => 'good'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'purchase_cost' => 'decimal:2',
            'warranty_expires_on' => 'date',
            'condition' => AssetCondition::class,
            'status' => AssetStatus::class,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function dataScopeMap(): array
    {
        return ['branch' => 'branch_id'];
    }

    /**
     * Tag, name, brand, model or serial number contains the term.
     *
     * @param  Builder<static>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereLike($query->qualifyColumn('asset_tag'), "%{$term}%")
            ->orWhereLike($query->qualifyColumn('name'), "%{$term}%")
            ->orWhereLike($query->qualifyColumn('brand'), "%{$term}%")
            ->orWhereLike($query->qualifyColumn('model'), "%{$term}%")
            ->orWhereLike($query->qualifyColumn('serial_number'), "%{$term}%"));
    }

    /**
     * @return BelongsTo<AssetCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<AssetAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class)->latest('assigned_on')->latest('id');
    }

    /**
     * @return HasOne<AssetAssignment, $this>
     */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(AssetAssignment::class)->where('status', AssetAssignmentStatus::Active)->latestOfMany();
    }

    /**
     * @return HasMany<AssetEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(AssetEvent::class)->latest('occurred_on')->latest('id');
    }

    /**
     * "Name — brand model" for lists and notifications.
     */
    public function label(): string
    {
        $details = trim(($this->brand ?? '').' '.($this->model ?? ''));

        return $this->asset_tag.' · '.$this->name.($details !== '' && ! str_contains($this->name, $details) ? ' ('.$details.')' : '');
    }

    public function isAvailable(): bool
    {
        return $this->status === AssetStatus::Available;
    }

    public function isAssigned(): bool
    {
        return $this->status === AssetStatus::Assigned;
    }
}
