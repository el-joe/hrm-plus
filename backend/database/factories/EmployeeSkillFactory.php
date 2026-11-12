<?php

namespace Database\Factories;

use App\Enums\SkillSource;
use App\Models\Employee;
use App\Models\EmployeeSkill;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeSkill>
 */
class EmployeeSkillFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'skill_id' => Skill::factory(),
            'level' => 2,
            'source' => SkillSource::Manual,
            'assessed_on' => now()->toDateString(),
        ];
    }
}
