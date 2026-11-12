<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\EmploymentType;
use App\Models\JobGrade;
use App\Models\JobTitle;
use App\Models\Position;
use App\Models\Team;
use App\Services\Settings\SettingsService;
use Illuminate\Database\Seeder;

/**
 * Demo organisation for development only (never run in production): a
 * company with three branches, a department tree, teams, a job catalogue and
 * a position reporting hierarchy. Idempotent: records are matched by code.
 */
class OrganisationStructureSeeder extends Seeder
{
    public function run(): void
    {
        $this->company();

        // The demo company is in Egypt: its timezone matches the branches (Africa/Cairo),
        // so "today" on the dashboards is Cairo's today.
        app(SettingsService::class)->set('localisation.timezone', 'Africa/Cairo');
        // ... and its currency is the Egyptian pound (salaries, payroll and asset values are in EGP).
        app(SettingsService::class)->set('localisation.currency_code', 'EGP');

        $grades = $this->jobGrades();
        $titles = $this->jobTitles();
        $this->employmentTypes();
        $costCenters = $this->costCenters();
        $branches = $this->branches();
        $departments = $this->departments($branches, $costCenters);
        $this->teams($departments);
        $positions = $this->positions($departments, $titles, $grades);

        foreach (['EXEC' => 'CEO', 'HR' => 'HRD', 'FIN' => 'CFO', 'IT' => 'ITM', 'SAL' => 'SALD', 'SAL-CAI' => 'SALM-CAI',
            'SAL-ALX' => 'SALM-ALX', 'OPS' => 'OPSM', 'OPS-WH' => 'WHS', 'HR-TA' => 'TAL', 'FIN-PAY' => 'PAYS'] as $department => $position) {
            $departments[$department]->update(['head_position_id' => $positions[$position]->id]);
        }

        foreach (['CAI' => 'CEO', 'ALX' => 'OPSM', 'GIZ' => 'ITM'] as $branch => $position) {
            $branches[$branch]->update(['manager_position_id' => $positions[$position]->id]);
        }
    }

    private function company(): void
    {
        $company = Company::query()->oldest('id')->first() ?? new Company;

        $company->fill([
            'name_ar' => 'شركة الأفق للحلول المتكاملة',
            'name_en' => 'Horizon Integrated Solutions',
            'legal_name_ar' => 'شركة الأفق للحلول المتكاملة ش.م.م',
            'legal_name_en' => 'Horizon Integrated Solutions LLC',
            'commercial_registration_no' => '184562',
            'tax_number' => '512-874-309',
            'email' => 'info@horizon.test',
            'phone' => '+20 2 2614 5500',
            'website' => 'https://horizon.test',
            'country_code' => 'EG',
            'address_ar' => 'التجمع الخامس، القاهرة الجديدة، القاهرة',
            'address_en' => 'Fifth Settlement, New Cairo, Cairo',
            'founded_on' => '2012-03-15',
        ])->save();
    }

    /**
     * @return array<string, JobGrade>
     */
    private function jobGrades(): array
    {
        $rows = [
            ['G1', 'الدرجة الأولى - مبتدئ', 'Grade 1 - Entry', 1, '6000.00', '9000.00'],
            ['G2', 'الدرجة الثانية - موظف', 'Grade 2 - Associate', 2, '8500.00', '13000.00'],
            ['G3', 'الدرجة الثالثة - أخصائي', 'Grade 3 - Specialist', 3, '12000.00', '19000.00'],
            ['G4', 'الدرجة الرابعة - أخصائي أول', 'Grade 4 - Senior specialist', 4, '18000.00', '27000.00'],
            ['G5', 'الدرجة الخامسة - مشرف', 'Grade 5 - Supervisor', 5, '25000.00', '36000.00'],
            ['G6', 'الدرجة السادسة - مدير', 'Grade 6 - Manager', 6, '34000.00', '52000.00'],
            ['G7', 'الدرجة السابعة - مدير أول', 'Grade 7 - Director', 7, '50000.00', '80000.00'],
            ['G8', 'الدرجة الثامنة - إدارة عليا', 'Grade 8 - Executive', 8, '78000.00', '140000.00'],
        ];

        $grades = [];

        foreach ($rows as [$code, $ar, $en, $level, $min, $max]) {
            $grades[$code] = JobGrade::query()->updateOrCreate(['code' => $code], [
                'name_ar' => $ar, 'name_en' => $en, 'level' => $level, 'min_salary' => $min, 'max_salary' => $max, 'is_active' => true,
            ]);
        }

        return $grades;
    }

