<?php

namespace Database\Factories;

use App\Enums\ReportExportStatus;
use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A queued English XLSX export of the employee list.
 *
 * @extends Factory<ReportExport>
 */
class ReportExportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->admin(),
            'report' => 'employees',
            'format' => 'xlsx',
            'locale' => 'en',
            'filters' => [],
            'status' => ReportExportStatus::Queued,
        ];
    }
}
