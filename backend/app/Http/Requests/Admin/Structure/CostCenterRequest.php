<?php

namespace App\Http\Requests\Admin\Structure;

use App\Models\CostCenter;

class CostCenterRequest extends StructureRequest
{
    protected function modelClass(): string
    {
        return CostCenter::class;
    }

    protected function routeKey(): string
    {
        return 'cost_center';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->identityRules(),
            'parent_id' => ['nullable', 'integer', $this->inScope(CostCenter::class, 'parent_id'), $this->notOwnDescendant()],
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
