<?php

namespace App\Http\Requests\Admin\Structure;

use App\Models\JobGrade;

class JobGradeRequest extends StructureRequest
{
    protected function modelClass(): string
    {
        return JobGrade::class;
    }

    protected function routeKey(): string
    {
        return 'job_grade';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->identityRules(),
            'level' => ['required', 'integer', 'between:1,999'],
            // Salary bands are financial data: without employee_financials.view they are neither shown nor accepted.
            'min_salary' => $this->user()->can('employee_financials.view') ? ['nullable', 'decimal:0,2', 'min:0', 'max:999999999999.99'] : ['exclude'],
            'max_salary' => $this->user()->can('employee_financials.view') ? ['nullable', 'decimal:0,2', 'min:0', 'max:999999999999.99', 'gte:min_salary'] : ['exclude'],
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
