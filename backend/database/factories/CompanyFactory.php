<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_ar' => 'شركة الأفق',
            'name_en' => fake()->company(),
            'legal_name_ar' => null,
            'legal_name_en' => null,
            'commercial_registration_no' => fake()->numerify('CR-######'),
            'tax_number' => fake()->numerify('###-###-###'),
            'email' => fake()->companyEmail(),
            'phone' => fake()->numerify('+20 2 #### ####'),
            'website' => 'https://'.fake()->domainName(),
            'country_code' => 'EG',
            'address_ar' => null,
            'address_en' => fake()->address(),
            'founded_on' => fake()->date(),
        ];
    }
}
