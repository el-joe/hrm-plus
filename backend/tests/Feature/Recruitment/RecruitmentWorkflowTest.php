<?php

use App\Enums\ApplicationStatus;
use App\Enums\CandidateSource;
use App\Enums\DocumentCategory;
use App\Enums\OfferStatus;
use App\Enums\OnboardingStatus;
use App\Enums\VacancyStatus;
use App\Models\Candidate;
use App\Models\Employee;
use App\Models\EvaluationForm;
use App\Models\Interview;
use App\Models\JobApplication;
use App\Models\JobOffer;
use App\Models\RecruitmentStage;
use App\Models\User;
use App\Models\Vacancy;
use App\Notifications\Recruitment\ApplicationReceived;
use App\Notifications\Recruitment\ApplicationStageChanged;
use App\Notifications\Recruitment\InterviewAssigned;
use App\Notifications\Recruitment\InterviewInvitation;
use App\Notifications\Recruitment\OfferSent;
use App\Services\Recruitment\Pipeline;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('recruitment_files');
    Storage::fake('employee_files');
    seedRecruitment();
    $this->org = hrStructure();
});

/**
 * @return array<string, mixed>
 */
function vacancyPayload(array $org, array $overrides = []): array
{
    return [
        'title_ar' => 'مطور', 'title_en' => 'Developer',
        'branch_id' => $org['branch']->id, 'department_id' => $org['department']->id, 'position_id' => $org['position']->id,
        'employment_type_id' => $org['type']->id, 'openings' => 1, 'status' => 'open', 'is_public' => '1',
        ...$overrides,
    ];
}

function stageOf(Vacancy $vacancy, string $code): RecruitmentStage
{
    return app(Pipeline::class)->stagesFor($vacancy)->firstWhere('code', $code);
}

it('creates a vacancy with a reference and keeps its openings within the position\'s free seats', function () {
    Employee::factory()->inPosition($this->org['position'])->create(['employment_type_id' => $this->org['type']->id]);
    $hr = superAdmin();

    $this->actingAs($hr, 'admin')->post('/admin/vacancies', vacancyPayload($this->org, ['openings' => 2]))
        ->assertSessionHasErrors('openings');

    $this->actingAs($hr, 'admin')->post('/admin/vacancies', vacancyPayload($this->org))->assertSessionHasNoErrors();
    $vacancy = Vacancy::query()->sole();

    expect($vacancy->reference)->toBe('VAC-00001')
        ->and($vacancy->job_title_id)->toBe($this->org['position']->job_title_id)
        ->and($vacancy->is_public)->toBeTrue();

    // The single free seat is now reserved by the open vacancy.
    $this->actingAs($hr, 'admin')->post('/admin/vacancies', vacancyPayload($this->org, ['title_en' => 'Second']))
        ->assertSessionHasErrors('openings');
});

