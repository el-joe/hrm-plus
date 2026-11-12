<?php

namespace App\Http\Requests\Admin\Assets;

use App\Enums\AssetCondition;
use App\Models\Asset;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $asset = $this->route('asset');

        return $asset instanceof Asset ? $this->user()->can('update', $asset) : $this->user()->can('create', Asset::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $asset = $this->route('asset');

        return [
            'asset_tag' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\-\/]*$/', Rule::unique('assets', 'asset_tag')->ignore($asset?->id)],
            'asset_category_id' => ['required', 'integer', Rule::exists('asset_categories', 'id')],
            'name' => ['required', 'string', 'max:200'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100', Rule::unique('assets', 'serial_number')->ignore($asset?->id)],
            'purchase_date' => ['nullable', 'date', 'before_or_equal:today'],
            'purchase_cost' => ['nullable', 'decimal:0,2', 'gte:0', 'max:999999999999'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'warranty_expires_on' => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'condition' => [$asset ? 'nullable' : 'required', Rule::enum(AssetCondition::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Validated data without the condition on edit (it changes only through handovers and status actions).
     *
     * @return array<string, mixed>
     */
    public function assetData(): array
    {
        $data = $this->validated();

        if ($this->route('asset') instanceof Asset) {
            unset($data['condition']);
        }

        return $data;
    }
}
