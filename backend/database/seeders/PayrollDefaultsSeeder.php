<?php

namespace Database\Seeders;

use App\Enums\ApproverType;
use App\Models\ApprovalWorkflow;
use App\Models\EmployeeLoan;
use App\Models\PayEntry;
use Illuminate\Database\Seeder;

/**
 * Payroll reference data for every environment: the approval workflows of
 * the component layer (editable afterwards). Components, structures and
 * statutory rule tables are company data and are NOT seeded in production —
 * in particular no tax or social insurance rate (blocker B7).
 *
 * - payroll_allowance / payroll_deduction: entries of components flagged
 *   "requires approval", one level (allowances.approve / deductions.approve);
 * - loan_request: loans and advances, one level (loans.approve);
 * - loan_change: skipping an instalment or rescheduling, one level (loans.approve).
 */
class PayrollDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            [PayEntry::WORKFLOWS['earning'], 'Allowance approval', 'Allowances, bonuses and incentives of components that require approval.', 'Finance approval', 'allowances.approve'],
            [PayEntry::WORKFLOWS['deduction'], 'Deduction approval', 'Deductions and penalties of components that require approval.', 'Finance approval', 'deductions.approve'],
            [EmployeeLoan::WORKFLOW, 'Loan and advance approval', 'Employee loan and salary advance requests.', 'Loan approval', 'loans.approve'],
            [EmployeeLoan::CHANGE_WORKFLOW, 'Loan schedule change approval', 'Skipping an instalment or rescheduling a loan.', 'Loan approval', 'loans.approve'],
        ] as [$key, $name, $description, $stepName, $permission]) {
            $workflow = ApprovalWorkflow::query()->firstOrCreate(['key' => $key], ['name' => $name, 'description' => $description, 'is_active' => true]);

            if ($workflow->steps()->doesntExist()) {
                $workflow->steps()->create([
                    'sequence' => 1, 'name' => $stepName, 'approver_type' => ApproverType::Permission, 'approver_permission' => $permission,
                ]);
            }
        }
    }
}
