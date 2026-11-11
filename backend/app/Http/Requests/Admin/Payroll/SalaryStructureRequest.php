<?php

namespace App\Http\Requests\Admin\Payroll;

use App\Http\Requests\Admin\Structure\StructureRequest;
use App\Models\SalaryStructure;

/**
 * Create (identity + version 1 with its lines) or update the identity of a
 * salary structure. Lines are validated in detail by SaveSalaryStructureVersion.
 */
class SalaryStructureRequest extends StructureRequest
{
    protected function modelClass(): string
    {
        return SalaryStructure::class;
    }

    protected function routeKey(): string
    {
        return 'salary_structure';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [...$this->identityRules(), 'description' => ['nullable', 'string', 'max:1000']];

        if ($this->record() === null) {
            $rules += StructureVersionRequest::versionRules();
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function structureData(): array
    {
        return collect($this->withBooleans(['is_active']))->only(['code', 'name_ar', 'name_en', 'description', 'is_active'])->all();
    }

    /**
     * @return array{effective_from: string, notes: ?string, lines: list<array<string, mixed>>}
     */
    public function versionData(): array
    {
        return ['effective_from' => $this->validated('effective_from'), 'notes' => $this->validated('notes'), 'lines' => array_values($this->validated('lines') ?? [])];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('payroll.fields');
    }
}