    /**
     * @return array<string, JobTitle>
     */
    private function jobTitles(): array
    {
        $rows = [
            'CEO' => ['الرئيس التنفيذي', 'Chief Executive Officer'],
            'CFO' => ['المدير المالي', 'Chief Financial Officer'],
            'HRD' => ['مدير الموارد البشرية', 'HR Director'],
            'HRS' => ['أخصائي موارد بشرية', 'HR Specialist'],
            'REC' => ['أخصائي استقطاب', 'Talent Acquisition Specialist'],
            'ACC' => ['محاسب', 'Accountant'],
            'PAY' => ['أخصائي رواتب', 'Payroll Specialist'],
            'ITM' => ['مدير تقنية المعلومات', 'IT Manager'],
            'SWE' => ['مهندس برمجيات', 'Software Engineer'],
            'DEVOPS' => ['مهندس عمليات تقنية', 'DevOps Engineer'],
            'SLSD' => ['مدير المبيعات', 'Sales Director'],
            'SLSM' => ['مدير مبيعات فرع', 'Branch Sales Manager'],
            'SLSR' => ['مندوب مبيعات', 'Sales Representative'],
            'OPSM' => ['مدير العمليات', 'Operations Manager'],
            'WHS' => ['مشرف مستودع', 'Warehouse Supervisor'],
            'STK' => ['أمين مخزن', 'Storekeeper'],
            'DRV' => ['سائق توصيل', 'Delivery Driver'],
        ];

        $titles = [];

        foreach ($rows as $code => [$ar, $en]) {
            $titles[$code] = JobTitle::query()->updateOrCreate(['code' => $code], ['name_ar' => $ar, 'name_en' => $en, 'is_active' => true]);
        }

        // Kept to show an inactive record in the lists.
        JobTitle::query()->updateOrCreate(['code' => 'TYP'], ['name_ar' => 'كاتب طباعة', 'name_en' => 'Typist', 'is_active' => false]);

        return $titles;
    }

    private function employmentTypes(): void
    {
        $rows = [
            ['FT', 'دوام كامل', 'Full-time', false],
            ['PT', 'دوام جزئي', 'Part-time', false],
            ['CT', 'عقد محدد المدة', 'Fixed-term contract', true],
            ['INT', 'تدريب', 'Internship', true],
            ['CON', 'استشاري مستقل', 'Consultant', true],
        ];

        foreach ($rows as [$code, $ar, $en, $fixedTerm]) {
            EmploymentType::query()->updateOrCreate(['code' => $code], [
                'name_ar' => $ar, 'name_en' => $en, 'is_fixed_term' => $fixedTerm, 'is_active' => true,
            ]);
        }
    }

    /**
     * @return array<string, CostCenter>
     */
    private function costCenters(): array
    {
        $rows = [
            ['CC-100', 'الإدارة العامة', 'Corporate', null],
            ['CC-110', 'الموارد البشرية', 'Human resources', 'CC-100'],
            ['CC-120', 'المالية', 'Finance', 'CC-100'],
            ['CC-200', 'التقنية', 'Technology', null],
            ['CC-300', 'المبيعات', 'Sales', null],
            ['CC-310', 'مبيعات القاهرة', 'Sales Cairo', 'CC-300'],
            ['CC-320', 'مبيعات الإسكندرية', 'Sales Alexandria', 'CC-300'],
            ['CC-400', 'العمليات والمستودعات', 'Operations and warehousing', null],
        ];

        $centers = [];

        foreach ($rows as [$code, $ar, $en, $parent]) {
            $centers[$code] = CostCenter::query()->updateOrCreate(['code' => $code], [
                'name_ar' => $ar, 'name_en' => $en, 'parent_id' => $parent ? $centers[$parent]->id : null, 'is_active' => true,
            ]);
        }

        return $centers;
    }

