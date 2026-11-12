<?php

namespace App\Http\Requests\Admin\Performance;

use App\Http\Requests\Performance\GoalRules;
use App\Models\Employee;
use App\Models\PerformanceGoal;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin goal form: create for a visible employee (PerformanceGoalPolicy::createFor),
 * or edit an existing goal (update).
 */
class PerformanceGoalRequest extends FormRequest
{
    use GoalRules;

    public function authorize(): bool
    {
        $goal = $this->route('performance_goal');

        if ($goal instanceof PerformanceGoal) {
            return $this->user()->can('update', $goal);
        }

        $employee = Employee::query()->find($this->integer('employee_id'));

        return $employee !== null && $this->user()->can('createFor', [PerformanceGoal::class, $employee]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...($this->route('performance_goal') ? [] : ['employee_id' => ['required', 'integer']]),
            ...$this->goalRules(),
        ];
    }

    public function employee(): Employee
    {
        return Employee::query()->findOrFail($this->integer('employee_id'));
    }
}
