<?php

namespace Database\Seeders;

use App\Enums\ApproverType;
use App\Enums\DisciplinaryActionType;
use App\Enums\ViolationSeverity;
use App\Models\ApprovalWorkflow;
use App\Models\DisciplinaryAction;
use App\Models\ViolationType;
use Illuminate\Database\Seeder;

/**
 * Disciplinary reference data for every environment (editable afterwards):
 *
 * - the "disciplinary_action" approval workflow: one step "HR approval",
 *   permission disciplinary.approve (data scope applies; never the proposer,
 *   never on an action about yourself);
 * - a starter catalogue of six violation types (bilingual), with severity and
 *   the action suggested for a first offence.
 */
class DisciplinaryDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $workflow = ApprovalWorkflow::query()->firstOrCreate(['key' => DisciplinaryAction::WORKFLOW], [
            'name' => 'Disciplinary action approval',
            'description' => 'Warnings, suspensions, salary deductions and termination recommendations proposed on a disciplinary case.',
            'is_active' => true,
        ]);

        if ($workflow->steps()->doesntExist()) {
            $workflow->steps()->create([
                'sequence' => 1, 'name' => 'HR approval', 'approver_type' => ApproverType::Permission, 'approver_permission' => 'disciplinary.approve',
            ]);
        }

        foreach ([
            ['LATE', 'التأخر المتكرر عن مواعيد العمل', 'Repeated late attendance', ViolationSeverity::Minor, DisciplinaryActionType::VerbalWarning],
            ['ABSENT', 'الغياب دون إذن أو إخطار', 'Absence without leave or notice', ViolationSeverity::Moderate, DisciplinaryActionType::WrittenWarning],
            ['POLICY', 'مخالفة سياسات ولوائح الشركة', 'Breach of company policy', ViolationSeverity::Moderate, DisciplinaryActionType::VerbalWarning],
            ['CONDUCT', 'سوء السلوك في مكان العمل', 'Misconduct at work', ViolationSeverity::Major, DisciplinaryActionType::WrittenWarning],
            ['SAFETY', 'مخالفة تعليمات السلامة المهنية', 'Health and safety violation', ViolationSeverity::Major, DisciplinaryActionType::FinalWarning],
            ['ASSETS', 'إساءة استخدام أصول الشركة', 'Misuse of company assets', ViolationSeverity::Gross, DisciplinaryActionType::SalaryDeduction],
        ] as [$code, $ar, $en, $severity, $action]) {
            ViolationType::query()->firstOrCreate(['code' => $code], [
                'name_ar' => $ar, 'name_en' => $en, 'severity' => $severity, 'default_action' => $action, 'is_active' => true,
            ]);
        }
    }
}
