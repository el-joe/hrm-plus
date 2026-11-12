<?php

namespace App\Http\Requests\Admin\Assets;

use App\Models\AssetCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('asset_category');

        return $category instanceof AssetCategory ? $this->user()->can('update', $category) : $this->user()->can('create', AssetCategory::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = $this->route('asset_category');

        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\-]*$/', Rule::unique('asset_categories', 'code')->ignore($category?->id)],
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'requires_serial' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'requires_serial' => $this->boolean('requires_serial'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