it('runs the whole hiring workflow from career page application to employee with preserved lineage', function () {
    Notification::fake();
    $vacancy = Vacancy::factory()->create([
        'branch_id' => $this->org['branch']->id, 'department_id' => $this->org['department']->id,
        'position_id' => $this->org['position']->id, 'employment_type_id' => $this->org['type']->id,
        'evaluation_form_id' => EvaluationForm::query()->value('id'),
    ]);
    $hr = adminWithRoles('HR Manager');
    $approver = adminWithRoles('HR Manager');

    // 1. Apply on the career page.
    $this->post("/careers/{$vacancy->reference}/apply", [
        'name' => 'Mona Fathy', 'email' => 'Mona@Example.test', 'phone' => '+20 100 555 0101', 'cv' => fakeCv(), 'consent' => '1',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $application = JobApplication::query()->sole();
    $candidate = $application->candidate;
    expect($candidate->email)->toBe('mona@example.test')
        ->and($candidate->source)->toBe(CandidateSource::CareerPage)
        ->and($application->stage->code)->toBe('new')
        ->and($application->stageChanges()->count())->toBe(1);
    Storage::disk('recruitment_files')->assertExists($candidate->cv_path);
    Notification::assertSentOnDemand(ApplicationReceived::class, fn ($notification, $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'mona@example.test');

    // 2. Move through the stages; history is kept.
    $this->actingAs($hr, 'admin')->patchJson("/admin/applications/{$application->id}/stage", ['stage_id' => stageOf($vacancy, 'shortlisted')->id])
        ->assertOk()->assertJsonPath('application.stage_id', stageOf($vacancy, 'shortlisted')->id);

    // 3. Interview with two interviewers; the application moves to the interview stage.
    $this->actingAs($hr, 'admin')->post("/admin/applications/{$application->id}/interviews", [
        'type' => 'technical', 'date' => today()->addDay()->toDateString(), 'time' => '10:00', 'timezone' => 'Africa/Cairo',
        'duration_minutes' => 60, 'mode' => 'online', 'meeting_url' => 'https://meet.example.test/x', 'interviewer_ids' => [$hr->id, $approver->id],
    ])->assertSessionHasNoErrors();
    $interview = Interview::query()->sole();
    expect($interview->scheduled_at->format('H:i'))->toBe('08:00')
        ->and($application->fresh()->stage->code)->toBe('screening');
    Notification::assertSentTo([$hr, $approver], InterviewAssigned::class);
    Notification::assertSentOnDemand(InterviewInvitation::class);

    // 4. Scorecards (weighted) and the aggregated score.
    $criteria = $interview->evaluationForm->criteria; // weights 3, 2, 2, 1
    $this->actingAs($hr, 'admin')->post("/admin/interviews/{$interview->id}/scorecard", [
        'recommendation' => 'strong_yes', 'scores' => $criteria->mapWithKeys(fn ($criterion) => [$criterion->id => 5])->all(),
    ])->assertSessionHasNoErrors();
    $this->actingAs($approver, 'admin')->post("/admin/interviews/{$interview->id}/scorecard", [
        'recommendation' => 'yes', 'scores' => array_combine($criteria->pluck('id')->all(), [4, 3, 3, 2]),
    ])->assertSessionHasNoErrors();
    // (4×3 + 3×2 + 3×2 + 2×1) / 8 = 3.25; mean of 5.00 and 3.25 = 4.13 (half up).
    expect($interview->fresh()->average_score)->toBe('4.13')
        ->and($application->fresh()->average_score)->toBe('4.13');

    $this->actingAs($hr, 'admin')->post("/admin/interviews/{$interview->id}/decision", ['decision' => 'advance'])->assertSessionHasNoErrors();

    // 5. Offer → approval (engine, workflow job_offer) → send → accepted.
    $this->actingAs($hr, 'admin')->post("/admin/applications/{$application->id}/offers", [
        'basic_salary' => '15000.50', 'start_date' => today()->addWeek()->toDateString(), 'expires_on' => today()->addDays(3)->toDateString(),
        'job_title_id' => $this->org['position']->job_title_id, 'employment_type_id' => $this->org['type']->id,
    ])->assertSessionHasNoErrors();
    $offer = JobOffer::query()->sole();
    $this->actingAs($hr, 'admin')->post("/admin/offers/{$offer->id}/submit")->assertSessionHasNoErrors();
    expect($offer->fresh()->status)->toBe(OfferStatus::PendingApproval);

    $this->actingAs($hr, 'admin')->post("/admin/offers/{$offer->id}/approval/approve")->assertSessionHasErrors('comment'); // own request
    $this->actingAs($approver, 'admin')->post("/admin/offers/{$offer->id}/approval/approve")->assertSessionHasNoErrors();
    expect($offer->fresh()->status)->toBe(OfferStatus::Approved);

    $this->actingAs($hr, 'admin')->post("/admin/offers/{$offer->id}/send")->assertSessionHasNoErrors();
    expect($application->fresh()->stage->code)->toBe('offer_sent');
    Notification::assertSentOnDemand(OfferSent::class);

    $this->actingAs($hr, 'admin')->post("/admin/offers/{$offer->id}/accept")->assertSessionHasNoErrors();
    expect($offer->fresh()->status)->toBe(OfferStatus::Accepted);

    // 6. Convert to employee, with a login account.
    $this->actingAs($hr, 'admin')->get("/admin/applications/{$application->id}/hire")->assertOk()->assertSee('15,000.50');
    $this->actingAs($hr, 'admin')->post("/admin/applications/{$application->id}/hire", [
        'name_ar' => 'منى فتحي', 'name_en' => 'Mona Fathy', 'email' => 'mona.fathy@company.test', 'hire_date' => today()->addWeek()->toDateString(),
        'branch_id' => $this->org['branch']->id, 'department_id' => $this->org['department']->id, 'position_id' => $this->org['position']->id,
        'employment_type_id' => $this->org['type']->id,
        'create_account' => '1', 'account_email' => 'mona.fathy@company.test',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $employee = Employee::query()->where('candidate_id', $candidate->id)->sole();
    expect($employee->job_application_id)->toBe($application->id)
        ->and($employee->basic_salary)->toBe('15000.50')
        ->and($employee->position_id)->toBe($this->org['position']->id)
        ->and($candidate->fresh()->employee_id)->toBe($employee->id)
        ->and($application->fresh()->status)->toBe(ApplicationStatus::Hired)
        ->and($application->fresh()->stage->code)->toBe('hired')
        ->and($vacancy->fresh()->filled_count)->toBe(1)
        ->and($vacancy->fresh()->status)->toBe(VacancyStatus::Filled)
        ->and($employee->onboardings()->sole()->status)->toBe(OnboardingStatus::InProgress)
        ->and($employee->onboardings()->sole()->tasks()->count())->toBe(10);

    $cv = $employee->documents()->sole();
    expect($cv->category)->toBe(DocumentCategory::Cv);
    Storage::disk('employee_files')->assertExists($cv->file_path);

    $account = User::query()->where('email', 'mona.fathy@company.test')->sole();
    expect($account->employee_id)->toBe($employee->id)->and($account->isEmployee())->toBeTrue();
    Notification::assertSentTo($account, ResetPassword::class);
    $this->assertDatabaseHas('audit_logs', ['event' => 'recruitment.candidate_converted', 'auditable_id' => $employee->id]);
});

it('refuses to hire without an accepted offer and never moves a card to the hired stage directly', function () {
    $application = JobApplication::factory()->create(['vacancy_id' => Vacancy::factory()->create(['branch_id' => $this->org['branch']->id, 'department_id' => $this->org['department']->id])]);
    $hr = superAdmin();

    $this->actingAs($hr, 'admin')->patchJson("/admin/applications/{$application->id}/stage", ['stage_id' => stageOf($application->vacancy, 'hired')->id])
        ->assertUnprocessable()->assertJsonValidationErrors('stage_id');

    $this->actingAs($hr, 'admin')->post("/admin/applications/{$application->id}/hire", [
        'name_ar' => 'أ', 'name_en' => 'A', 'hire_date' => today()->toDateString(),
        'branch_id' => $this->org['branch']->id, 'department_id' => $this->org['department']->id, 'employment_type_id' => $this->org['type']->id,
    ])->assertSessionHasErrors('hire_date');

    expect(Employee::query()->count())->toBe(0);
});

it('rejects a stage of another pipeline and records the rejection reason internally', function () {
    $vacancy = Vacancy::factory()->create(['branch_id' => $this->org['branch']->id, 'department_id' => $this->org['department']->id]);
    $other = Vacancy::factory()->create(['branch_id' => $this->org['branch']->id, 'department_id' => $this->org['department']->id]);
    app(Pipeline::class)->customise($other);
    $application = JobApplication::factory()->create(['vacancy_id' => $vacancy->id]);
    Notification::fake();

    $this->actingAs(superAdmin(), 'admin')->patchJson("/admin/applications/{$application->id}/stage", ['stage_id' => $other->stages()->first()->id])
        ->assertUnprocessable();

    $this->actingAs(superAdmin(), 'admin')->patch("/admin/applications/{$application->id}/stage", [
        'stage_id' => stageOf($vacancy, 'rejected')->id, 'rejection_reason' => 'Salary expectations too high',
    ])->assertSessionHasNoErrors();

    expect($application->fresh()->status)->toBe(ApplicationStatus::Rejected)
        ->and($application->fresh()->rejection_reason)->toBe('Salary expectations too high');
    Notification::assertSentOnDemand(ApplicationStageChanged::class);
});

it('warns about duplicate candidates by e-mail or phone until the admin confirms', function () {
    Candidate::factory()->create(['email' => 'sam@example.test', 'phone' => '+20 100 123 4567']);
    $hr = superAdmin();
    $payload = ['name_en' => 'Sam Other', 'email' => 'someone@example.test', 'phone' => '0100-123-4567', 'source' => 'referral'];

    $this->actingAs($hr, 'admin')->post('/admin/candidates', $payload)->assertSessionHasErrors('email');
    $this->actingAs($hr, 'admin')->post('/admin/candidates', [...$payload, 'confirm_duplicate' => '1'])->assertSessionHasNoErrors();

    expect(Candidate::query()->count())->toBe(2);
});

it('sends interview reminders once, and expires sent offers past their date', function () {
    Notification::fake();
    $interviewer = superAdmin();
    $application = JobApplication::factory()->create(['vacancy_id' => Vacancy::factory()->create(['branch_id' => $this->org['branch']->id, 'department_id' => $this->org['department']->id])]);
    $soon = $application->interviews()->create(['type' => 'hr', 'scheduled_at' => now()->addHours(5), 'timezone' => 'UTC', 'mode' => 'phone', 'status' => 'scheduled']);
    $soon->interviewers()->attach($interviewer);
    $later = $application->interviews()->create(['type' => 'hr', 'scheduled_at' => now()->addDays(3), 'timezone' => 'UTC', 'mode' => 'phone', 'status' => 'scheduled']);

    $this->artisan('recruitment:send-interview-reminders')->assertSuccessful();
    $this->artisan('recruitment:send-interview-reminders')->assertSuccessful();

    Notification::assertSentToTimes($interviewer, InterviewAssigned::class, 1);
    expect($soon->fresh()->reminder_sent_at)->not->toBeNull()->and($later->fresh()->reminder_sent_at)->toBeNull();

    $offer = new JobOffer(['job_application_id' => $application->id, 'start_date' => today()->addWeek(), 'expires_on' => today()->subDay()]);
    $offer->forceFill(['reference' => 'OFR-1', 'currency' => 'USD', 'status' => OfferStatus::Sent])->save();
    $this->artisan('recruitment:expire-offers')->assertSuccessful();

    expect($offer->fresh()->status)->toBe(OfferStatus::Expired);
});

it('completes end-to-end scenario 1 up to the new employee signing in to the mobile app', function () {
    Notification::fake();
    $hr = adminWithRoles('HR Manager');
    $approver = adminWithRoles('HR Manager');

    // 1. HR creates a vacancy.
    $this->actingAs($hr, 'admin')->post('/admin/vacancies', vacancyPayload($this->org))->assertSessionHasNoErrors();
    $vacancy = Vacancy::query()->sole();

    // 2. The candidate applies.
    $this->post("/careers/{$vacancy->reference}/apply", ['name' => 'Karim Nabil', 'email' => 'karim@example.test', 'phone' => '+201007770000', 'cv' => fakeCv(), 'consent' => '1'])
        ->assertSessionHasNoErrors();
    $application = JobApplication::query()->sole();

    // 3. The candidate passes the process and accepts the offer.
    foreach (['shortlisted', 'hr_interview', 'final_interview'] as $code) {
        $this->actingAs($hr, 'admin')->patch("/admin/applications/{$application->id}/stage", ['stage_id' => stageOf($vacancy, $code)->id])->assertSessionHasNoErrors();
    }
    $this->actingAs($hr, 'admin')->post("/admin/applications/{$application->id}/offers", [
        'basic_salary' => '9000', 'start_date' => today()->toDateString(), 'expires_on' => today()->toDateString(),
    ])->assertSessionHasNoErrors();
    $offer = JobOffer::query()->sole();
    $this->actingAs($hr, 'admin')->post("/admin/offers/{$offer->id}/submit");
    $this->actingAs($approver, 'admin')->post("/admin/offers/{$offer->id}/approval/approve");
    $this->actingAs($hr, 'admin')->post("/admin/offers/{$offer->id}/send");
    $this->actingAs($hr, 'admin')->post("/admin/offers/{$offer->id}/accept")->assertSessionHasNoErrors();

    // 4–7. Hired, converted into an employee with a branch and department, and given an account.
    $this->actingAs($hr, 'admin')->post("/admin/applications/{$application->id}/hire", [
        'name_ar' => 'كريم نبيل', 'name_en' => 'Karim Nabil', 'hire_date' => today()->toDateString(),
        'branch_id' => $this->org['branch']->id, 'department_id' => $this->org['department']->id, 'employment_type_id' => $this->org['type']->id,
        'create_account' => '1', 'account_email' => 'karim@company.test',
    ])->assertSessionHasNoErrors();

    $employee = Employee::query()->sole();
    expect($employee->branch_id)->toBe($this->org['branch']->id)->and($employee->department_id)->toBe($this->org['department']->id);

    // 8. The employee sets a password from the invitation and signs in to the mobile API.
    $account = User::query()->where('email', 'karim@company.test')->sole();
    $this->post('/portal/reset-password', [
        'token' => Password::createToken($account), 'email' => 'karim@company.test', 'password' => 'Karim-2026!', 'password_confirmation' => 'Karim-2026!',
    ])->assertSessionHasNoErrors();
    auth('admin')->logout();

    $token = $this->postJson('/api/v1/auth/login', ['email' => 'karim@company.test', 'password' => 'Karim-2026!', 'device_name' => 'Pixel'])
        ->assertOk()->json('data.token');
    $this->withToken($token)->getJson('/api/v1/me/onboarding')->assertOk()->assertJsonPath('data.status', 'in_progress');
});
