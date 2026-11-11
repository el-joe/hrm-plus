<?php

namespace App\Http\Requests\Admin\Structure;

use App\Models\EmployeeGroup;

class EmployeeGroupRequest extends StructureRequest
{
    protected function modelClass(): string
    {
        return EmployeeGroup::class;
    }

    protected function routeKey(): string
    {
        return 'employee_group';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->identityRules(),
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordData(): array
    {
        return $this->withBooleans(['is_active']);
    }
}
