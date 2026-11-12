<?php

namespace Database\Factories;

use App\Models\RatingScale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A 1–5 rating scale: 1 (< 1.50), 2 (≥ 1.50), 3 (≥ 2.50), 4 (≥ 3.50), 5 (≥ 4.50).
 *
 * @extends Factory<RatingScale>
 */
class RatingScaleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 99999);

        return ['code' => 'RS'.$number, 'name_ar' => 'مقياس '.$number, 'name_en' => 'Scale '.$number, 'is_active' => true];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (RatingScale $scale): void {
            foreach ([
                ['1', 'غير مرض', 'Unsatisfactory', '0'],
                ['2', 'يحتاج إلى تحسين', 'Needs improvement', '1.50'],
                ['3', 'يلبي التوقعات', 'Meets expectations', '2.50'],
                ['4', 'يفوق التوقعات', 'Exceeds expectations', '3.50'],
                ['5', 'متميز', 'Outstanding', '4.50'],
            ] as $index => [$value, $ar, $en, $min]) {
                $scale->levels()->create(['value' => $value, 'label_ar' => $ar, 'label_en' => $en, 'min_score' => $min, 'color' => 'chart-'.($index + 1)]);
            }
        });
    }
}
