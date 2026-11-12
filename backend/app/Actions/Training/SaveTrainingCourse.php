<?php

namespace App\Actions\Training;

use App\Models\TrainingCourse;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a course with the skills it teaches (skill id => level 1–5).
 */
class SaveTrainingCourse
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $skills
     */
    public function handle(?TrainingCourse $course, array $data, array $skills): TrainingCourse
    {
        return DB::transaction(function () use ($course, $data, $skills): TrainingCourse {
            if (! $data['issues_certificate']) {
                $data['certificate_validity_months'] = null;
            }

            $course ??= new TrainingCourse;
            $course->fill($data)->save();

            $course->courseSkills()->whereNotIn('skill_id', array_keys($skills))->get()->each->delete();

            foreach ($skills as $skillId => $level) {
                $course->courseSkills()->updateOrCreate(['skill_id' => $skillId], ['level' => $level]);
            }

            return $course;
        });
    }
}
