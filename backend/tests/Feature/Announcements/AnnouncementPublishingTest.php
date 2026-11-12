<?php

use App\Actions\Announcements\ManageAnnouncement;
use App\Models\Announcement;
use App\Models\Branch;
use App\Models\Department;
use App\Models\DeviceToken;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\User;
use App\Notifications\Announcements\AnnouncementPublishedNotification;
use App\Notifications\Channels\PushNotificationChannel;
use App\Services\Announcements\AnnouncementReadStats;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/AnnouncementHelpers.php';

/*
| Publishing (Phase 8b): notifications go out once, when the announcement becomes visible (now or by
| announcements:publish-scheduled), through the channels the announcement allows; acknowledgements and
| read statistics; scope rules of restricted admins; drafts-only deletion; audit.
*/

beforeEach(function () {
    seedRoles();
    Notification::fake();
    $this->hr = adminWithRoles('HR Manager');
    $this->branch = Branch::factory()->create();
    $this->other = Branch::factory()->create();
    $this->employees = Employee::factory()->count(4)->create(['branch_id' => $this->branch->id, 'department_id' => Department::factory()->create(['branch_id' => $this->branch->id])->id]);
    $this->accounts = $this->employees->map(fn (Employee $employee) => User::factory()->employee()->create(['employee_id' => $employee->id]));
    $this->outsider = User::factory()->employee()->create(['employee_id' => Employee::factory()->create(['branch_id' => $this->other->id])->id]);
});

it('notifies every account of the audience once when published', function () {
    $announcement = makeAnnouncement($this->hr, ['audience' => 'targeted', 'targets' => ['branch' => [$this->branch->id]]]);

    foreach ($this->accounts as $account) {
        Notification::assertSentToTimes($account, AnnouncementPublishedNotification::class, 1);
    }
    Notification::assertNotSentTo($this->outsider, AnnouncementPublishedNotification::class);

    expect($announcement->refresh()->notified_at)->not->toBeNull()
        ->and($announcement->notified_count)->toBe(4);

    $this->artisan('announcements:publish-scheduled')->assertSuccessful();
    Notification::assertSentToTimes($this->accounts->first(), AnnouncementPublishedNotification::class, 1);
});

it('waits for the publish time of a scheduled announcement, then notifies once', function () {
    $announcement = makeAnnouncement($this->hr, ['publish_at' => now()->addHours(3)->toDateTimeString()]);

    Notification::assertNothingSent();
    expect($announcement->refresh()->notified_at)->toBeNull();

    $this->travel(4)->hours();
    $this->artisan('announcements:publish-scheduled')->assertSuccessful();
    $this->artisan('announcements:publish-scheduled')->assertSuccessful();

    Notification::assertSentToTimes($this->outsider, AnnouncementPublishedNotification::class, 1);
    expect($announcement->refresh()->notified_at)->not->toBeNull();
});

it('does not notify for drafts', function () {
    makeAnnouncement($this->hr, publish: false);
    $this->artisan('announcements:publish-scheduled')->assertSuccessful();

    Notification::assertNothingSent();
});

it('limits the channels to the announcement switches', function () {
    app(SettingsService::class)->setMany(['notifications.email_enabled' => true, 'notifications.push_enabled' => true]);
    $account = $this->accounts->first();
    $account->forceFill(['email' => 'reader@example.test'])->save();
    DeviceToken::query()->create([
        'user_id' => $account->id, 'token' => 'token-123', 'token_hash' => DeviceToken::hashOf('token-123'), 'platform' => 'android',
    ]);

    $quiet = new AnnouncementPublishedNotification(Announcement::factory()->published()->create(['notify_email' => false, 'notify_push' => false]));
    $loud = new AnnouncementPublishedNotification(Announcement::factory()->published()->create(['notify_email' => true, 'notify_push' => true]));

    expect($quiet->via($account))->toBe(['database'])
        ->and($loud->via($account))->toBe(['database', 'mail', PushNotificationChannel::class]);
});

