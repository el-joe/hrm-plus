<?php

namespace Database\Factories;

use App\Models\Skill;
use App\Models\TrainingCourse;
use App\Models\TrainingCourseSkill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A skill a course develops up to level 3.
 *
 * @extends Factory<TrainingCourseSkill>
 */
class TrainingCourseSkillFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'training_course_id' => TrainingCourse::factory(),
            'skill_id' => Skill::factory(),
            'level' => 3,
        ];
    }
}
