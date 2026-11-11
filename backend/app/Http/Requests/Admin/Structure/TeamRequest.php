<?php

namespace App\Http\Requests\Admin\Structure;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Team;

class TeamRequest extends StructureRequest
{
    protected function modelClass(): string
    {
        return Team::class;
    }

    protected function routeKey(): string
    {
        return 'team';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->identityRules(),
            'department_id' => ['required', 'integer', $this->inScope(Department::class, 'department_id')],
            'leader_employee_id' => ['nullable', 'integer', $this->inScope(Employee::class, 'leader_employee_id')],
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
