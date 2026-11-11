<?php

namespace Database\Seeders;

use App\Enums\AssignmentChangeType;
use App\Enums\ContractType;
use App\Enums\CustomFieldType;
use App\Enums\DocumentCategory;
use App\Enums\EmployeeStatus;
use App\Enums\SalaryChangeType;
use App\Enums\TerminationType;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeCustomField;
use App\Models\EmployeeGroup;
use App\Models\EmploymentType;
use App\Models\JobGrade;
use App\Models\Position;
use App\Models\Team;
use App\Services\Employees\CustomFieldValues;
use App\Services\Employees\EmployeeNumberGenerator;
use App\Services\Employees\EmploymentHistory;
use App\Services\Employees\ManagerResolver;
use App\Support\SensitiveValue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demo employees for development only (never run in production): about 60
 * people across the three branches of OrganisationStructureSeeder, with
 * Arabic and English names, history rows (promotions, transfers, salary
 * changes, terminations, one scheduled transfer), contracts, bank accounts,
 * groups, custom fields and private documents — some expiring soon.
 *
 * Deterministic (fixed random seed) and skipped when employees already exist.
 */
class EmployeeSeeder extends Seeder
{
    /**
     * Development accounts linked to the first holder of these positions
     * (used by DevelopmentAccountsSeeder).
     *
     * @var array<string, string>
     */
    public const ACCOUNT_POSITIONS = [
        'hr.manager@hrmplus.test' => 'HRD',
        'branch.manager@hrmplus.test' => 'OPSM',
        'department.manager@hrmplus.test' => 'ITM',
        'direct.manager@hrmplus.test' => 'SALM-CAI',
        'employee1@hrmplus.test' => 'SALR-CAI',
        'employee2@hrmplus.test' => 'SWE-BE',
        'employee3@hrmplus.test' => 'STK',
    ];

    /** Positions left with one empty seat, to show vacancies. */
    private const VACANT = ['SALR-CAI', 'DRV', 'ACC'];

    /** @var list<array{0: string, 1: string, 2: string}> [ar, en, gender] */
    private const FIRST_NAMES = [
        ['أحمد', 'Ahmed', 'male'], ['محمد', 'Mohamed', 'male'], ['محمود', 'Mahmoud', 'male'], ['عمر', 'Omar', 'male'],
        ['يوسف', 'Youssef', 'male'], ['كريم', 'Karim', 'male'], ['مصطفى', 'Mostafa', 'male'], ['حسن', 'Hassan', 'male'],
        ['خالد', 'Khaled', 'male'], ['طارق', 'Tarek', 'male'], ['إبراهيم', 'Ibrahim', 'male'], ['عمرو', 'Amr', 'male'],
        ['شريف', 'Sherif', 'male'], ['هشام', 'Hesham', 'male'], ['وليد', 'Walid', 'male'], ['ياسر', 'Yasser', 'male'],
        ['فاطمة', 'Fatma', 'female'], ['مريم', 'Mariam', 'female'], ['نور', 'Nour', 'female'], ['سارة', 'Sara', 'female'],
        ['هبة', 'Heba', 'female'], ['ياسمين', 'Yasmine', 'female'], ['دينا', 'Dina', 'female'], ['رنا', 'Rana', 'female'],
        ['منى', 'Mona', 'female'], ['آية', 'Aya', 'female'], ['سلمى', 'Salma', 'female'], ['ريم', 'Reem', 'female'],
    ];

    /** @var list<array{0: string, 1: string}> */
    private const FAMILY_NAMES = [
        ['عبد الرحمن', 'Abdelrahman'], ['السيد', 'El-Sayed'], ['حسين', 'Hussein'], ['عبد الله', 'Abdallah'], ['فهمي', 'Fahmy'],
        ['منصور', 'Mansour'], ['الشافعي', 'El-Shafei'], ['النجار', 'El-Naggar'], ['سليمان', 'Soliman'], ['رمضان', 'Ramadan'],
        ['عثمان', 'Osman'], ['البدري', 'El-Badry'], ['زكي', 'Zaki'], ['جمال', 'Gamal'], ['شوقي', 'Shawky'], ['الجندي', 'El-Gendy'],
        ['عادل', 'Adel'], ['نصار', 'Nassar'], ['حمدي', 'Hamdy'], ['الحسيني', 'El-Husseiny'],
    ];

    private int $nameIndex = 0;

