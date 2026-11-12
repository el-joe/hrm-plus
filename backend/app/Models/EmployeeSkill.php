<?php

namespace App\Models;

use App\Enums\SkillSource;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\ScopedThroughEmployee;
use Database\Factories\EmployeeSkillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An employee's level (1–5) in a skill: one row per employee and skill (the skills matrix).
 */
#[Fillable(['employee_id', 'skill_id', 'level', 'source', 'training_enrolment_id', 'assessed_on', 'updated_by'])]
class EmployeeSkill extends Model
{
    /** @use HasFactory<EmployeeSkillFactory> */
    use Auditable, HasFactory, ScopedThroughEmployee;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['level' => 'integer', 'source' => SkillSource::class, 'assessed_on' => 'date'];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Skill, $this>
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    /**
     * @return BelongsTo<TrainingEnrolment, $this>
     */
    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(TrainingEnrolment::class, 'training_enrolment_id');
    }
}
