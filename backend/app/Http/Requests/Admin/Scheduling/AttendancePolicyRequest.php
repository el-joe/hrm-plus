<?php

namespace App\Http\Requests\Admin\Scheduling;

use App\Http\Requests\Admin\Structure\StructureRequest;
use App\Models\AttendancePolicy;

/**
 * Create: identity + the rules of version 1 + its effective date. Edit: identity only.
 */
class AttendancePolicyRequest extends StructureRequest
{
    use PolicyRuleRules;

    protected function modelClass(): string
    {
        return AttendancePolicy::class;
    }

    protected function routeKey(): string
    {
        return 'attendance_policy';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $identity = [...$this->identityRules(), 'description' => ['nullable', 'string', 'max:1000']];

        if ($this->record() !== null) {
            return $identity;
        }

        return [...$identity, ...$this->ruleRules(), 'effective_from' => ['required', 'date_format:Y-m-d']];
    }

    /**
     * @return array<string, mixed>
     */
    public function identityData(): array
    {
        return collect($this->withBooleans(['is_active']))->only(['code', 'name_ar', 'name_en', 'description', 'is_active'])->all();
    }
}
