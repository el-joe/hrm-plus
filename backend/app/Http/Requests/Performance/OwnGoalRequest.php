<?php

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A goal the employee adds for themselves (portal / API), when the setting
 * performance.employees_can_add_goals is on. The cycle must be one of the
 * employee's open cycles (checked by the controller through PortalPerformance).
 */
class OwnGoalRequest extends FormRequest
{
    use GoalRules;

    public function authorize(): bool
    {
        return (bool) setting('performance.employees_can_add_goals') && $this->user()?->employee_id !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->goalRules();
    }
}