    public function __construct(
        private readonly EmployeeNumberGenerator $numbers,
        private readonly EmploymentHistory $history,
        private readonly ManagerResolver $managers,
        private readonly CustomFieldValues $customFields,
    ) {}

    public function run(): void
    {
        if (Employee::query()->withoutGlobalScopes()->exists() || ! Position::query()->where('code', 'CEO')->exists()) {
            return;
        }

        mt_srand(20261111);

        $groups = $this->groups();
        $this->customFieldDefinitions();
        $fullTime = EmploymentType::query()->where('code', 'FT')->firstOrFail();
        $fixedTerm = EmploymentType::query()->where('code', 'CT')->firstOrFail();
        $internship = EmploymentType::query()->where('code', 'INT')->firstOrFail();

        // Position holders, top of the hierarchy first so managers exist before their reports.
        foreach (Position::query()->with('department')->orderBy('id')->get() as $position) {
            $seats = $position->headcount - (in_array($position->code, self::VACANT, true) ? 1 : 0);

            for ($seat = 0; $seat < $seats; $seat++) {
                $type = in_array($position->code, ['DRV', 'SALR-ALX'], true) && $seat % 2 === 1 ? $fixedTerm : $fullTime;
                $this->hire($position->department, $position, $type, $groups, $position->jobGrade?->level ?? 2);
            }
        }

        // Staff without a position seat: interns and assistants.
        foreach (['IT' => 4, 'HR' => 2, 'SAL-CAI' => 3, 'SAL-ALX' => 2, 'OPS-WH' => 3, 'FIN' => 1] as $code => $count) {
            $department = Department::query()->where('code', $code)->firstOrFail();

            for ($i = 0; $i < $count; $i++) {
                $this->hire($department, null, $i === 0 && $code === 'IT' ? $internship : ($i % 2 ? $fixedTerm : $fullTime), $groups, 1);
            }
        }

        $this->formerEmployees($fullTime);
        $this->scheduledTransfer();
    }

    /**
     * @param  array<string, EmployeeGroup>  $groups
     */
    private function hire(Department $department, ?Position $position, EmploymentType $type, array $groups, int $level): Employee
    {
        [$first, $family] = $this->nextName();
        $hireDate = Carbon::today()->subDays(mt_rand(120, 365 * 9));
        $branchId = $department->branch_id ?? Branch::query()->where('code', 'CAI')->value('id');
        $team = Team::query()->where('department_id', $department->id)->inRandomOrder(mt_rand())->first();
        $manager = $this->managers->defaultManagerFor($position, $department);

        $employee = new Employee([
            'name_ar' => $first[0].' '.$family[0],
            'name_en' => $first[1].' '.$family[1],
            'date_of_birth' => Carbon::today()->subYears(mt_rand(22, 58))->subDays(mt_rand(0, 360)),
            'gender' => $first[2],
            'marital_status' => ['single', 'married', 'married', 'divorced'][mt_rand(0, 3)],
            'nationality' => mt_rand(1, 12) === 1 ? 'SA' : 'EG',
            'national_id' => (string) mt_rand(2, 3).str_pad((string) mt_rand(0, 9_999_999), 7, '0', STR_PAD_LEFT).str_pad((string) mt_rand(0, 999_999), 6, '0', STR_PAD_LEFT),
            'passport_number' => mt_rand(1, 3) === 1 ? 'A'.str_pad((string) mt_rand(0, 99_999_999), 8, '0', STR_PAD_LEFT) : null,
            'email' => strtolower(str_replace(['-', ' '], '', $first[1]).'.'.str_replace(['-', ' '], '', $family[1])).'.'.($this->nameIndex).'@horizon.test',
            'phone' => '+2010'.str_pad((string) mt_rand(0, 99_999_999), 8, '0', STR_PAD_LEFT),
            'address' => ['شارع التسعين، التجمع الخامس', 'شارع فؤاد، محطة الرمل', 'شارع الهرم، الجيزة', 'مدينة نصر', 'المعادي'][mt_rand(0, 4)],
            'city' => $department->branch?->code === 'ALX' ? 'Alexandria' : ($department->branch?->code === 'GIZ' ? 'Giza' : 'Cairo'),
            'country_code' => 'EG',
        ]);
        $employee->passport_expiry_date = $employee->passport_number ? Carbon::today()->addDays(mt_rand(10, 1500)) : null;
        $employee->forceFill([
            'employee_number' => $this->numbers->next(Branch::query()->find($branchId), $hireDate),
            'branch_id' => $branchId,
            'department_id' => $department->id,
            'team_id' => $position === null || mt_rand(0, 1) ? $team?->id : null,
            'position_id' => $position?->id,
            'job_title_id' => $position?->job_title_id,
            'job_grade_id' => $position?->job_grade_id ?? JobGrade::query()->where('level', $level)->value('id'),
            'employment_type_id' => $type->id,
            'cost_center_id' => $department->cost_center_id,
            'manager_id' => $manager?->id,
            'work_location_id' => $department->branch?->locations()->value('id'),
            'status' => EmployeeStatus::Active,
            'hire_date' => $hireDate,
            'original_hire_date' => $hireDate,
            'probation_end_date' => $hireDate->copy()->addMonths(3),
            'national_id_hash' => SensitiveValue::hash($employee->national_id),
            'passport_number_hash' => SensitiveValue::hash($employee->passport_number),
        ])->save();

        $this->history($employee, $level);
        $this->contract($employee, $type);
        $this->extras($employee, $groups, $position);

        return $employee;
    }

