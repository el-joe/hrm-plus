<?php

use App\Enums\HrRequestStatus;
use App\Models\HrRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/HrRequestHelpers.php';

/*
| Portal "My requests" and /api/v1/me/requests (Phase 8b): own requests only, shapes, validation, files.
*/

beforeEach(function () {
    seedHrRequests();
    Notification::fake();
    Storage::fake('hr_request_files');

    [$this->employee, $this->user] = hrRequestEmployee();
    [$this->otherEmployee, $this->otherUser] = hrRequestEmployee();
    $this->theirs = fileHrRequest($this->otherEmployee, $this->otherUser);
});

it('submits from the portal and shows the request', function () {
    $this->actingAs($this->user, 'employee')->post(route('portal.requests.store'), [
        'hr_request_category_id' => hrCategory('GENERAL_INQUIRY')->id,
        'subject' => 'Medical insurance card',
        'description' => 'My card has not arrived.',
        'attachments' => [UploadedFile::fake()->create('form.pdf', 50, 'application/pdf')],
    ])->assertRedirect();

    $request = HrRequest::query()->where('employee_id', $this->employee->id)->firstOrFail();
    expect($request->subject)->toBe('Medical insurance card')->and($request->source->value)->toBe('portal')->and($request->attachments)->toHaveCount(1);

    $this->actingAs($this->user, 'employee')->get(route('portal.requests.index'))->assertOk()->assertSee('Medical insurance card');
    $this->actingAs($this->user, 'employee')->get(route('portal.requests.show', $request->id))->assertOk()->assertSee($request->reference);
});

it('never shows or touches another employee\'s request in the portal', function () {
    $this->actingAs($this->user, 'employee')->get(route('portal.requests.show', $this->theirs->id))->assertNotFound();
    $this->actingAs($this->user, 'employee')->post(route('portal.requests.reply', $this->theirs->id), ['body' => 'Hi there'])->assertNotFound();
    $this->actingAs($this->user, 'employee')->post(route('portal.requests.cancel', $this->theirs->id))->assertNotFound();
    $this->actingAs($this->user, 'employee')->get(route('portal.requests.index'))->assertDontSee($this->theirs->reference);

    expect($this->theirs->refresh()->status)->toBe(HrRequestStatus::Open);
});

it('lists categories with their fields for the app', function () {
    Sanctum::actingAs($this->user);

    $this->getJson(route('api.v1.me.requests.categories'))->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => [['id', 'code', 'name', 'type', 'requires_attachment', 'requires_approval', 'sla_hours', 'letter_languages', 'fields']], 'meta' => ['attachments']]);
});

it('creates, lists, replies to, attaches to and cancels a request through the API', function () {
    Sanctum::actingAs($this->user);

    $created = $this->postJson(route('api.v1.me.requests.store'), [
        'hr_request_category_id' => hrCategory('SALARY_CERT')->id, 'letter_language' => 'en', 'fields' => ['addressee' => 'My bank'],
    ])->assertCreated()->assertJsonPath('data.status', 'open')->assertJsonPath('data.category.type', 'letter')->json('data');

    expect($created['fields'][0]['value'])->toBe('My bank');

    $this->getJson(route('api.v1.me.requests.index'))->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.reference', $created['reference'])
        ->assertJsonStructure(['meta' => ['pagination' => ['current_page', 'per_page', 'total', 'last_page']]]);

    $this->post(route('api.v1.me.requests.replies.store', $created['id']), [
        'body' => 'Any update?', 'attachments' => [UploadedFile::fake()->create('note.pdf', 20, 'application/pdf')],
    ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.messages.0.from_employee', true)->assertJsonCount(1, 'data.attachments');

    $this->postJson(route('api.v1.me.requests.cancel', $created['id']))->assertOk()->assertJsonPath('data.status', 'cancelled');
});

it('validates API submissions', function () {
    Sanctum::actingAs($this->user);

    $this->postJson(route('api.v1.me.requests.store'), [])->assertUnprocessable()->assertJsonPath('success', false)->assertJsonStructure(['errors' => ['hr_request_category_id']]);
    $this->postJson(route('api.v1.me.requests.store'), ['hr_request_category_id' => hrCategory('GENERAL_INQUIRY')->id])->assertUnprocessable()->assertJsonStructure(['errors' => ['description']]);
    $this->postJson(route('api.v1.me.requests.store'), ['hr_request_category_id' => hrCategory('PROFILE_CORRECTION')->id, 'changes' => ['personal_email' => 'not-an-email']])
        ->assertUnprocessable()->assertJsonStructure(['errors' => ['changes.personal_email']]);
    $this->post(route('api.v1.me.requests.store'), [
        'hr_request_category_id' => hrCategory('GENERAL_INQUIRY')->id, 'description' => 'x', 'attachments' => [UploadedFile::fake()->create('virus.exe', 10)],
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonStructure(['errors' => ['attachments.0']]);
});

it('returns 404 for another employee\'s request in the API', function () {
    Sanctum::actingAs($this->user);

    $this->getJson(route('api.v1.me.requests.show', $this->theirs->id))->assertNotFound();
    $this->postJson(route('api.v1.me.requests.replies.store', $this->theirs->id), ['body' => 'Hello'])->assertNotFound();
});

it('shows the profile fields with masked identity values', function () {
    $this->employee->update(['national_id' => '29001011234567']);
    Sanctum::actingAs($this->user);

    $fields = collect($this->getJson(route('api.v1.me.requests.profile-fields'))->assertOk()->json('data'))->keyBy('field');

    expect($fields['national_id']['masked'])->toBeTrue()->and($fields['national_id']['current'])->not->toContain('29001011234');
});

it('refuses admin accounts on the employee API', function () {
    Sanctum::actingAs(adminWithRoles('HR Manager'));

    $this->getJson(route('api.v1.me.requests.index'))->assertForbidden();
});
