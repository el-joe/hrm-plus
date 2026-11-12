<?php

namespace Database\Factories;

use App\Enums\DisciplinaryActionStatus;
use App\Enums\DisciplinaryActionType;
use App\Models\DisciplinaryAction;
use App\Models\DisciplinaryCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending verbal warning on a case (status / issued fields are not fillable:
 * use forceFill or the issued() state).
 *
 * @extends Factory<DisciplinaryAction>
 */
class DisciplinaryActionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'disciplinary_case_id' => DisciplinaryCase::factory(),
            'employee_id' => fn (array $attributes) => DisciplinaryCase::query()->withoutGlobalScopes()->find($attributes['disciplinary_case_id'])->employee_id,
            'type' => DisciplinaryActionType::VerbalWarning,
            'warning_level' => 1,
            'suggested_level' => 1,
            'details' => 'Action details',
            'effective_date' => now()->toDateString(),
            'expires_on' => now()->addYear()->toDateString(),
        ];
    }

    /**
     * Approved and issued to the employee.
     */
    public function issued(): static
    {
        return $this->afterCreating(function (DisciplinaryAction $action): void {
            $action->forceFill([
                'status' => DisciplinaryActionStatus::Approved, 'decided_at' => now(), 'issued_at' => now(),
                'response_due_on' => now()->addDays(7)->toDateString(),
            ])->save();
        });
    }
}
