<?php

namespace App\Http\Requests\Admin\Disciplinary;

use App\Enums\DisciplinaryActionType;
use App\Enums\ViolationSeverity;
use App\Http\Requests\Admin\Structure\StructureRequest;
use App\Models\ViolationType;
use Illuminate\Validation\Rule;

class ViolationTypeRequest extends StructureRequest
{
    protected function modelClass(): string
    {
        return ViolationType::class;
    }

    protected function routeKey(): string
    {
        return 'violation_type';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->identityRules(),
            'description' => ['nullable', 'string', 'max:1000'],
            'severity' => ['required', Rule::enum(ViolationSeverity::class)],
            'default_action' => ['required', Rule::enum(DisciplinaryActionType::class)],
        ];
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
        return [...trans('structure.fields'), ...trans('disciplinary.fields')];
    }
}
