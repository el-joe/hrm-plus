<?php

use App\Enums\DataScopeType;
use App\Models\Branch;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\JobOffer;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\Employees\SensitiveDataAccessLog;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('recruitment_files');
    seedRecruitment();
    $this->cairo = Branch::factory()->create();
    $this->alex = Branch::factory()->create();
    $this->cairoVacancy = Vacancy::factory()->create(['branch_id' => $this->cairo->id, 'department_id' => Department::factory()->create(['branch_id' => $this->cairo->id])->id, 'title_en' => 'Cairo Analyst', 'title_ar' => 'Cairo Analyst']);
    $this->alexVacancy = Vacancy::factory()->create(['branch_id' => $this->alex->id, 'department_id' => Department::factory()->create(['branch_id' => $this->alex->id])->id, 'title_en' => 'Alex Storekeeper', 'title_ar' => 'Alex Storekeeper']);
    $this->cairoCandidate = Candidate::factory()->create(['name_en' => 'Cairo Person', 'name_ar' => 'Cairo Person', 'expected_salary' => '12345.67', 'current_salary' => '9999.00']);
    $this->alexCandidate = Candidate::factory()->create(['name_en' => 'Alex Person', 'name_ar' => 'Alex Person']);
    $this->cairoApplication = JobApplication::factory()->create(['candidate_id' => $this->cairoCandidate->id, 'vacancy_id' => $this->cairoVacancy->id]);
    $this->alexApplication = JobApplication::factory()->create(['candidate_id' => $this->alexCandidate->id, 'vacancy_id' => $this->alexVacancy->id]);
});

function scopedRecruiter(Branch $branch): User
{
    $recruiter = adminWithRoles('Recruiter');
    $recruiter->dataScopes()->create(['scope_type' => DataScopeType::Branch, 'scope_id' => $branch->id]);

    return $recruiter->fresh();
}

describe('salary fields', function () {
    it('hides expected and current salary from admins without employee_financials.view and ignores them on save', function () {
        $recruiter = adminWithRoles('Recruiter');

        $this->actingAs($recruiter, 'admin')->get("/admin/candidates/{$this->cairoCandidate->id}")
            ->assertOk()->assertDontSee('12,345.67')->assertDontSee('9,999.00')->assertSee(__('recruitment.common.restricted'));

        $this->actingAs($recruiter, 'admin')->put("/admin/candidates/{$this->cairoCandidate->id}", [
            'name_en' => 'Cairo Person', 'email' => $this->cairoCandidate->email, 'source' => 'linkedin', 'expected_salary' => '1.00',
        ])->assertSessionHasNoErrors();

        expect($this->cairoCandidate->fresh()->expected_salary)->toBe('12345.67');
        $this->assertDatabaseMissing('audit_logs', ['event' => SensitiveDataAccessLog::RECRUITMENT_SALARY]);
    });

    it('shows salaries to admins with employee_financials.view and logs every view', function () {
        $hr = adminWithRoles('HR Manager');

        $this->actingAs($hr, 'admin')->get("/admin/candidates/{$this->cairoCandidate->id}")->assertOk()->assertSee('12,345.67');

        $this->assertDatabaseHas('audit_logs', [
            'event' => SensitiveDataAccessLog::RECRUITMENT_SALARY, 'user_id' => $hr->id,
            'auditable_type' => 'candidate', 'auditable_id' => $this->cairoCandidate->id,
        ]);
    });

    it('keeps the offered salary out of reach of recruiters, who cannot submit an offer without it', function () {
        $recruiter = adminWithRoles('Recruiter');

        $this->actingAs($recruiter, 'admin')->post("/admin/applications/{$this->cairoApplication->id}/offers", [
            'basic_salary' => '50000', 'start_date' => today()->addWeek()->toDateString(), 'expires_on' => today()->addDays(2)->toDateString(),
        ])->assertSessionHasNoErrors();
        $offer = JobOffer::query()->sole();
        expect($offer->basic_salary)->toBeNull();

        $this->actingAs($recruiter, 'admin')->post("/admin/offers/{$offer->id}/submit")->assertSessionHasErrors('basic_salary');

        $offer->forceFill(['basic_salary' => '50000.00'])->save();
        $this->actingAs($recruiter, 'admin')->get("/admin/offers/{$offer->id}")->assertOk()->assertDontSee('50,000.00');
        $this->actingAs($recruiter, 'admin')->get("/admin/offers/{$offer->id}/letter")->assertOk()->assertDontSee('50,000.00');
    });
});

