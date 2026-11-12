<?php

namespace Database\Factories;

use App\Enums\ApprovalActionType;
use App\Models\ApprovalAction;
use App\Models\ApprovalRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The "submitted" entry of an approval request's history.
 *
 * @extends Factory<ApprovalAction>
 */
class ApprovalActionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'approval_request_id' => ApprovalRequest::factory(),
            'action' => ApprovalActionType::Submitted,
        ];
    }
}
