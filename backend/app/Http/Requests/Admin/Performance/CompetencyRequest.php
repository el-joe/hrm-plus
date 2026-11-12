<?php

namespace App\Http\Requests\Admin\Performance;

use App\Enums\CompetencyCategory;
use App\Http\Requests\Admin\Structure\StructureRequest;
use App\Models\Competency;
use Illuminate\Validation\Rule;

class CompetencyRequest extends StructureRequest
{
    protected function modelClass(): string
    {
        return Competency::class;
    }

    protected function routeKey(): string
    {
        return 'competency';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->identityRules(),
            'category' => ['required', Rule::enum(CompetencyCategory::class)],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordData(): array
    {
        return [...$this->validated(), 'is_active' => $this->boolean('is_active', true)];
    }
}