describe('data scope and permissions', function () {
    it('limits a branch-scoped recruiter to the vacancies and candidates of that branch', function () {
        $recruiter = scopedRecruiter($this->cairo);

        $this->actingAs($recruiter, 'admin')->get('/admin/vacancies')->assertOk()->assertSee('Cairo Analyst')->assertDontSee('Alex Storekeeper');
        $this->actingAs($recruiter, 'admin')->get('/admin/candidates')->assertOk()->assertSee('Cairo Person')->assertDontSee('Alex Person');
        $this->actingAs($recruiter, 'admin')->get("/admin/vacancies/{$this->alexVacancy->id}")->assertNotFound();
        $this->actingAs($recruiter, 'admin')->get("/admin/candidates/{$this->alexCandidate->id}")->assertNotFound();
        $this->actingAs($recruiter, 'admin')->patch("/admin/applications/{$this->alexApplication->id}/stage", ['stage_id' => $this->alexApplication->stage_id])->assertNotFound();
        $this->actingAs($recruiter, 'admin')->get("/admin/vacancies/{$this->cairoVacancy->id}")->assertOk()->assertSee('Cairo Person');
    });

    it('makes a scoped recruiter attach new candidates to a vacancy in scope', function () {
        $recruiter = scopedRecruiter($this->cairo);
        $payload = ['name_en' => 'New Person', 'email' => 'new@example.test', 'source' => 'referral'];

        $this->actingAs($recruiter, 'admin')->post('/admin/candidates', $payload)->assertSessionHasErrors('vacancy_id');
        $this->actingAs($recruiter, 'admin')->post('/admin/candidates', [...$payload, 'vacancy_id' => $this->alexVacancy->id])->assertSessionHasErrors('vacancy_id');
        $this->actingAs($recruiter, 'admin')->post('/admin/candidates', [...$payload, 'vacancy_id' => $this->cairoVacancy->id])->assertSessionHasNoErrors();
    });

    it('refuses recruitment screens to admins without recruitment permissions', function () {
        $officer = adminWithRoles('Attendance Officer');

        foreach (['/admin/vacancies', '/admin/candidates', '/admin/offers', '/admin/interviews', '/admin/recruitment-stages', '/admin/evaluation-forms', '/admin/onboarding', '/admin/offboarding'] as $url) {
            $this->actingAs($officer, 'admin')->get($url)->assertForbidden();
        }

        $this->actingAs($officer, 'admin')->get("/admin/candidates/{$this->cairoCandidate->id}/cv")->assertForbidden();
    });

    it('lets only recruitment.manage edit the pipeline', function () {
        $viewer = adminWithPermissions('recruitment.view', 'recruitment.update');

        $this->actingAs($viewer, 'admin')->post('/admin/recruitment-stages', ['code' => 'x', 'name_ar' => 'س', 'name_en' => 'X', 'category' => 'open'])->assertForbidden();
        $this->actingAs(adminWithRoles('Recruiter'), 'admin')->post('/admin/recruitment-stages', ['code' => 'assessment', 'name_ar' => 'تقييم', 'name_en' => 'Assessment', 'category' => 'interview', 'is_active' => '1'])
            ->assertSessionHasNoErrors();
    });
});

describe('interviewer visibility', function () {
    beforeEach(function () {
        $this->mine = $this->cairoApplication->interviews()->create(['type' => 'hr', 'scheduled_at' => now()->addDay(), 'timezone' => 'UTC', 'mode' => 'phone', 'status' => 'scheduled']);
        $this->other = $this->alexApplication->interviews()->create(['type' => 'hr', 'scheduled_at' => now()->addDay(), 'timezone' => 'UTC', 'mode' => 'phone', 'status' => 'scheduled']);
    });

    it('shows an interviewer with interviews.view only the interviews they sit on, whatever their data scope', function () {
        $manager = adminWithRoles('Department Manager');
        $manager->dataScopes()->create(['scope_type' => DataScopeType::Branch, 'scope_id' => $this->cairo->id]);
        $this->other->interviewers()->attach($manager);

        $this->actingAs($manager, 'admin')->get('/admin/interviews')->assertOk()->assertSee('Alex Person')->assertDontSee('Cairo Person');
        $this->actingAs($manager, 'admin')->get("/admin/interviews/{$this->other->id}")->assertOk();
        $this->actingAs($manager, 'admin')->get("/admin/interviews/{$this->mine->id}")->assertForbidden();
        $this->actingAs($manager, 'admin')->get("/admin/applications/{$this->alexApplication->id}")->assertNotFound();
    });

    it('lets interviews.manage see every interview in scope', function () {
        $manager = adminWithPermissions('interviews.view', 'interviews.manage');

        $this->actingAs($manager, 'admin')->get('/admin/interviews')->assertOk()->assertSee('Alex Person')->assertSee('Cairo Person');
        $this->actingAs($manager, 'admin')->get("/admin/interviews/{$this->mine->id}")->assertOk();
    });

    it('lets an employee interviewer score through the portal, and nobody else', function () {
        $employeeUser = User::factory()->employee()->linkedToEmployee(Employee::factory()->create()->id)->create();
        $outsider = User::factory()->employee()->linkedToEmployee(Employee::factory()->create()->id)->create();
        $this->mine->interviewers()->attach($employeeUser);

        $this->actingAs($employeeUser, 'employee')->get('/portal/interviews')->assertOk()->assertSee('Cairo Person')->assertDontSee('Alex Person');
        $this->actingAs($employeeUser, 'employee')->get("/portal/interviews/{$this->other->id}")->assertNotFound(); // portal ownership: 404, never 403 (Phase 9)
        $this->actingAs($employeeUser, 'employee')->post("/portal/interviews/{$this->mine->id}/scorecard", ['recommendation' => 'yes', 'comments' => 'Good'])
            ->assertSessionHasNoErrors();
        $this->actingAs($outsider, 'employee')->post("/portal/interviews/{$this->mine->id}/scorecard", ['recommendation' => 'yes'])->assertNotFound();

        expect($this->mine->scorecards()->sole()->user_id)->toBe($employeeUser->id);
    });
});
