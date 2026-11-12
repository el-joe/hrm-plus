<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\TrainingCourseSkillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A skill a course teaches and the level (1–5) a participant reaches on completing it.
 */
#[Fillable(['training_course_id', 'skill_id', 'level'])]
class TrainingCourseSkill extends Model
{
    /** @use HasFactory<TrainingCourseSkillFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['level' => 'integer'];
    }

    /**
     * @return BelongsTo<TrainingCourse, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'training_course_id');
    }

    /**
     * @return BelongsTo<Skill, $this>
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