    /**
     * Hire row (with an earlier grade or department for some), a later
     * promotion / transfer, and the salary history.
     */
    private function history(Employee $employee, int $level): void
    {
        $current = $this->history->currentPlacement($employee);
        $story = mt_rand(1, 10);
        $hire = $current;
        $changeDate = null;

        if ($story <= 3 && $level > 1) {
            // Joined one grade lower and was promoted later.
            $hire['job_grade_id'] = JobGrade::query()->where('level', $level - 1)->value('id') ?? $current['job_grade_id'];
            $changeDate = $employee->hire_date->copy()->addDays(mt_rand(200, max(201, (int) $employee->hire_date->diffInDays(today()) - 30)));
        } elseif ($story === 4) {
            // Started in another department of the same branch and was transferred.
            $other = Department::query()->where('branch_id', $employee->branch_id)->whereKeyNot($employee->department_id)->first();

            if ($other) {
                $hire = [...$hire, 'department_id' => $other->id, 'team_id' => null, 'position_id' => null, 'cost_center_id' => $other->cost_center_id];
                $changeDate = $employee->hire_date->copy()->addDays(mt_rand(150, max(151, (int) $employee->hire_date->diffInDays(today()) - 30)));
            }
        }

        $employee->assignments()->create([...$hire, 'change_type' => AssignmentChangeType::Hire, 'effective_date' => $employee->hire_date, 'applied_at' => now()]);

        if ($changeDate !== null && $changeDate->lt(today())) {
            $employee->assignments()->create([
                ...$current,
                'change_type' => $story <= 3 ? AssignmentChangeType::Promotion : AssignmentChangeType::Transfer,
                'effective_date' => $changeDate,
                'reason' => $story <= 3 ? 'ترقية بناءً على تقييم الأداء السنوي' : 'نقل لاحتياجات العمل',
                'applied_at' => now(),
            ]);
        }

        $band = JobGrade::query()->find($employee->job_grade_id);
        $min = (int) ($band?->min_salary ?? 6000);
        $max = (int) ($band?->max_salary ?? 9000);
        $start = (string) (intdiv(mt_rand($min, (int) (($min + $max) / 2)), 50) * 50);
        $this->history->recordSalary($employee, $start.'.00', SalaryChangeType::Initial, $employee->hire_date, applyNow: true);

        $raiseOn = $changeDate ?? $employee->hire_date->copy()->addYear();

        if ($raiseOn->lt(today())) {
            $raised = bcmul($start, (string) (1 + mt_rand(5, 15) / 100), 0);
            $this->history->recordSalary($employee, bcadd($raised, '0', 2), $changeDate ? SalaryChangeType::Promotion : SalaryChangeType::Increase, $raiseOn, 'المراجعة السنوية للرواتب');
        }
    }

    private function contract(Employee $employee, EmploymentType $type): void
    {
        if (! $type->is_fixed_term) {
            $employee->contracts()->create(['contract_type' => ContractType::Permanent, 'start_date' => $employee->hire_date, 'reference' => 'C-'.$employee->employee_number]);

            return;
        }

        $start = $employee->hire_date->copy();
        $previous = null;

        // One-year contracts renewed up to today; the last one ends within the coming months.
        while ($start->lte(today())) {
            $end = $start->copy()->addYear()->subDay();
            $previous = $employee->contracts()->create([
                'contract_type' => $type->code === 'INT' ? ContractType::Internship : ContractType::FixedTerm,
                'start_date' => $start, 'end_date' => $end, 'renewal_of_id' => $previous?->id,
                'reference' => 'C-'.$employee->employee_number.'-'.$start->format('Y'),
            ]);
            $start = $end->copy()->addDay();
        }
    }

