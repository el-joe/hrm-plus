<?php

namespace App\Http\Requests\Admin\Structure;

use App\Models\EmploymentType;

class EmploymentTypeRequest extends StructureRequest
{
    protected function modelClass(): string
    {
        return EmploymentType::class;
    }

    protected function routeKey(): string
    {
        return 'employment_type';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->identityRules(),
            'is_fixed_term' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordData(): array
    {
        return $this->withBooleans(['is_active', 'is_fixed_term']);
    }
}
