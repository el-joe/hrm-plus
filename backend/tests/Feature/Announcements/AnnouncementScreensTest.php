<?php

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use App\Services\Announcements\AnnouncementFileStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/AnnouncementHelpers.php';

/*
| Admin announcement screens (Phase 8b): permissions, compose / publish over HTTP, statistics page,
| data-scoped list, attachments and the read report export.
*/

beforeEach(function () {
    seedRoles();
    Notification::fake();
    Storage::fake('announcement_files');
    $this->hr = adminWithRoles('HR Manager');
});

it('refuses admins without the announcement permissions', function () {
    $admin = adminWithRoles('Payroll Accountant');
    $announcement = makeAnnouncement($this->hr);

    $this->actingAs($admin, 'admin')->get(route('admin.announcements.index'))->assertForbidden();
    $this->actingAs($admin, 'admin')->get(route('admin.announcements.show', $announcement))->assertForbidden();
    $this->actingAs($admin, 'admin')->post(route('admin.announcements.store'), announcementInput())->assertForbidden();
});

it('lets viewers read but not change announcements', function () {
    $viewer = adminWithPermissions('announcements.view');
    $announcement = makeAnnouncement($this->hr, publish: false);

    $this->actingAs($viewer, 'admin')->withSession(['locale' => 'en'])->get(route('admin.announcements.index'))->assertOk()->assertSee('Test announcement');
    $this->actingAs($viewer, 'admin')->get(route('admin.announcements.edit', $announcement))->assertForbidden();
    $this->actingAs($viewer, 'admin')->post(route('admin.announcements.publish', $announcement))->assertForbidden();
    $this->actingAs($viewer, 'admin')->delete(route('admin.announcements.destroy', $announcement))->assertForbidden();
});

it('renders the list, the form and the show page', function () {
    $announcement = makeAnnouncement($this->hr, ['requires_acknowledgement' => true]);

    $this->actingAs($this->hr, 'admin')->withSession(['locale' => 'en'])->get(route('admin.announcements.index'))->assertOk()->assertSee($announcement->title_en);
    $this->actingAs($this->hr, 'admin')->get(route('admin.announcements.create'))->assertOk();
    $this->actingAs($this->hr, 'admin')->withSession(['locale' => 'en'])->get(route('admin.announcements.show', $announcement))->assertOk()
        ->assertSee('data-region="statistics"', false)->assertSee('Announcement text');
});

it('creates, sanitises and publishes an announcement with an attachment', function () {
    $branch = Branch::factory()->create();

    $response = $this->actingAs($this->hr, 'admin')->post(route('admin.announcements.store'), announcementInput([
        'body_en' => '<p>Hello <strong>team</strong></p><img src=x onerror=alert(1)>',
        'audience' => 'targeted',
        'targets' => ['branch' => [$branch->id]],
        'is_pinned' => '1',
        'attachments' => [UploadedFile::fake()->create('policy.pdf', 50, 'application/pdf')],
        'intent' => 'publish',
    ]));

    $announcement = Announcement::query()->latest('id')->firstOrFail();
    $response->assertRedirect(route('admin.announcements.show', $announcement));

    expect($announcement->state()->value)->toBe('published')
        ->and($announcement->is_pinned)->toBeTrue()
        ->and($announcement->body_en)->toBe('<p>Hello <strong>team</strong></p>')
        ->and($announcement->targets->pluck('target_id')->all())->toBe([$branch->id])
        ->and($announcement->attachments)->toHaveCount(1);
    Storage::disk('announcement_files')->assertExists($announcement->attachments->first()->file_path);
});

it('saves a draft without notifying anyone', function () {
    User::factory()->employee()->create(['employee_id' => Employee::factory()->create()->id]);

    $this->actingAs($this->hr, 'admin')->post(route('admin.announcements.store'), announcementInput(['intent' => 'draft']))->assertRedirect();

    expect(Announcement::query()->latest('id')->first()->state()->value)->toBe('draft');
    Notification::assertNothingSent();
});

it('shows a restricted admin only announcements within their scope', function () {
    $alex = Branch::factory()->create();
    $cairo = Branch::factory()->create();
    $manager = adminWithRoles('Branch Manager');
    $manager->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => $alex->id]);

    $everyone = makeAnnouncement($this->hr, ['title_en' => 'For everyone']);
    $forAlex = makeAnnouncement($this->hr, ['title_en' => 'For Alexandria', 'audience' => 'targeted', 'targets' => ['branch' => [$alex->id]]]);
    $forCairo = makeAnnouncement($this->hr, ['title_en' => 'For Cairo', 'audience' => 'targeted', 'targets' => ['branch' => [$cairo->id]]]);

    $this->actingAs($manager, 'admin')->withSession(['locale' => 'en'])->get(route('admin.announcements.index', ['search' => 'For']))
        ->assertOk()->assertSee('For everyone')->assertSee('For Alexandria')->assertDontSee('For Cairo');
    $this->actingAs($manager, 'admin')->get(route('admin.announcements.show', $forCairo))->assertForbidden();
    // Company-wide announcements are visible but cannot be changed by a restricted admin.
    $this->actingAs($manager, 'admin')->get(route('admin.announcements.edit', $everyone))->assertForbidden();
    $this->actingAs($manager, 'admin')->post(route('admin.announcements.store'), announcementInput(['intent' => 'draft']))
        ->assertSessionHasErrors('audience');
});

it('downloads attachments only for admins allowed to view the announcement', function () {
    $announcement = makeAnnouncement($this->hr);
    $attachment = $announcement->attachments()->create([
        ...app(AnnouncementFileStore::class)->storeContents($announcement, 'PDF', 'a.pdf', 'application/pdf'),
    ]);

    $this->actingAs($this->hr, 'admin')->get(route('admin.announcements.attachments.download', [$announcement, $attachment]))->assertOk();
    $this->actingAs(adminWithRoles('Recruiter'), 'admin')->get(route('admin.announcements.attachments.download', [$announcement, $attachment]))->assertForbidden();
});

it('exports the read report with the export permission only', function () {
    $employee = Employee::factory()->create();
    $announcement = makeAnnouncement($this->hr);
    AnnouncementRead::query()->create(['announcement_id' => $announcement->id, 'employee_id' => $employee->id, 'read_at' => now()]);

    $this->actingAs($this->hr, 'admin')->get(route('admin.announcements.export', [$announcement, 'format' => 'csv']))->assertOk()->assertDownload();
    $this->actingAs(adminWithPermissions('announcements.view'), 'admin')->get(route('admin.announcements.export', $announcement))->assertForbidden();
});