    /**
     * @return array<string, Branch>
     */
    private function branches(): array
    {
        $rows = [
            'CAI' => [
                'branch' => ['name_ar' => 'المقر الرئيسي - القاهرة', 'name_en' => 'Cairo Head Office', 'is_headquarters' => true,
                    'email' => 'cairo@horizon.test', 'phone' => '+20 2 2614 5500', 'country_code' => 'EG',
                    'address_ar' => 'التجمع الخامس، القاهرة الجديدة', 'address_en' => 'Fifth Settlement, New Cairo'],
                'locations' => [
                    ['المبنى الرئيسي', 'Main building', 'Plot 12, 90th Street, New Cairo', '30.0285310', '31.4725180', 150],
                    ['مبنى الخدمات المساندة', 'Support services building', 'Plot 14, 90th Street, New Cairo', '30.0278940', '31.4739020', 100],
                ],
            ],
            'ALX' => [
                'branch' => ['name_ar' => 'فرع الإسكندرية', 'name_en' => 'Alexandria Branch', 'is_headquarters' => false,
                    'email' => 'alex@horizon.test', 'phone' => '+20 3 4870 2200', 'country_code' => 'EG',
                    'address_ar' => 'طريق الحرية، سموحة، الإسكندرية', 'address_en' => 'El Horreya Road, Smouha, Alexandria'],
                'locations' => [
                    ['مكتب سموحة', 'Smouha office', 'El Horreya Road, Smouha', '31.2156730', '29.9425960', 150],
                    ['مستودع برج العرب', 'Borg El Arab warehouse', 'Industrial Zone 3, Borg El Arab', '30.8812450', '29.6094310', 300],
                ],
            ],
            'GIZ' => [
                'branch' => ['name_ar' => 'مركز التقنية - القرية الذكية', 'name_en' => 'Smart Village Tech Centre', 'is_headquarters' => false,
                    'email' => 'tech@horizon.test', 'phone' => '+20 2 3537 1100', 'country_code' => 'EG',
                    'address_ar' => 'القرية الذكية، طريق القاهرة الإسكندرية الصحراوي، الجيزة', 'address_en' => 'Smart Village, Cairo-Alexandria Desert Road, Giza'],
                'locations' => [
                    ['المبنى B12', 'Building B12', 'Smart Village, Building B12', '30.0712450', '31.0170830', 120],
                ],
            ],
        ];

        $branches = [];

        foreach ($rows as $code => $row) {
            $branch = Branch::query()->updateOrCreate(['code' => $code], [...$row['branch'], 'is_active' => true]);

            foreach ($row['locations'] as [$ar, $en, $address, $latitude, $longitude, $radius]) {
                $branch->locations()->updateOrCreate(['name_en' => $en], [
                    'name_ar' => $ar, 'address' => $address, 'latitude' => $latitude, 'longitude' => $longitude,
                    'radius_meters' => $radius, 'is_active' => true,
                ]);
            }

            $branches[$code] = $branch;
        }

        return $branches;
    }

    /**
     * @param  array<string, Branch>  $branches
     * @param  array<string, CostCenter>  $costCenters
     * @return array<string, Department>
     */
    private function departments(array $branches, array $costCenters): array
    {
        // code => [ar, en, parent, branch, cost centre]
        $rows = [
            'EXEC' => ['الإدارة التنفيذية', 'Executive Office', null, 'CAI', 'CC-100'],
            'HR' => ['الموارد البشرية', 'Human Resources', 'EXEC', 'CAI', 'CC-110'],
            'HR-TA' => ['استقطاب المواهب', 'Talent Acquisition', 'HR', 'CAI', 'CC-110'],
            'FIN' => ['المالية', 'Finance', 'EXEC', 'CAI', 'CC-120'],
            'FIN-PAY' => ['الرواتب', 'Payroll', 'FIN', 'CAI', 'CC-120'],
            'IT' => ['تقنية المعلومات', 'Information Technology', 'EXEC', 'GIZ', 'CC-200'],
            'SAL' => ['المبيعات', 'Sales', 'EXEC', null, 'CC-300'],
            'SAL-CAI' => ['مبيعات القاهرة', 'Sales Cairo', 'SAL', 'CAI', 'CC-310'],
            'SAL-ALX' => ['مبيعات الإسكندرية', 'Sales Alexandria', 'SAL', 'ALX', 'CC-320'],
            'OPS' => ['العمليات', 'Operations', 'EXEC', 'ALX', 'CC-400'],
            'OPS-WH' => ['المستودعات', 'Warehousing', 'OPS', 'ALX', 'CC-400'],
        ];

        $departments = [];

        foreach ($rows as $code => [$ar, $en, $parent, $branch, $costCenter]) {
            $departments[$code] = Department::query()->updateOrCreate(['code' => $code], [
                'name_ar' => $ar,
                'name_en' => $en,
                'parent_id' => $parent ? $departments[$parent]->id : null,
                'branch_id' => $branch ? $branches[$branch]->id : null,
                'cost_center_id' => $costCenters[$costCenter]->id,
                'is_active' => true,
            ]);
        }

        return $departments;
    }

    /**
     * @param  array<string, Department>  $departments
     */
    private function teams(array $departments): void
    {
        $rows = [
            ['IT-BE', 'فريق الأنظمة الخلفية', 'Backend team', 'IT'],
            ['IT-MOB', 'فريق تطبيقات الجوال', 'Mobile team', 'IT'],
            ['IT-INF', 'فريق البنية التحتية', 'Infrastructure team', 'IT'],
            ['SAL-CORP', 'مبيعات الشركات', 'Corporate accounts', 'SAL-CAI'],
            ['SAL-RET', 'مبيعات التجزئة', 'Retail accounts', 'SAL-CAI'],
            ['OPS-LOG', 'فريق التوصيل', 'Delivery team', 'OPS-WH'],
        ];

        foreach ($rows as [$code, $ar, $en, $department]) {
            Team::query()->updateOrCreate(['code' => $code], [
                'name_ar' => $ar, 'name_en' => $en, 'department_id' => $departments[$department]->id, 'is_active' => true,
            ]);
        }
    }

