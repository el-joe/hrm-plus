<?php

namespace App\Http\Requests\Admin\Training;

use App\Http\Requests\Admin\Structure\StructureRequest;
use App\Models\Skill;

class SkillRequest extends StructureRequest
{
    protected function modelClass(): string
    {
        return Skill::class;
    }

    protected function routeKey(): string
    {
        return 'skill';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...$this->identityRules(), 'category' => ['nullable', 'string', 'max:50']];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordData(): array
    {
        return $this->withBooleans(['is_active']);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('training.fields');
    }
}
