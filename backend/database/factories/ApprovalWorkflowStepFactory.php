<?php

namespace Database\Factories;

use App\Enums\ApproverType;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalWorkflowStep>
 */
class ApprovalWorkflowStepFactory extends Factory
{
    /**
     * Define the model's default state. Pick the approver with one of the
     * approvedBy*() states.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'approval_workflow_id' => ApprovalWorkflow::factory(),
            'sequence' => 1,
            'name' => fake()->words(2, true),
            'approver_type' => ApproverType::Permission,
            'approver_permission' => 'leaves.approve',
        ];
    }

    public function approvedByUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'approver_type' => ApproverType::User,
            'approver_user_id' => $user->id,
            'approver_permission' => null,
        ]);
    }

    public function approvedByRole(Role $role): static
    {
        return $this->state(fn (array $attributes) => [
            'approver_type' => ApproverType::Role,
            'approver_role_id' => $role->id,
            'approver_permission' => null,
        ]);
    }

    public function approvedByPermission(string $permission): static
    {
        return $this->state(fn (array $attributes) => [
            'approver_type' => ApproverType::Permission,
            'approver_permission' => $permission,
        ]);
    }
}
