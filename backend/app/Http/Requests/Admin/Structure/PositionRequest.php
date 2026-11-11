<?php

namespace App\Http\Requests\Admin\Structure;

use App\Models\Department;
use App\Models\JobGrade;
use App\Models\JobTitle;
use App\Models\Position;
use App\Services\Employees\PositionCapacity;
use Closure;

class PositionRequest extends StructureRequest
{
    protected function modelClass(): string
    {
        return Position::class;
    }

    protected function routeKey(): string
    {
        return 'position';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->identityRules(),
            'department_id' => ['required', 'integer', $this->inScope(Department::class, 'department_id')],
            'job_title_id' => ['required', 'integer', $this->inScope(JobTitle::class, 'job_title_id')],
            'job_grade_id' => ['nullable', 'integer', $this->inScope(JobGrade::class, 'job_grade_id')],
            'reports_to_position_id' => ['nullable', 'integer', $this->inScope(Position::class, 'reports_to_position_id'), $this->notOwnDescendant()],
            'headcount' => ['required', 'integer', 'between:1,9999', $this->notBelowHolders()],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * The approved headcount can never drop below the employees holding the position.
     */
    private function notBelowHolders(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $position = $this->record();

            if ($position instanceof Position && is_numeric($value)) {
                $holders = app(PositionCapacity::class)->holders($position);

                if ((int) $value < $holders) {
                    $fail(__('employees.validation.headcount_below_holders', ['count' => $holders]));
                }
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function recordData(): array
    {
        return $this->withBooleans(['is_active']);
    }
}
