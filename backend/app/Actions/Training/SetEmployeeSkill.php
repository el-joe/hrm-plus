<?php

namespace App\Actions\Training;

use App\Enums\SkillSource;
use App\Models\Employee;
use App\Models\EmployeeSkill;
use App\Models\Skill;
use App\Models\User;

/**
 * Sets an employee's level in a skill by hand (skills matrix). Level 0 removes it.
 */
class SetEmployeeSkill
{
    public function handle(Employee $employee, Skill $skill, int $level, User $actor): ?EmployeeSkill
    {
        $current = EmployeeSkill::query()->withoutGlobalScopes()->where('employee_id', $employee->id)->where('skill_id', $skill->id)->first();

        if ($level === 0) {
            $current?->delete();

            return null;
        }

        $current ??= new EmployeeSkill(['employee_id' => $employee->id, 'skill_id' => $skill->id]);
        $current->fill([
            'level' => $level, 'source' => SkillSource::Manual, 'training_enrolment_id' => null,
            'assessed_on' => today()->toDateString(), 'updated_by' => $actor->id,
        ])->save();

        return $current;
    }
}
