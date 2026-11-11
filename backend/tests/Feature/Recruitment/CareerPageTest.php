<?php

use App\Models\Candidate;
use App\Models\JobApplication;
use App\Models\Vacancy;
use App\Services\Settings\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('recruitment_files');
    Notification::fake();
    seedRecruitment();
    $this->vacancy = Vacancy::factory()->create(['title_en' => 'Published Role', 'salary_min' => '7777.00', 'salary_max' => '8888.00']);
});

/**
 * @return array<string, mixed>
 */
function careerApplication(array $overrides = []): array
{
    return ['name' => 'Hany Lotfy', 'email' => 'hany@example.test', 'phone' => '+20 122 333 4444', 'cv' => fakeCv(), 'consent' => '1', ...$overrides];
}

it('lists only published vacancies, without salaries', function () {
    Vacancy::factory()->create(['title_en' => 'Hidden Internal', 'is_public' => false]);
    Vacancy::factory()->draft()->create(['title_en' => 'Draft Role']);
    Vacancy::factory()->create(['title_en' => 'Expired Role', 'application_deadline' => today()->subDay()]);
    Vacancy::factory()->create(['title_en' => 'Future Role', 'publish_at' => today()->addDay()]);

    $this->withSession(['locale' => 'en'])->get('/careers')->assertOk()
        ->assertSee('Published Role')->assertDontSee('Hidden Internal')->assertDontSee('Draft Role')
        ->assertDontSee('Expired Role')->assertDontSee('Future Role');

    $this->get("/careers/{$this->vacancy->reference}")->assertOk()->assertDontSee('7,777.00')->assertDontSee('7777');
    $this->get('/careers/'.Vacancy::query()->where('title_en', 'Draft Role')->value('reference'))->assertNotFound();
});

it('is switched off by the setting', function () {
    app(SettingsService::class)->set('recruitment.career_page_enabled', false);

    $this->get('/careers')->assertNotFound();
    $this->post("/careers/{$this->vacancy->reference}/apply", careerApplication())->assertNotFound();
    expect(Candidate::query()->count())->toBe(0);
});

it('validates the CV type and size, the consent and the honeypot', function (array $input, string $field) {
    $this->post("/careers/{$this->vacancy->reference}/apply", careerApplication($input))->assertSessionHasErrors($field);

    expect(JobApplication::query()->count())->toBe(0);
})->with([
    'executable disguised as CV' => [['cv' => UploadedFile::fake()->create('cv.php', 10, 'application/x-php')], 'cv'],
    'image instead of document' => [['cv' => UploadedFile::fake()->image('cv.png')], 'cv'],
    'CV too large' => [['cv' => UploadedFile::fake()->create('cv.pdf', 6000, 'application/pdf')], 'cv'],
    'no consent' => [['consent' => null], 'consent'],
    'bot filled the honeypot' => [['website' => 'http://spam.test'], 'website'],
    'bad e-mail' => [['email' => 'not-an-email'], 'email'],
]);

it('matches a returning applicant to the existing candidate and refuses a second application to the same vacancy', function () {
    $existing = Candidate::factory()->create(['email' => 'hany@example.test', 'name_en' => 'Hany L.', 'city' => null]);
    $other = Vacancy::factory()->create();

    $this->post("/careers/{$this->vacancy->reference}/apply", careerApplication(['city' => 'Tanta']))->assertSessionHasNoErrors();
    $this->post("/careers/{$other->reference}/apply", careerApplication())->assertSessionHasNoErrors();
    $this->post("/careers/{$this->vacancy->reference}/apply", careerApplication())->assertSessionHasErrors('vacancy_id');

    expect(Candidate::query()->count())->toBe(1)
        ->and($existing->fresh()->name_en)->toBe('Hany L.')
        ->and($existing->fresh()->city)->toBe('Tanta')
        ->and($existing->applications()->count())->toBe(2);
});

it('rate limits applications per visitor', function () {
    RateLimiter::clear('career-apply');
    app(SettingsService::class)->set('recruitment.career_applications_per_hour', 2);

    foreach (['a', 'b'] as $suffix) {
        $this->post("/careers/{$this->vacancy->reference}/apply", careerApplication(['email' => "{$suffix}@example.test", 'phone' => '+2010000000'.ord($suffix)]))
            ->assertRedirect();
    }

    $this->post("/careers/{$this->vacancy->reference}/apply", careerApplication(['email' => 'c@example.test', 'phone' => '+201099999999']))
        ->assertTooManyRequests();
    expect(JobApplication::query()->count())->toBe(2);
});
