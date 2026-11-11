<?php

namespace App\Http\Requests\Admin\Structure;

use App\Models\Branch;
use App\Models\BranchLocation;
use Illuminate\Database\Eloquent\Model;

/**
 * Locations are managed as part of their branch: branches.update on the branch.
 */
class BranchLocationRequest extends StructureRequest
{
    protected function modelClass(): string
    {
        return BranchLocation::class;
    }

    protected function routeKey(): string
    {
        return 'location';
    }

    public function authorize(): bool
    {
        $branch = $this->route('branch');

        return $branch instanceof Branch && $this->user()->can('update', $branch);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'decimal:0,7', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'decimal:0,7', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'between:10,5000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function record(): ?Model
    {
        $location = $this->route('location');

        return $location instanceof BranchLocation ? $location : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function recordData(): array
    {
        return $this->withBooleans(['is_active']);
    }
}
