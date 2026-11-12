<?php

namespace App\Http\Requests\Performance;

use App\Enums\GoalType;
use Illuminate\Validation\Rule;

/**
 * Validation rules of a goal / KPI shared by the admin form, the portal and the API.
 */
trait GoalRules
{
    /**
     * @return array<string, mixed>
     */
    protected function goalRules(): array
    {
        return [
            'performance_cycle_id' => ['nullable', 'integer', 'exists:performance_cycles,id'],
            'type' => ['required', Rule::enum(GoalType::class)],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'unit' => ['nullable', 'string', 'max:30'],
            'target_value' => ['nullable', 'required_if:type,kpi', 'decimal:0,2', 'gt:0', 'max:999999999999'],
            'current_value' => ['nullable', 'decimal:0,2', 'min:0', 'max:999999999999'],
            'weight' => ['required', 'decimal:0,2', 'min:0', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}
