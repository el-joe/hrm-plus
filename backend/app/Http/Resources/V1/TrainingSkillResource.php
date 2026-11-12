<?php

namespace App\Http\Resources\V1;

use App\Models\EmployeeSkill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One of the employee's skills (level 1–5 of max_level).
 *
 * @mixin EmployeeSkill
 */
class TrainingSkillResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'skill_id' => $this->skill_id,
            'code' => $this->skill->code,
            'name' => $this->skill->name,
            'category' => $this->skill->category,
            'level' => $this->level,
            'max_level' => 5,
            'source' => $this->source->value,
            'assessed_on' => $this->assessed_on?->toDateString(),
        ];
    }
}
