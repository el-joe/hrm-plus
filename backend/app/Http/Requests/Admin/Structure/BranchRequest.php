<?php

namespace App\Http\Requests\Admin\Structure;

use App\Models\Branch;
use App\Models\Position;

class BranchRequest extends StructureRequest
{
    protected function modelClass(): string
    {
        return Branch::class;
    }

    protected function routeKey(): string
    {
        return 'branch';
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if ($this->filled('country_code')) {
            $this->merge(['country_code' => mb_strtoupper(trim((string) $this->input('country_code')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->identityRules(),
            'is_headquarters' => ['sometimes', 'boolean'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'country_code' => ['nullable', 'string', 'size:2', 'alpha'],
            'address_ar' => ['nullable', 'string', 'max:500'],
            'address_en' => ['nullable', 'string', 'max:500'],
            'timezone' => ['nullable', 'timezone'],
            'manager_position_id' => ['nullable', 'integer', $this->inScope(Position::class, 'manager_position_id')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordData(): array
    {
        return $this->withBooleans(['is_active', 'is_headquarters']);
    }
}
