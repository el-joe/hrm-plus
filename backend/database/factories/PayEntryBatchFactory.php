<?php

namespace Database\Factories;

use App\Enums\PayComponentKind;
use App\Models\PayEntryBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An imported batch of allowances.
 *
 * @extends Factory<PayEntryBatch>
 */
class PayEntryBatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => 'import',
            'kind' => PayComponentKind::Earning,
            'entries_count' => 0,
            'file_name' => 'allowances.xlsx',
        ];
    }
}
