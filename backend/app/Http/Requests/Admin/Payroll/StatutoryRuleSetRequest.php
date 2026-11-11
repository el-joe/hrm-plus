<?php

namespace App\Http\Requests\Admin\Payroll;

use App\Enums\StatutoryType;
use App\Http\Requests\Admin\Structure\StructureRequest;
use App\Models\StatutoryRuleSet;
use Illuminate\Validation\Rule;

/**
 * A statutory rule table (identity). The country and type are fixed once the
 * table has versions; "demo" marks illustrative values.
 */
class StatutoryRuleSetRequest extends StructureRequest
{
    protected function modelClass(): string
    {
        return StatutoryRuleSet::class;
    }

    protected function routeKey(): string
    {
        return 'statutory_rule';
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if ($this->has('country_code')) {
            $this->merge(['country_code' => strtoupper(trim((string) $this->input('country_code')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $locked = $this->record()?->versions()->exists() ?? false;

        return [
            ...$this->identityRules(),
            'description' => ['nullable', 'string', 'max:1000'],
            'country_code' => [$locked ? 'prohibited' : 'required', 'string', 'size:2', 'alpha'],
            'type' => [$locked ? 'prohibited' : 'required', Rule::enum(StatutoryType::class)],
            'is_demo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordData(): array
    {
        return $this->withBooleans(['is_demo', 'is_active']);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('payroll.fields');
    }
}
