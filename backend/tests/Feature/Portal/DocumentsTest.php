<?php

use App\Models\EmployeeContract;
use App\Models\EmployeeDocument;
use App\Models\HrRequest;
use App\Models\HrRequestAttachment;
use App\Models\TrainingCertificate;
use App\Models\User;
use App\Services\Employees\SensitiveDataAccessLog;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/*
| "My documents" (Phase 9 portal page, API GET /api/v1/me/documents/all): shared documents, own contracts,
| certificates, letters issued on HR requests and payslips in one place — nothing HR did not share,
| nothing internal, nothing of anyone else.
*/

beforeEach(function () {
    seedRoles();
    $this->user = User::factory()->employee()->create();
    $this->employee = $this->user->employee;
    $this->other = User::factory()->employee()->create()->employee;
    $file = ['file_name' => 'f.pdf', 'file_mime' => 'application/pdf', 'file_size' => 2048];

    $this->shared = EmployeeDocument::query()->forceCreate(['employee_id' => $this->employee->id, 'category' => 'national_id', 'title' => 'ID copy', 'visible_to_employee' => true, 'file_path' => 'docs/id.pdf', ...$file]);
    EmployeeDocument::query()->forceCreate(['employee_id' => $this->employee->id, 'category' => 'medical', 'title' => 'HR only report', 'visible_to_employee' => false, 'file_path' => 'docs/m.pdf', ...$file]);
    EmployeeDocument::query()->forceCreate(['employee_id' => $this->other->id, 'category' => 'cv', 'title' => 'Their CV', 'visible_to_employee' => true, 'file_path' => 'docs/cv.pdf', ...$file]);

    $this->contract = EmployeeContract::query()->forceCreate(['employee_id' => $this->employee->id, 'reference' => 'CT-MINE', 'contract_type' => 'fixed_term', 'start_date' => '2025-01-01', 'end_date' => '2027-01-01', 'file_path' => 'contracts/c.pdf', ...$file]);
    EmployeeContract::query()->forceCreate(['employee_id' => $this->employee->id, 'reference' => 'CT-NOFILE', 'contract_type' => 'fixed_term', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31']);
    EmployeeContract::query()->forceCreate(['employee_id' => $this->other->id, 'reference' => 'CT-THEIRS', 'contract_type' => 'fixed_term', 'start_date' => '2025-01-01', 'file_path' => 'contracts/t.pdf', ...$file]);

    $this->certificate = TrainingCertificate::factory()->create(['employee_id' => $this->employee->id, 'name' => 'First aid', 'file_path' => 'certs/a.pdf', ...$file]);

    $request = HrRequest::factory()->create(['employee_id' => $this->employee->id]);
    $this->letter = HrRequestAttachment::query()->forceCreate(['hr_request_id' => $request->id, 'is_letter' => true, 'is_internal' => false, 'file_path' => 'letters/l.pdf', ...$file]);
    HrRequestAttachment::query()->forceCreate(['hr_request_id' => $request->id, 'is_letter' => false, 'is_internal' => true, 'file_path' => 'letters/internal.pdf', 'file_name' => 'internal-note.pdf', 'file_mime' => 'application/pdf', 'file_size' => 1]);
    $theirRequest = HrRequest::factory()->create(['employee_id' => $this->other->id]);
    HrRequestAttachment::query()->forceCreate(['hr_request_id' => $theirRequest->id, 'is_letter' => true, 'is_internal' => false, 'file_path' => 'letters/t.pdf', 'file_name' => 'their-letter.pdf', 'file_mime' => 'application/pdf', 'file_size' => 1]);
});

it('lists every kind of own document on one portal page', function () {
    $this->actingAs($this->user, 'employee')->get(route('portal.documents'))
        ->assertOk()
        ->assertSee('ID copy')
        ->assertSee('CT-MINE')
        ->assertSee('First aid')
        ->assertSee(route('portal.documents.contracts.download', $this->contract->id), false)
        ->assertSee(route('portal.requests.attachments.download', [$this->letter->hr_request_id, $this->letter->id]), false)
        ->assertDontSee('HR only report')
        ->assertDontSee('CT-NOFILE')
        ->assertDontSee('Their CV')
        ->assertDontSee('CT-THEIRS')
        ->assertDontSee('internal-note.pdf')
        ->assertDontSee('their-letter.pdf');
});

it('returns the consolidated list with download URLs through the API', function () {
    Sanctum::actingAs($this->user, ['employee']);

    $response = $this->getJson(route('api.v1.me.documents.all'))->assertOk()
        ->assertJsonPath('meta.counts', ['documents' => 1, 'contracts' => 1, 'certificates' => 1, 'letters' => 1, 'payslips' => 0])
        ->assertJsonPath('data.documents.0.title', 'ID copy')
        ->assertJsonPath('data.documents.0.download_url', route('api.v1.me.documents.download', $this->shared->id))
        ->assertJsonPath('data.contracts.0.download_url', route('api.v1.me.contracts.download', $this->contract->id))
        ->assertJsonPath('data.certificates.0.download_url', route('api.v1.me.training.certificates.download', $this->certificate->id))
        ->assertJsonPath('data.letters.0.download_url', route('api.v1.me.requests.attachments.show', [$this->letter->hr_request_id, $this->letter->id]))
        ->assertJsonPath('data.contracts.0.is_current', true);

    expect($response->json('data.letters.0.type'))->toBe('letter');
});

it('keeps the existing paginated me/documents answer unchanged (backward compatible)', function () {
    Sanctum::actingAs($this->user, ['employee']);

    $this->getJson(route('api.v1.me.documents.index'))->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonStructure(['data' => [['id', 'category', 'category_label', 'title', 'download_url']], 'meta' => ['pagination']]);
});

it('serves the own contract copy and logs the access, but 404s another employee\'s contract', function () {
    Storage::fake('employee_files');
    Storage::disk('employee_files')->put('contracts/c.pdf', '%PDF-1.4 test');
    $theirs = EmployeeContract::query()->where('reference', 'CT-THEIRS')->value('id');

    $this->actingAs($this->user, 'employee')->get(route('portal.documents.contracts.download', $this->contract->id))->assertOk();
    $this->actingAs($this->user, 'employee')->get(route('portal.documents.contracts.download', $theirs))->assertNotFound();

    Sanctum::actingAs($this->user, ['employee']);
    $this->get(route('api.v1.me.contracts.download', $theirs), ['Accept' => 'application/json'])->assertNotFound();

    $this->assertDatabaseHas('audit_logs', ['event' => SensitiveDataAccessLog::CONTRACT, 'auditable_id' => $this->contract->id]);
});

it('shows an empty state when there is nothing yet', function () {
    $newcomer = User::factory()->employee()->create();

    $this->actingAs($newcomer, 'employee')->get(route('portal.documents'))->assertOk()->assertSee(__('app.my_documents.empty'));
});
