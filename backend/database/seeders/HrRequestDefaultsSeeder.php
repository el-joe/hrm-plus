<?php

namespace Database\Seeders;

use App\Enums\ApproverType;
use App\Enums\HrRequestCategoryType;
use App\Enums\HrRequestPriority;
use App\Models\ApprovalWorkflow;
use App\Models\HrLetterTemplate;
use App\Models\HrRequest;
use App\Models\HrRequestCategory;
use Illuminate\Database\Seeder;

/**
 * Reference data of HR requests (Phase 8b, every environment):
 *
 * - the "hr_request" approval workflow: one step "Manager approval" (permission hr_requests.approve) — used
 *   by categories flagged requires_approval;
 * - three bilingual letter templates: salary certificate, employment certificate, experience letter;
 * - the request categories of spec module 13 (certificates, leave balance / salary inquiries, document
 *   request, general inquiry, personal information correction, other).
 *
 * Idempotent (firstOrCreate by key / code); never overwrites edits. Default assignees are left empty here
 * (the demo seeder sets them for the development accounts).
 */
class HrRequestDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $workflow = ApprovalWorkflow::query()->firstOrCreate(['key' => HrRequest::WORKFLOW], [
            'name' => 'HR request approval',
            'description' => 'HR requests of categories that need approval (e.g. "Other") before HR handles them.',
            'is_active' => true,
        ]);

        if ($workflow->steps()->doesntExist()) {
            $workflow->steps()->create([
                'sequence' => 1, 'name' => 'Manager approval', 'approver_type' => ApproverType::Permission, 'approver_permission' => 'hr_requests.approve',
            ]);
        }

        $templates = [];

        foreach ($this->letters() as $code => [$nameAr, $nameEn, $bodyAr, $bodyEn]) {
            $templates[$code] = HrLetterTemplate::query()->firstOrCreate(['code' => $code], [
                'name_ar' => $nameAr, 'name_en' => $nameEn, 'body_ar' => $bodyAr, 'body_en' => $bodyEn, 'is_active' => true,
            ]);
        }

        $addressee = ['key' => 'addressee', 'label_ar' => 'الجهة الموجّه إليها الخطاب', 'label_en' => 'Addressed to', 'type' => 'text', 'is_required' => false];

        $categories = [
            ['SALARY_CERT', 'شهادة راتب', 'Salary certificate', 'شهادة بالراتب الأساسي لتقديمها للبنوك أو السفارات.', 'A certificate of your basic salary for banks or embassies.',
                HrRequestCategoryType::Letter, 'SALARY_CERTIFICATE', 48, false, false, [$addressee]],
            ['EMPLOYMENT_CERT', 'شهادة قيد / تعريف بالعمل', 'Employment certificate', 'تثبت أنك تعمل لدى الشركة ومسمّاك الوظيفي.', 'Confirms that you work for the company and your job title.',
                HrRequestCategoryType::Letter, 'EMPLOYMENT_CERTIFICATE', 48, false, false, [$addressee]],
            ['EXPERIENCE_LETTER', 'شهادة خبرة', 'Experience letter', 'خطاب بمدة خدمتك ووظيفتك.', 'A letter stating your period of service and role.',
                HrRequestCategoryType::Letter, 'EXPERIENCE_LETTER', 72, false, false, [$addressee]],
            ['LEAVE_INQUIRY', 'استفسار عن رصيد الإجازات', 'Leave balance inquiry', 'اسأل عن رصيدك أو المرحَّل أو الاستحقاق.', 'Questions about your balance, carried-over days or accruals.',
                HrRequestCategoryType::General, null, 24, false, false, []],
            ['SALARY_INQUIRY', 'استفسار عن الراتب', 'Salary inquiry', 'أسئلة عن قسيمة الراتب أو الاستقطاعات أو البدلات.', 'Questions about a payslip, deductions or allowances.',
                HrRequestCategoryType::General, null, 48, false, false, [
                    ['key' => 'period', 'label_ar' => 'الشهر المعني', 'label_en' => 'Payroll month', 'type' => 'text', 'is_required' => false],
                ]],
            ['DOCUMENT_REQUEST', 'طلب مستند', 'Document request', 'نسخة من عقد أو مستند في ملفك. أرفق ما يوضح طلبك.', 'A copy of a contract or a document on your file. Attach what explains your request.',
                HrRequestCategoryType::General, null, 72, false, true, [
                    ['key' => 'document_type', 'label_ar' => 'نوع المستند', 'label_en' => 'Document', 'type' => 'select', 'is_required' => true,
                        'options' => ['Employment contract copy', 'Social insurance print-out', 'ID copy on file', 'Other']],
                ]],
            ['GENERAL_INQUIRY', 'استفسار عام', 'General HR inquiry', 'أي سؤال للموارد البشرية.', 'Any question for HR.',
                HrRequestCategoryType::General, null, 72, false, false, []],
            ['PROFILE_CORRECTION', 'تصحيح البيانات الشخصية', 'Personal information correction', 'اطلب تعديل بيانات التواصل أو الهوية أو الحساب البنكي.', 'Ask to correct contact, identity or bank details.',
                HrRequestCategoryType::ProfileChange, null, 48, false, false, []],
            ['OTHER', 'طلب آخر', 'Other request', 'طلبات أخرى تحتاج موافقة المدير قبل المعالجة.', 'Other requests; your manager approves them before HR handles them.',
                HrRequestCategoryType::General, null, 96, true, false, []],
        ];

        foreach ($categories as $order => [$code, $nameAr, $nameEn, $descriptionAr, $descriptionEn, $type, $template, $sla, $approval, $attachment, $fields]) {
            $category = HrRequestCategory::query()->firstOrCreate(['code' => $code], [
                'name_ar' => $nameAr, 'name_en' => $nameEn, 'description_ar' => $descriptionAr, 'description_en' => $descriptionEn,
                'type' => $type, 'hr_letter_template_id' => $template !== null ? $templates[$template]->id : null, 'sla_hours' => $sla,
                'default_priority' => HrRequestPriority::Normal, 'requires_approval' => $approval, 'requires_attachment' => $attachment,
                'is_active' => true, 'sort_order' => $order,
            ]);

            if ($category->wasRecentlyCreated) {
                foreach ($fields as $index => $field) {
                    $category->fields()->create([...$field, 'options' => $field['options'] ?? null, 'sort_order' => $index]);
                }
            }
        }
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    private function letters(): array
    {
        return [
            'SALARY_CERTIFICATE' => [
                'شهادة راتب',
                'Salary certificate',
                "إلى: {{addressee}}\n\nتشهد {{company_name}} بأن السيد/السيدة {{employee_name}}، الرقم الوظيفي {{employee_number}}، يعمل لديها بوظيفة {{job_title}} بإدارة {{department}} ({{branch}}) منذ {{hire_date}}، ولا يزال على رأس العمل حتى تاريخه.\n\nويتقاضى راتبًا أساسيًا شهريًا قدره {{basic_salary}} {{salary_currency}}.\n\nوقد أُعطيت له هذه الشهادة بناءً على طلبه دون أدنى مسؤولية على الشركة.",
                "To: {{addressee}}\n\n{{company_name}} certifies that {{employee_name}}, employee number {{employee_number}}, has been employed as {{job_title}} in {{department}} ({{branch}}) since {{hire_date}} and remains in service to date.\n\nThe employee's monthly basic salary is {{basic_salary}} {{salary_currency}}.\n\nThis certificate is issued at the employee's request without any liability on the company.",
            ],
            'EMPLOYMENT_CERTIFICATE' => [
                'شهادة تعريف بالعمل',
                'Employment certificate',
                "إلى: {{addressee}}\n\nتشهد {{company_name}} بأن السيد/السيدة {{employee_name}}، الرقم الوظيفي {{employee_number}}، يعمل لديها بوظيفة {{job_title}} بإدارة {{department}} منذ {{hire_date}}.\n\nوقد أُعطيت له هذه الشهادة بناءً على طلبه.",
                "To: {{addressee}}\n\n{{company_name}} certifies that {{employee_name}}, employee number {{employee_number}}, has been employed as {{job_title}} in {{department}} since {{hire_date}}.\n\nThis certificate is issued at the employee's request.",
            ],
            'EXPERIENCE_LETTER' => [
                'شهادة خبرة',
                'Experience letter',
                "إلى: {{addressee}}\n\nتشهد {{company_name}} بأن السيد/السيدة {{employee_name}} عمل لديها بوظيفة {{job_title}} في {{department}} اعتبارًا من {{hire_date}}، وقد أدى خلال هذه الفترة المهام المسندة إليه بكفاءة.\n\nونتمنى له التوفيق.",
                "To: {{addressee}}\n\n{{company_name}} certifies that {{employee_name}} has worked for the company as {{job_title}} in {{department}} since {{hire_date}}, carrying out the duties of the role competently.\n\nWe wish them every success.",
            ],
        ];
    }
}
