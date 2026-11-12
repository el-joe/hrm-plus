<?php

namespace Database\Factories;

use App\Enums\ManualBatchStatus;
use App\Enums\RawEventSource;
use App\Models\AttendanceManualBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A draft batch of manual attendance entries.
 *
 * @extends Factory<AttendanceManualBatch>
 */
class AttendanceManualBatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => RawEventSource::Manual,
            'work_date' => now()->subDay()->toDateString(),
            'status' => ManualBatchStatus::Draft,
        ];
    }
}
