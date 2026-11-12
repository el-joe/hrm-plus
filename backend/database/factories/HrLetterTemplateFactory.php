<?php

namespace Database\Factories;

use App\Models\HrLetterTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active bilingual letter mentioning the employee and the company.
 *
 * @extends Factory<HrLetterTemplate>
 */
class HrLetterTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 99999);

        return [
            'code' => 'LT'.$number,
            'name_ar' => 'خطاب '.$number,
            'name_en' => 'Letter '.$number,
            'body_ar' => 'تشهد {{company_name}} بأن السيد/ة {{employee_name}} ({{employee_number}}) يعمل لديها بوظيفة {{job_title}} منذ {{hire_date}}.',
            'body_en' => '{{company_name}} certifies that {{employee_name}} ({{employee_number}}) has worked as {{job_title}} since {{hire_date}}.',
            'is_active' => true,
        ];
    }

    /**
     * A salary certificate (uses {{basic_salary}}).
     */
    public function withSalary(): static
    {
        return $this->state(fn (array $attributes) => [
            'body_ar' => 'يتقاضى {{employee_name}} راتبًا أساسيًا شهريًا قدره {{basic_salary}} {{salary_currency}}.',
            'body_en' => '{{employee_name}} earns a monthly basic salary of {{basic_salary}} {{salary_currency}}.',
        ]);
    }
}
