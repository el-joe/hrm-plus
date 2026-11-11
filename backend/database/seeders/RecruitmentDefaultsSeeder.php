<?php

namespace Database\Seeders;

use App\Enums\ApproverType;
use App\Models\ApprovalWorkflow;
use App\Models\EvaluationForm;
use App\Models\JobOffer;
use App\Models\OnboardingTemplate;
use App\Services\Recruitment\Pipeline;
use Illuminate\Database\Seeder;

/**
 * Reference data of Phase 4, seeded in every environment and idempotent:
 * the company default recruitment pipeline (spec module 4 stages), the
 * "job_offer" approval workflow (one step: offers.approve), a general
 * interview evaluation form and a default onboarding checklist (spec module 5
 * examples). All of them are editable in the admin area afterwards.
 */
class RecruitmentDefaultsSeeder extends Seeder
{
    public function run(Pipeline $pipeline): void
    {
        $pipeline->seedDefaults();

        $workflow = ApprovalWorkflow::query()->firstOrCreate(['key' => JobOffer::WORKFLOW], [
            'name' => 'Job offer approval',
            'description' => 'Offers are approved before they are sent to the candidate.',
            'is_active' => true,
        ]);

        if ($workflow->steps()->doesntExist()) {
            $workflow->steps()->create([
                'sequence' => 1,
                'name' => 'HR approval',
                'approver_type' => ApproverType::Permission,
                'approver_permission' => 'offers.approve',
            ]);
        }

        if (EvaluationForm::query()->doesntExist()) {
            $form = EvaluationForm::create(['name_ar' => 'تقييم المقابلة العام', 'name_en' => 'General interview evaluation', 'is_active' => true]);

            foreach ([
                ['المعرفة الفنية', 'Technical knowledge', 3],
                ['مهارات التواصل', 'Communication', 2],
                ['حل المشكلات', 'Problem solving', 2],
                ['الملاءمة للثقافة والفريق', 'Culture and team fit', 1],
            ] as $index => [$ar, $en, $weight]) {
                $form->criteria()->create(['name_ar' => $ar, 'name_en' => $en, 'weight' => $weight, 'sort_order' => ($index + 1) * 10]);
            }
        }

        if (OnboardingTemplate::query()->doesntExist()) {
            $template = OnboardingTemplate::create([
                'name_ar' => 'قائمة الانضمام القياسية', 'name_en' => 'Standard onboarding', 'is_active' => true,
                'description' => 'Default checklist for every new hire.',
            ]);

            foreach ([
                ['تسليم المستندات الشخصية (الهوية، المؤهلات، الصور)', 'Hand in personal documents (ID, certificates, photos)', 'employee', -2],
                ['توقيع عقد العمل', 'Sign the employment contract', 'hr', 0],
                ['إنشاء حساب الموظف وحساب البريد', 'Create the employee and e-mail accounts', 'it', -1],
                ['التأكد من الفرع والقسم والمدير المباشر', 'Confirm branch, department and direct manager', 'hr', 0],
                ['تسليم العهد (حاسب، بطاقة الدخول)', 'Assign company assets (laptop, access card)', 'it', 0],
                ['تحديد جدول العمل والوردية', 'Assign the work schedule', 'manager', 1],
                ['قراءة لائحة العمل وسياسات الشركة', 'Read the work regulations and company policies', 'employee', 3],
                ['تعبئة بيانات الحساب البنكي والطوارئ', 'Complete bank and emergency contact details', 'employee', 3],
                ['لقاء تعريفي مع المدير والفريق', 'Welcome meeting with the manager and the team', 'manager', 1],
                ['مراجعة نهاية الأسبوع الأول', 'End of first week review', 'manager', 7],
            ] as $index => [$ar, $en, $assignee, $offset]) {
                $template->tasks()->create([
                    'title_ar' => $ar, 'title_en' => $en, 'assignee' => $assignee,
                    'due_offset_days' => $offset, 'sort_order' => ($index + 1) * 10,
                ]);
            }
        }
    }
}