    /**
     * @param  array<string, EmployeeGroup>  $groups
     */
    private function extras(Employee $employee, array $groups, ?Position $position): void
    {
        $employee->emergencyContacts()->create([
            'name' => self::FIRST_NAMES[mt_rand(0, 27)][0].' '.explode(' ', $employee->name_ar, 2)[1],
            'relationship' => ['زوج / زوجة', 'أب', 'أم', 'أخ', 'أخت'][mt_rand(0, 4)],
            'phone' => '+2011'.str_pad((string) mt_rand(0, 99_999_999), 8, '0', STR_PAD_LEFT),
        ]);

        $employee->bankAccounts()->create([
            'bank_name' => ['البنك الأهلي المصري', 'بنك مصر', 'البنك التجاري الدولي', 'بنك القاهرة'][mt_rand(0, 3)],
            'account_holder' => $employee->name_en,
            'iban' => 'EG'.str_pad((string) mt_rand(10, 99), 2, '0').str_pad((string) mt_rand(0, 9999), 4, '0', STR_PAD_LEFT).'0001'
                .str_pad((string) mt_rand(0, 999_999_999), 9, '0', STR_PAD_LEFT).str_pad((string) mt_rand(0, 99_999_999), 8, '0', STR_PAD_LEFT),
            'is_primary' => true,
        ]);

        $code = $position?->code ?? '';

        if (in_array($code, ['DRV', 'SALR-CAI', 'SALR-ALX'], true)) {
            $employee->groups()->attach($groups['FIELD']->id, ['created_at' => now()]);
        }

        if (in_array($code, ['STK', 'DRV', 'WHS'], true)) {
            $employee->groups()->attach($groups['WH-NIGHT']->id, ['created_at' => now()]);
        }

        if (in_array($code, ['CEO', 'CFO', 'HRD', 'ITM', 'SALD', 'OPSM'], true)) {
            $employee->groups()->attach($groups['MGMT']->id, ['created_at' => now()]);
        }

        $this->customFields->save($employee, [
            'blood_type' => ['A+', 'B+', 'O+', 'AB+', 'O-'][mt_rand(0, 4)],
            'social_insurance_no' => (string) mt_rand(10_000_000, 99_999_999),
            'driving_license' => in_array($code, ['DRV', 'SALR-CAI', 'SALR-ALX', 'OPSM'], true) ? '1' : '0',
        ]);

        $this->documents($employee);
    }

    private function documents(Employee $employee): void
    {
        if (mt_rand(1, 10) > 6) {
            return;
        }

        $disk = Storage::disk(config('employees.disk'));
        $files = [
            [DocumentCategory::NationalId, 'صورة بطاقة الرقم القومي', 'national-id.pdf', Carbon::today()->addDays(mt_rand(-20, 2500)), true],
            [DocumentCategory::Cv, 'السيرة الذاتية', 'cv.pdf', null, false],
        ];

        if ($employee->passport_number) {
            $files[] = [DocumentCategory::Passport, 'صورة جواز السفر', 'passport.pdf', $employee->passport_expiry_date, true];
        }

        if (mt_rand(1, 3) === 1) {
            $files[] = [DocumentCategory::Medical, 'شهادة اللياقة الطبية', 'medical.pdf', Carbon::today()->addDays(mt_rand(3, 28)), false];
        }

        foreach ($files as [$category, $title, $name, $expiry, $shared]) {
            $path = $employee->id.'/documents/'.Str::uuid().'.pdf';
            $content = $this->pdf($title.' - '.$employee->name_en);
            $disk->put($path, $content);

            $employee->documents()->create([
                'category' => $category, 'title' => $title, 'issue_date' => $employee->hire_date, 'expiry_date' => $expiry,
                'visible_to_employee' => $shared, 'file_path' => $path, 'file_name' => $name, 'file_mime' => 'application/pdf',
                'file_size' => strlen($content),
            ]);
        }
    }

