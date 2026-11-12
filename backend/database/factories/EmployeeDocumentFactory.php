<?php

namespace Database\Factories;

use App\Enums\DocumentCategory;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A national ID copy, hidden from the employee.
 *
 * @extends Factory<EmployeeDocument>
 */
class EmployeeDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'category' => DocumentCategory::NationalId,
            'title' => 'National ID',
            'issue_date' => now()->subYears(2)->toDateString(),
            'expiry_date' => now()->addYears(5)->toDateString(),
            'visible_to_employee' => false,
            'file_path' => 'employee-documents/'.fake()->uuid().'.pdf',
            'file_name' => 'national-id.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => fake()->numberBetween(10_000, 500_000),
        ];
    }
}
