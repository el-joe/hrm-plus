<?php

use App\Models\AnnouncementRead;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use App\Services\Announcements\AnnouncementFileStore;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/AnnouncementHelpers.php';

/*
| Portal and mobile API (Phase 8b): only live announcements addressed to the employee; opening one
| marks it read; acknowledgement; attachments; another audience's announcement is a 404.
*/

beforeEach(function () {
    seedRoles();
    Notification::fake();
    Storage::fake('announcement_files');
    $this->hr = adminWithRoles('HR Manager');
    $this->mine = Branch::factory()->create();
    $this->theirs = Branch::factory()->create();
    $this->employee = Employee::factory()->create(['branch_id' => $this->mine->id]);
    $this->user = User::factory()->employee()->create(['employee_id' => $this->employee->id]);
    $this->forMe = makeAnnouncement($this->hr, ['title_en' => 'Mine', 'audience' => 'targeted', 'targets' => ['branch' => [$this->mine->id]], 'requires_acknowledgement' => true]);
    $this->notForMe = makeAnnouncement($this->hr, ['title_en' => 'Theirs', 'audience' => 'targeted', 'targets' => ['branch' => [$this->theirs->id]]]);
});

it('lists only the employee\'s announcements in the portal', function () {
    $this->actingAs($this->user, 'employee')->get(route('portal.announcements.index'))
        ->assertOk()->assertSee('Mine')->assertDontSee('Theirs');
});

it('marks an announcement read when opened and records the acknowledgement', function () {
    $this->actingAs($this->user, 'employee')->get(route('portal.announcements.show', $this->forMe->id))->assertOk()->assertSee('Announcement text');

    expect(AnnouncementRead::query()->where('employee_id', $this->employee->id)->first())->read_at->not->toBeNull();

    $this->actingAs($this->user, 'employee')->post(route('portal.announcements.acknowledge', $this->forMe->id))->assertRedirect();

    expect(AnnouncementRead::query()->where('employee_id', $this->employee->id)->first()->acknowledged_at)->not->toBeNull()
        ->and(AnnouncementRead::query()->first()->acknowledged_via)->toBe('portal');
});

it('returns 404 for announcements outside the employee\'s audience', function () {
    $this->actingAs($this->user, 'employee')->get(route('portal.announcements.show', $this->notForMe->id))->assertNotFound();
    $this->actingAs($this->user, 'employee')->post(route('portal.announcements.acknowledge', $this->notForMe->id))->assertNotFound();

    Sanctum::actingAs($this->user);
    $this->getJson(route('api.v1.me.announcements.show', $this->notForMe->id))->assertNotFound();
    $this->postJson(route('api.v1.me.announcements.read', $this->notForMe->id))->assertNotFound();

    expect(AnnouncementRead::query()->count())->toBe(0);
});

it('serves the API list with unread meta and the detail with both body formats', function () {
    Sanctum::actingAs($this->user);

    $this->getJson(route('api.v1.me.announcements.index'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->forMe->id)
        ->assertJsonPath('data.0.is_read', false)
        ->assertJsonPath('data.0.can_acknowledge', true)
        ->assertJsonPath('meta.unread_count', 1)
        ->assertJsonPath('meta.pending_acknowledgements', 1)
        ->assertJsonStructure(['meta' => ['pagination' => ['current_page', 'per_page', 'total', 'last_page']]]);

    $this->getJson(route('api.v1.me.announcements.show', $this->forMe->id))
        ->assertOk()
        ->assertJsonPath('data.body_html', '<p>Announcement text</p>')
        ->assertJsonPath('data.body_text', 'Announcement text')
        ->assertJsonPath('data.is_read', true);

    $this->postJson(route('api.v1.me.announcements.acknowledge', $this->forMe->id))
        ->assertOk()->assertJsonPath('data.acknowledged', true)->assertJsonPath('data.can_acknowledge', false);

    $this->getJson(route('api.v1.me.announcements.index', ['unread' => 1]))->assertOk()->assertJsonCount(0, 'data');
});

it('answers 422 when acknowledging an announcement that needs no acknowledgement', function () {
    $plain = makeAnnouncement($this->hr);
    Sanctum::actingAs($this->user);

    $this->postJson(route('api.v1.me.announcements.acknowledge', $plain->id))->assertUnprocessable();
});

it('downloads attachments only from announcements addressed to the employee', function () {
    $store = app(AnnouncementFileStore::class);
    $mine = $this->forMe->attachments()->create($store->storeContents($this->forMe, 'PDF', 'mine.pdf', 'application/pdf'));
    $theirs = $this->notForMe->attachments()->create($store->storeContents($this->notForMe, 'PDF', 'theirs.pdf', 'application/pdf'));

    $this->actingAs($this->user, 'employee')->get(route('portal.announcements.attachments.download', [$this->forMe->id, $mine->id]))->assertOk();
    $this->actingAs($this->user, 'employee')->get(route('portal.announcements.attachments.download', [$this->notForMe->id, $theirs->id]))->assertNotFound();
    // An attachment id of another announcement through an allowed announcement is a 404 too.
    $this->actingAs($this->user, 'employee')->get(route('portal.announcements.attachments.download', [$this->forMe->id, $theirs->id]))->assertNotFound();

    Sanctum::actingAs($this->user);
    $this->get(route('api.v1.me.announcements.attachments.show', [$this->forMe->id, $mine->id]))->assertOk();
});

it('refuses admin accounts on the API', function () {
    Sanctum::actingAs($this->hr);

    $this->getJson(route('api.v1.me.announcements.index'))->assertForbidden();
});
