<?php

namespace Database\Seeders;

use App\Enums\ApproverType;
use App\Enums\CompetencyCategory;
use App\Models\ApprovalWorkflow;
use App\Models\Competency;
use App\Models\PerformanceReview;
use App\Models\RatingScale;
use Illuminate\Database\Seeder;

/**
 * Performance reference data for every environment (editable afterwards):
 *
 * - the "performance_review" approval workflow: one step "HR calibration",
 *   permission performance.approve (the submitting manager and the employee
 *   can never approve — approval engine + ManagePerformanceReview);
 * - the default 1–5 rating scale STD5 with its final-rating thresholds;
 * - eight competencies of a general framework (core, leadership, technical, behavioural).
 */
class PerformanceDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $workflow = ApprovalWorkflow::query()->firstOrCreate(['key' => PerformanceReview::WORKFLOW], [
            'name' => 'Performance review calibration',
            'description' => 'Manager assessments of performance reviews: HR calibrates the final rating before the employee sees it.',
            'is_active' => true,
        ]);

        if ($workflow->steps()->doesntExist()) {
            $workflow->steps()->create([
                'sequence' => 1, 'name' => 'HR calibration', 'approver_type' => ApproverType::Permission, 'approver_permission' => 'performance.approve',
            ]);
        }

        if (RatingScale::query()->where('code', 'STD5')->doesntExist()) {
            $scale = RatingScale::create([
                'code' => 'STD5', 'name_ar' => 'المقياس الخماسي القياسي', 'name_en' => 'Standard 1–5 scale',
                'description' => 'Five levels; the final rating follows the weighted overall score.', 'is_active' => true,
            ]);

            foreach ([
                ['1', 'غير مرضٍ', 'Unsatisfactory', 'Falls clearly short of the expectations of the role.', '0', 'chart-5'],
                ['2', 'يحتاج إلى تحسين', 'Needs improvement', 'Meets some expectations; development needed.', '1.50', 'chart-3'],
                ['3', 'يلبي التوقعات', 'Meets expectations', 'Fully meets the expectations of the role.', '2.50', 'chart-1'],
                ['4', 'يفوق التوقعات', 'Exceeds expectations', 'Often goes beyond what the role expects.', '3.50', 'chart-2'],
                ['5', 'أداء متميز', 'Outstanding', 'Exceptional results and behaviours throughout the period.', '4.50', 'chart-6'],
            ] as [$value, $ar, $en, $description, $min, $color]) {
                $scale->levels()->create(['value' => $value, 'label_ar' => $ar, 'label_en' => $en, 'description' => $description, 'min_score' => $min, 'color' => $color]);
            }
        }

        foreach ([
            ['CUST', 'التركيز على العميل', 'Customer focus', CompetencyCategory::Core, 'Understands and meets the needs of internal and external customers.'],
            ['TEAM', 'العمل الجماعي', 'Teamwork', CompetencyCategory::Core, 'Works with others towards shared goals and shares knowledge.'],
            ['COMM', 'التواصل', 'Communication', CompetencyCategory::Behavioural, 'Communicates clearly and respectfully, in writing and verbally.'],
            ['INTEG', 'النزاهة والالتزام', 'Integrity and accountability', CompetencyCategory::Behavioural, 'Acts ethically and owns results and mistakes.'],
            ['PROB', 'حل المشكلات', 'Problem solving', CompetencyCategory::Technical, 'Analyses issues and finds practical solutions.'],
            ['TECH', 'الخبرة الفنية', 'Job knowledge', CompetencyCategory::Technical, 'Masters the knowledge and tools the role requires.'],
            ['LEAD', 'القيادة وتطوير الآخرين', 'Leadership and developing others', CompetencyCategory::Leadership, 'Sets direction, coaches and develops the team.'],
            ['PLAN', 'التخطيط والتنظيم', 'Planning and organising', CompetencyCategory::Leadership, 'Plans work, sets priorities and meets deadlines.'],
        ] as [$code, $ar, $en, $category, $description]) {
            Competency::query()->firstOrCreate(['code' => $code], [
                'name_ar' => $ar, 'name_en' => $en, 'category' => $category, 'description' => $description, 'is_active' => true,
            ]);
        }
    }
}
