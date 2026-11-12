<?php

namespace Database\Factories;

use App\Enums\HrRequestCategoryType;
use App\Enums\HrRequestPriority;
use App\Models\HrLetterTemplate;
use App\Models\HrRequestCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active general inquiry category with a 48-hour SLA, no approval, no attachment rule.
 *
 * @extends Factory<HrRequestCategory>
 */
class HrRequestCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 99999);

        return [
            'code' => 'CAT'.$number,
            'name_ar' => 'تصنيف '.$number,
            'name_en' => 'Category '.$number,
            'type' => HrRequestCategoryType::General,
            'sla_hours' => 48,
            'default_priority' => HrRequestPriority::Normal,
            'requires_approval' => false,
            'requires_attachment' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function letter(?HrLetterTemplate $template = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => HrRequestCategoryType::Letter,
            'hr_letter_template_id' => $template?->id ?? HrLetterTemplate::factory(),
        ]);
    }

    public function profileChange(): static
    {
        return $this->state(fn (array $attributes) => ['type' => HrRequestCategoryType::ProfileChange]);
    }

    public function withApproval(): static
    {
        return $this->state(fn (array $attributes) => ['requires_approval' => true]);
    }
}
