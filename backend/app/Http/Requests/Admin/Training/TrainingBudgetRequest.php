<?php

namespace App\Http\Requests\Admin\Training;

use App\Models\Department;
use App\Models\TrainingBudget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrainingBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $budget = $this->route('training_budget');

        return $budget instanceof TrainingBudget ? $this->user()->can('update', $budget) : $this->user()->can('create', TrainingBudget::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $budget = $this->route('training_budget');

        return [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'department_id' => [
                'nullable', 'integer', Rule::exists(Department::class, 'id'),
                Rule::unique('training_budgets')->where(fn ($query) => $query->where('year', $this->integer('year'))
                    ->where(fn ($query) => $this->filled('department_id') ? $query->where('department_id', $this->integer('department_id')) : $query->whereNull('department_id')))
                    ->ignore($budget instanceof TrainingBudget ? $budget->id : null),
            ],
            'amount' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['department_id.unique' => __('training.validation.budget_exists')];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('training.fields');
    }
}