    private function formerEmployees(EmploymentType $type): void
    {
        foreach ([['SAL-ALX', TerminationType::Resignation, true], ['OPS-WH', TerminationType::ContractEnd, true], ['IT', TerminationType::Dismissal, false]] as [$code, $termination, $eligible]) {
            $employee = $this->hire(Department::query()->where('code', $code)->firstOrFail(), null, $type, [], 2);
            $date = Carbon::today()->subDays(mt_rand(20, 200));

            if ($date->lte($employee->hire_date)) {
                $date = $employee->hire_date->copy()->addDays(60);
            }

            $this->history->recordAssignment($employee, AssignmentChangeType::Termination, ['status' => EmployeeStatus::Terminated->value], $date,
                $termination === TerminationType::Dismissal ? 'مخالفات متكررة لسياسة الحضور' : 'إنهاء الخدمة وفق طلب الموظف',
                ['termination_type' => $termination->value, 'eligible_for_rehire' => $eligible]);
            $employee->contracts()->whereNull('terminated_on')->update(['terminated_on' => $date->toDateString()]);
        }
    }

    /**
     * A transfer scheduled for next month, to show pending changes.
     */
    private function scheduledTransfer(): void
    {
        $employee = Employee::query()->whereNull('position_id')->where('status', EmployeeStatus::Active)
            ->whereHas('department', fn ($query) => $query->where('code', 'SAL-CAI'))->first();
        $target = Department::query()->where('code', 'SAL-ALX')->first();

        if ($employee && $target) {
            $this->history->recordAssignment($employee, AssignmentChangeType::Transfer, [
                'branch_id' => $target->branch_id, 'department_id' => $target->id, 'team_id' => null,
                'cost_center_id' => $target->cost_center_id, 'work_location_id' => null,
            ], Carbon::today()->addMonth()->startOfMonth(), 'دعم فريق مبيعات الإسكندرية');
        }
    }

    /**
     * @return array<string, EmployeeGroup>
     */
    private function groups(): array
    {
        $groups = [];

        foreach ([['FIELD', 'الفرق الميدانية', 'Field staff'], ['WH-NIGHT', 'الوردية الليلية للمستودع', 'Warehouse night shift'], ['MGMT', 'الإدارة العليا', 'Senior management']] as [$code, $ar, $en]) {
            $groups[$code] = EmployeeGroup::query()->updateOrCreate(['code' => $code], ['name_ar' => $ar, 'name_en' => $en, 'is_active' => true]);
        }

        return $groups;
    }

    private function customFieldDefinitions(): void
    {
        $bloodType = EmployeeCustomField::query()->updateOrCreate(['code' => 'blood_type'], [
            'name_ar' => 'فصيلة الدم', 'name_en' => 'Blood type', 'type' => CustomFieldType::Select, 'visible_to_employee' => true, 'sort_order' => 1, 'is_active' => true,
        ]);

        foreach (['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'] as $index => $value) {
            $bloodType->options()->updateOrCreate(['value' => $value], ['label_ar' => $value, 'label_en' => $value, 'sort_order' => $index]);
        }

        EmployeeCustomField::query()->updateOrCreate(['code' => 'social_insurance_no'], [
            'name_ar' => 'رقم التأمين الاجتماعي', 'name_en' => 'Social insurance number', 'type' => CustomFieldType::Text, 'sort_order' => 2, 'is_active' => true,
        ]);

        EmployeeCustomField::query()->updateOrCreate(['code' => 'driving_license'], [
            'name_ar' => 'يحمل رخصة قيادة', 'name_en' => 'Holds a driving licence', 'type' => CustomFieldType::Boolean, 'visible_to_employee' => true, 'sort_order' => 3, 'is_active' => true,
        ]);
    }

    /**
     * @return array{0: array{0: string, 1: string, 2: string}, 1: array{0: string, 1: string}}
     */
    private function nextName(): array
    {
        $this->nameIndex++;

        return [self::FIRST_NAMES[($this->nameIndex * 11) % count(self::FIRST_NAMES)], self::FAMILY_NAMES[($this->nameIndex * 7) % count(self::FAMILY_NAMES)]];
    }

    /**
     * A tiny valid one-page PDF with a line of (ASCII) text.
     */
    private function pdf(string $text): string
    {
        $text = preg_replace('/[^\x20-\x7E]/', '', $text) ?: 'Document';
        $stream = 'BT /F1 14 Tf 60 760 Td ('.addcslashes($text, '()\\').') Tj ET';

        return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n"
            .'4 0 obj<</Length '.strlen($stream).">>stream\n".$stream."\nendstream endobj\n"
            ."5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    }
}
