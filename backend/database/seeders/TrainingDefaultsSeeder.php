<?php

namespace Database\Seeders;

use App\Enums\ApproverType;
use App\Models\ApprovalWorkflow;
use App\Models\TrainingEnrolment;
use Illuminate\Database\Seeder;

/**
 * Training reference data for every environment: the "training_enrolment" approval
 * workflow (employees' own requests and nominations by managers without
 * training.manage), one level — permission training.approve (HR Manager by default),
 * inside the approver's data scope. Editable afterwards.
 */
class TrainingDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $workflow = ApprovalWorkflow::query()->firstOrCreate(['key' => TrainingEnrolment::WORKFLOW], [
            'name' => 'Training request approval',
            'description' => 'Employees\' training requests and nominations by managers.',
            'is_active' => true,
        ]);

        if ($workflow->steps()->doesntExist()) {
            $workflow->steps()->create([
                'sequence' => 1, 'name' => 'Training approval', 'approver_type' => ApproverType::Permission, 'approver_permission' => 'training.approve',
            ]);
        }
    }
}