it('uses the mandatory acknowledgement event when an acknowledgement is required', function () {
    $announcement = makeAnnouncement($this->hr, ['requires_acknowledgement' => true]);

    Notification::assertSentTo($this->accounts->first(), AnnouncementPublishedNotification::class,
        fn (AnnouncementPublishedNotification $notification) => $notification->event() === 'announcement_acknowledgement_required'
            && $notification->payload($this->accounts->first())['announcement_id'] === $announcement->id);
});

it('records acknowledgements and computes read statistics against the audience', function () {
    $announcement = makeAnnouncement($this->hr, ['audience' => 'targeted', 'targets' => ['branch' => [$this->branch->id]], 'requires_acknowledgement' => true]);
    $actions = app(ManageAnnouncement::class);

    $actions->markRead($announcement, $this->employees[0]);
    $actions->markRead($announcement, $this->employees[0]);
    $actions->acknowledge($announcement, $this->employees[1], $this->accounts[1], 'portal');
    // A read by someone outside the audience is not counted.
    $actions->markRead($announcement, $this->outsider->employee);

    expect(app(AnnouncementReadStats::class)->summary($announcement))->toBe([
        'audience' => 4, 'read' => 2, 'unread' => 2, 'acknowledged' => 1, 'pending_acknowledgement' => 3,
        'read_percent' => 50.0, 'acknowledged_percent' => 25.0,
    ]);
    $this->assertDatabaseHas('audit_logs', ['event' => 'announcement.acknowledged']);
});

it('refuses an acknowledgement the announcement does not ask for', function () {
    $announcement = makeAnnouncement($this->hr);

    app(ManageAnnouncement::class)->acknowledge($announcement, $this->employees[0], $this->accounts[0], 'api');
})->throws(ValidationException::class);

it('stores the body sanitised', function () {
    $announcement = makeAnnouncement($this->hr, ['body_en' => '<p onclick="x()">Hello<script>alert(1)</script></p>']);

    expect($announcement->body_en)->toBe('<p>Hello</p>');
});

it('needs a title and a text in at least one language', function () {
    makeAnnouncement($this->hr, ['title_ar' => '', 'title_en' => '', 'body_ar' => '', 'body_en' => '']);
})->throws(ValidationException::class);

it('keeps restricted admins inside their data scope', function () {
    $manager = adminWithRoles('Branch Manager');
    $manager->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => $this->branch->id]);
    $manager->refresh();
    $actions = app(ManageAnnouncement::class);

    expect(fn () => $actions->save(announcementInput(), $manager))->toThrow(ValidationException::class)
        ->and(fn () => $actions->save(announcementInput(['audience' => 'targeted', 'targets' => ['branch' => [$this->other->id]]]), $manager))->toThrow(ValidationException::class)
        ->and(fn () => $actions->save(announcementInput(['audience' => 'targeted', 'targets' => ['employment_type' => [EmploymentType::factory()->create()->id]]]), $manager))->toThrow(ValidationException::class);

    $own = $actions->save(announcementInput(['audience' => 'targeted', 'targets' => ['branch' => [$this->branch->id]]]), $manager);
    $departmentOfBranch = Department::factory()->create(['branch_id' => $this->branch->id]);
    $byDepartment = $actions->save(announcementInput(['audience' => 'targeted', 'targets' => ['department' => [$departmentOfBranch->id]]]), $manager);

    expect($own->targets)->toHaveCount(1)->and($byDepartment->targets)->toHaveCount(1);
});

it('deletes drafts only and archives published announcements', function () {
    $actions = app(ManageAnnouncement::class);
    $draft = makeAnnouncement($this->hr, publish: false);
    $published = makeAnnouncement($this->hr);

    $actions->delete($draft, $this->hr);
    expect(Announcement::query()->find($draft->id))->toBeNull();

    expect(fn () => $actions->delete($published, $this->hr))->toThrow(ValidationException::class);

    $actions->archive($published, $this->hr);
    expect($published->refresh()->state()->value)->toBe('archived')
        ->and(fn () => $actions->save(announcementInput(), $this->hr, $published))->toThrow(ValidationException::class);

    $this->assertDatabaseHas('audit_logs', ['event' => 'announcement.published']);
    $this->assertDatabaseHas('audit_logs', ['event' => 'announcement.archived']);
});
