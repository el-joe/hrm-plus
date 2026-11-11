<?php

namespace App\Http\Requests\Admin\Structure;

use App\Models\Branch;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Position;
use Closure;

class DepartmentRequest extends StructureRequest
{
    protected function modelClass(): string
    {
        return Department::class;
    }

    protected function routeKey(): string
    {
        return 'department';
    }

    /**
     * A data-restricted admin must keep the department inside a branch, or it
     * would drop out of their own scope.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->identityRules(),
            'parent_id' => ['nullable', 'integer', $this->inScope(Department::class, 'parent_id'), $this->notOwnDescendant()],
            'branch_id' => [$this->user()->hasDataRestrictions() ? 'required' : 'nullable', 'integer', $this->inScope(Branch::class, 'branch_id')],
            'cost_center_id' => ['nullable', 'integer', $this->inScope(CostCenter::class, 'cost_center_id')],
            'head_position_id' => ['nullable', 'integer', $this->inScope(Position::class, 'head_position_id'), $this->headPositionInDepartment(...)],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * The head is one of the department's own positions (so it can only be
     * chosen once the department has positions).
     */
    private function headPositionInDepartment(string $attribute, mixed $value, Closure $fail): void
    {
        $department = $this->record();

        if ($department === null || ! Position::query()->whereKey($value)->where('department_id', $department->getKey())->exists()) {
            $fail(__('structure.messages.head_position_outside_department'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function recordData(): array
    {
        return $this->withBooleans(['is_active']);
    }
}
