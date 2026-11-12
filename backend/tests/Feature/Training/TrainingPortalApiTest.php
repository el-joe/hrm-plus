<?php

use App\Enums\TrainingEnrolmentStatus;
use App\Models\TrainingCertificate;
use App\Models\TrainingEnrolment;
use App\Models\User;
use App\Services\Training\TrainingFileStore;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/TrainingHelpers.php';

/*
| "My training" in the portal and GET/POST /api/v1/me/training*: own records only
| (someone else's id is a 404), requests through approval, certificate upload and
| download from the private disk.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00:00'));
    seedTraining();
    Notification::fake();
    Storage::fake(TrainingFileStore::DISK);
    $this->hr = adminWithRoles('HR Manager');
    $this->employee = attendanceEmployee();
    $this->other = attendanceEmployee();
    $this->account = User::factory()->employee()->create(['employee_id' => $this->employee->id]);
    $this->session = trainingSession(by: $this->hr);
    $this->mine = enrolEmployee($this->session, $this->employee, $this->hr);
    $this->theirs = enrolEmployee($this->session, $this->other, $this->hr);
    $this->open = trainingSession(by: $this->hr);
    $this->myCertificate = TrainingCertificate::factory()->create(['employee_id' => $this->employee->id, 'name' => 'My first aid']);
    $this->theirCertificate = TrainingCertificate::factory()->create(['employee_id' => $this->other->id, 'name' => 'Their forklift licence']);

    foreach ([$this->myCertificate, $this->theirCertificate] as $certificate) {
        $certificate->forceFill(app(TrainingFileStore::class)->storeContents($certificate->employee_id, '%PDF-1.4 demo', 'cert.pdf', 'application/pdf'))->save();
    }
});

describe('portal', function () {
    it('shows only the employee\'s own training, certificates and open sessions', function () {
        $this->actingAs($this->account, 'employee')->get(route('portal.training'))
            ->assertOk()->assertSee('My first aid')->assertDontSee('Their forklift licence')
            ->assertSee(route('portal.training.enrolments.show', $this->mine->id))
            ->assertDontSee(route('portal.training.enrolments.show', $this->theirs->id));
    });

    it('answers 404 for someone else\'s enrolment and certificate file', function () {
        $this->actingAs($this->account, 'employee')->get(route('portal.training.enrolments.show', $this->theirs->id))->assertNotFound();
        $this->actingAs($this->account, 'employee')->get(route('portal.training.certificates.download', $this->theirCertificate->id))->assertNotFound();
        $this->actingAs($this->account, 'employee')->get(route('portal.training.certificates.download', $this->myCertificate->id))->assertOk();
    });

    it('sends a request for approval and lets the employee withdraw it', function () {
        $this->actingAs($this->account, 'employee')->post(route('portal.training.requests.store'), ['training_session_id' => $this->open->id, 'reason' => 'Growth'])
            ->assertRedirect(route('portal.training'));

        $request = TrainingEnrolment::query()->where('training_session_id', $this->open->id)->sole();
        expect($request->status)->toBe(TrainingEnrolmentStatus::Pending)->and($request->requested_by)->toBe($this->account->id);

        $this->actingAs($this->account, 'employee')->post(route('portal.training.enrolments.cancel', $request->id))->assertRedirect();
        expect($request->refresh()->status)->toBe(TrainingEnrolmentStatus::Cancelled);
    });

    it('does not let the employee cancel an enrolment HR made (only pending requests)', function () {
        $this->actingAs($this->account, 'employee')->post(route('portal.training.enrolments.cancel', $this->mine->id))->assertForbidden();
    });

    it('uploads a certificate to the private disk with validated files', function () {
        $this->actingAs($this->account, 'employee')->post(route('portal.training.certificates.store'), [
            'type' => 'licence', 'name' => 'Forklift', 'expires_on' => '2027-05-01', 'file' => UploadedFile::fake()->create('lic.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $certificate = TrainingCertificate::query()->where('name', 'Forklift')->sole();
        expect($certificate->employee_id)->toBe($this->employee->id)->and($certificate->uploaded_by)->toBe($this->account->id);
        Storage::disk(TrainingFileStore::DISK)->assertExists($certificate->file_path);

        $this->actingAs($this->account, 'employee')->post(route('portal.training.certificates.store'), [
            'type' => 'licence', 'name' => 'Bad', 'file' => UploadedFile::fake()->create('run.exe', 10),
        ])->assertSessionHasErrors('file');
    });
});

describe('API', function () {
    beforeEach(function () {
        $this->headers = ['Authorization' => 'Bearer '.$this->account->createToken('phone')->plainTextToken, 'Accept-Language' => 'en'];
    });

    it('returns the summary of the token owner', function () {
        $this->getJson(route('api.v1.me.training.summary'), $this->headers)->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.upcoming')
            ->assertJsonPath('data.upcoming.0.id', $this->mine->id)
            ->assertJsonPath('data.upcoming.0.session.reference', $this->session->reference)
            ->assertJsonPath('data.upcoming.0.session.seats_left', 8)
            ->assertJsonPath('meta.requests_enabled', true)
            ->assertJsonStructure(['data' => ['upcoming', 'pending_requests', 'completed_count', 'expiring_certificates', 'expired_certificates_count', 'skills']]);
    });

    it('lists enrolments and certificates of the owner only, 404 for others', function () {
        $this->getJson(route('api.v1.me.training.enrolments.index'), $this->headers)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->mine->id)->assertJsonPath('meta.pagination.total', 1);
        $this->getJson(route('api.v1.me.training.enrolments.show', $this->theirs->id), $this->headers)->assertNotFound();
        $this->getJson(route('api.v1.me.training.certificates.index'), $this->headers)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'My first aid')->assertJsonPath('data.0.has_file', true);
        $this->get(route('api.v1.me.training.certificates.download', $this->theirCertificate->id), $this->headers)->assertNotFound();
        $this->get(route('api.v1.me.training.certificates.download', $this->myCertificate->id), $this->headers)->assertOk();
    });

    it('offers open sessions and creates a pending request (201), then a withdrawal', function () {
        $this->getJson(route('api.v1.me.training.sessions.index'), $this->headers)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->open->id);

        $response = $this->postJson(route('api.v1.me.training.requests.store'), ['training_session_id' => $this->open->id, 'reason' => 'Growth'], $this->headers)
            ->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.can_cancel', true)->assertJsonPath('data.current_step', 'Training approval');

        $this->postJson(route('api.v1.me.training.enrolments.cancel', $response->json('data.id')), [], $this->headers)
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson(route('api.v1.me.training.enrolments.cancel', $this->mine->id), [], $this->headers)
            ->assertUnprocessable()->assertJsonPath('code', 'not_cancellable');
    });

    it('validates the request and refuses a full session', function () {
        $this->postJson(route('api.v1.me.training.requests.store'), [], $this->headers)->assertUnprocessable()->assertJsonValidationErrors('training_session_id');

        $full = trainingSession(['capacity' => 1], by: $this->hr);
        enrolEmployee($full, $this->other, $this->hr);

        $this->postJson(route('api.v1.me.training.requests.store'), ['training_session_id' => $full->id], $this->headers)->assertUnprocessable();
    });

    it('uploads a certificate (multipart) and refuses admin tokens', function () {
        $this->post(route('api.v1.me.training.certificates.store'), [
            'type' => 'qualification', 'name' => 'BSc Computer Science', 'issued_on' => '2018-07-01', 'file' => UploadedFile::fake()->create('degree.pdf', 200, 'application/pdf'),
        ], [...$this->headers, 'Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.type', 'qualification')->assertJsonPath('data.validity', 'no_expiry');

        $admin = adminWithRoles('HR Manager');
        $this->app['auth']->forgetGuards(); // the sanctum guard keeps the employee of the previous request otherwise
        $this->getJson(route('api.v1.me.training.summary'), ['Authorization' => 'Bearer '.$admin->createToken('x')->plainTextToken])->assertForbidden();
    });
});
