<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use Database\Factories\SkillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A skill of the skills matrix. Employees hold it at a level 1–5 (employee_skills),
 * set by HR or raised by completing a course that teaches it (training_course_skills).
 */
#[Fillable(['code', 'name_ar', 'name_en', 'category', 'is_active'])]
class Skill extends Model
{
    /** @use HasFactory<SkillFactory> */
    use Auditable, HasActiveFlag, HasFactory, HasLocalizedName;

    public const MAX_LEVEL = 5;

    /**
     * @return HasMany<EmployeeSkill, $this>
     */
    public function employeeSkills(): HasMany
    {
        return $this->hasMany(EmployeeSkill::class);
    }

    /**
     * @return HasMany<TrainingCourseSkill, $this>
     */
    public function courseSkills(): HasMany
    {
        return $this->hasMany(TrainingCourseSkill::class);
    }

    public function isInUse(): bool
    {
        return $this->employeeSkills()->withoutGlobalScopes()->exists() || $this->courseSkills()->exists();
    }
}