    /**
     * @param  array<string, Department>  $departments
     * @param  array<string, JobTitle>  $titles
     * @param  array<string, JobGrade>  $grades
     * @return array<string, Position>
     */
    private function positions(array $departments, array $titles, array $grades): array
    {
        // code => [ar, en, department, title, grade, reports to, headcount]
        $rows = [
            'CEO' => ['الرئيس التنفيذي', 'Chief Executive Officer', 'EXEC', 'CEO', 'G8', null, 1],
            'HRD' => ['مدير الموارد البشرية', 'HR Director', 'HR', 'HRD', 'G7', 'CEO', 1],
            'HRS' => ['أخصائي موارد بشرية', 'HR Specialist', 'HR', 'HRS', 'G3', 'HRD', 2],
            'TAL' => ['قائد استقطاب المواهب', 'Talent Acquisition Lead', 'HR-TA', 'REC', 'G5', 'HRD', 1],
            'REC' => ['أخصائي استقطاب', 'Recruiter', 'HR-TA', 'REC', 'G3', 'TAL', 2],
            'CFO' => ['المدير المالي', 'Chief Financial Officer', 'FIN', 'CFO', 'G8', 'CEO', 1],
            'ACC' => ['محاسب', 'Accountant', 'FIN', 'ACC', 'G3', 'CFO', 3],
            'PAYS' => ['مشرف الرواتب', 'Payroll Supervisor', 'FIN-PAY', 'PAY', 'G5', 'CFO', 1],
            'PAY' => ['أخصائي رواتب', 'Payroll Specialist', 'FIN-PAY', 'PAY', 'G3', 'PAYS', 2],
            'ITM' => ['مدير تقنية المعلومات', 'IT Manager', 'IT', 'ITM', 'G7', 'CEO', 1],
            'SWE-BE' => ['مهندس أنظمة خلفية', 'Backend Engineer', 'IT', 'SWE', 'G4', 'ITM', 4],
            'SWE-MOB' => ['مهندس تطبيقات جوال', 'Mobile Engineer', 'IT', 'SWE', 'G4', 'ITM', 2],
            'DEVOPS' => ['مهندس عمليات تقنية', 'DevOps Engineer', 'IT', 'DEVOPS', 'G4', 'ITM', 1],
            'SALD' => ['مدير المبيعات', 'Sales Director', 'SAL', 'SLSD', 'G7', 'CEO', 1],
            'SALM-CAI' => ['مدير مبيعات القاهرة', 'Sales Manager Cairo', 'SAL-CAI', 'SLSM', 'G6', 'SALD', 1],
            'SALR-CAI' => ['مندوب مبيعات القاهرة', 'Sales Representative Cairo', 'SAL-CAI', 'SLSR', 'G2', 'SALM-CAI', 6],
            'SALM-ALX' => ['مدير مبيعات الإسكندرية', 'Sales Manager Alexandria', 'SAL-ALX', 'SLSM', 'G6', 'SALD', 1],
            'SALR-ALX' => ['مندوب مبيعات الإسكندرية', 'Sales Representative Alexandria', 'SAL-ALX', 'SLSR', 'G2', 'SALM-ALX', 4],
            'OPSM' => ['مدير العمليات', 'Operations Manager', 'OPS', 'OPSM', 'G6', 'CEO', 1],
            'WHS' => ['مشرف المستودع', 'Warehouse Supervisor', 'OPS-WH', 'WHS', 'G5', 'OPSM', 1],
            'STK' => ['أمين مخزن', 'Storekeeper', 'OPS-WH', 'STK', 'G1', 'WHS', 3],
            'DRV' => ['سائق توصيل', 'Delivery Driver', 'OPS-WH', 'DRV', 'G1', 'WHS', 5],
        ];

        $positions = [];

        foreach ($rows as $code => [$ar, $en, $department, $title, $grade, $reportsTo, $headcount]) {
            $positions[$code] = Position::query()->updateOrCreate(['code' => $code], [
                'name_ar' => $ar,
                'name_en' => $en,
                'department_id' => $departments[$department]->id,
                'job_title_id' => $titles[$title]->id,
                'job_grade_id' => $grades[$grade]->id,
                'reports_to_position_id' => $reportsTo ? $positions[$reportsTo]->id : null,
                'headcount' => $headcount,
                'is_active' => true,
            ]);
        }

        return $positions;
    }
}
