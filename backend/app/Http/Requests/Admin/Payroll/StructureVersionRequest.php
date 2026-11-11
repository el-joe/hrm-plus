<?php

namespace App\Http\Requests\Admin\Payroll;

use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StructureVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $structure = $this->route('salary_structure');

        return $structure instanceof SalaryStructure && $this->user()->can('update', $structure);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::versionRules();
    }

    /**
     * @return array<string, mixed>
     */
    public static function versionRules(): array
    {
        return [
            'effective_from' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'max:60'],
            'lines.*.salary_component_id' => ['nullable', 'integer', Rule::exists(SalaryComponent::class, 'id')],
            'lines.*.amount' => ['nullable', 'decimal:0,4', 'min:0', 'max:9999999999'],
            'lines.*.rate' => ['nullable', 'decimal:0,4', 'min:0', 'max:99999'],
            'lines.*.base_component_id' => ['nullable', 'integer', Rule::exists(SalaryComponent::class, 'id')],
            'lines.*.sort_order' => ['nullable', 'integer', 'between:0,9999'],
        ];
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
