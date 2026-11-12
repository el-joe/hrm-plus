<?php

use App\Actions\HrRequests\ManageHrRequest;
use App\Notifications\HrRequests\HrRequestNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/HrRequestHelpers.php';

/*
| Internal notes and HR-only files never reach the employee (Phase 8b): not in the portal page, not in the
| API JSON, not in any notification, not downloadable.
*/

beforeEach(function () {
    seedHrRequests();
    Notification::fake();
    Storage::fake('hr_request_files');

    [$this->employee, $this->user] = hrRequestEmployee();
    $this->hr = adminWithRoles('HR Manager');

    $this->request = fileHrRequest($this->employee, $this->user);
    $manage = app(ManageHrRequest::class);
    $manage->reply($this->request, $this->hr, 'SECRET-NOTE: check his attendance first.', [UploadedFile::fake()->create('internal.pdf', 10, 'application/pdf')], internal: true);
    $manage->reply($this->request->refresh(), $this->hr, 'We are looking into it.');
    $this->internalFile = $this->request->attachments()->where('is_internal', true)->firstOrFail();
});

it('shows HR the internal note, clearly marked', function () {
    $this->actingAs($this->hr, 'admin')->get(route('admin.hr-requests.show', $this->request))
        ->assertOk()->assertSee('SECRET-NOTE: check his attendance first.')->assertSee('data-message-type="internal_note"', false);
});

it('never shows the internal note on the portal page', function () {
    $this->actingAs($this->user, 'employee')->get(route('portal.requests.show', $this->request->id))
        ->assertOk()
        ->assertSee('We are looking into it.')
        ->assertDontSee('SECRET-NOTE')
        ->assertDontSee('internal.pdf');
});

it('never returns the internal note or file in the API', function () {
    Sanctum::actingAs($this->user);
    $response = $this->getJson(route('api.v1.me.requests.show', $this->request->id))->assertOk();

    expect(json_encode($response->json()))->not->toContain('SECRET-NOTE')->not->toContain('internal.pdf');
    expect($response->json('data.messages'))->toHaveCount(1);
});

it('refuses to stream an HR-only file to the employee', function () {
    $this->actingAs($this->user, 'employee')->get(route('portal.requests.attachments.download', [$this->request->id, $this->internalFile->id]))->assertNotFound();
    Sanctum::actingAs($this->user);
    $this->get(route('api.v1.me.requests.attachments.show', [$this->request->id, $this->internalFile->id]))->assertNotFound();
});

it('never sends an internal note in a notification', function () {
    Notification::assertSentTo($this->user, HrRequestNotification::class, function (HrRequestNotification $notification) {
        $data = $notification->toArray($this->user);

        return ! str_contains(json_encode($data), 'SECRET-NOTE');
    });
    Notification::assertNotSentTo($this->user, HrRequestNotification::class, fn (HrRequestNotification $notification) => str_contains((string) $notification->excerpt, 'SECRET-NOTE'));
});
