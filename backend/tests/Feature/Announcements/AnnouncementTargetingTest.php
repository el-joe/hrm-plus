<?php

use App\Actions\Announcements\ManageAnnouncement;
use App\Models\Announcement;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeGroup;
use App\Models\EmploymentType;
use App\Services\Announcements\AnnouncementAudience;
use App\Services\Announcements\AnnouncementFeed;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/AnnouncementHelpers.php';

/*
| Who sees an announcement (Phase 8b): everyone, or employees matching ANY target — branch,
| department with its sub-departments, employee group, employment type — and only while it is
| published and inside its publish window.
*/

beforeEach(function () {
    seedRoles();
    Notification::fake();
    $this->hr = adminWithRoles('HR Manager');

    $this->cairo = Branch::factory()->create(['code' => 'CAI']);
    $this->alex = Branch::factory()->create(['code' => 'ALX']);
    $this->sales = Department::factory()->create(['code' => 'SAL', 'branch_id' => $this->cairo->id]);
    $this->salesCairo = Department::factory()->create(['code' => 'SAL-CAI', 'parent_id' => $this->sales->id, 'branch_id' => $this->cairo->id]);
    $this->it = Department::factory()->create(['code' => 'IT', 'branch_id' => $this->cairo->id]);
    $this->interns = EmploymentType::factory()->create(['code' => 'INT']);
    $this->fullTime = EmploymentType::factory()->create(['code' => 'FT']);
    $this->field = EmployeeGroup::factory()->create(['code' => 'FIELD']);

    $this->salesRep = Employee::factory()->create(['branch_id' => $this->cairo->id, 'department_id' => $this->salesCairo->id, 'employment_type_id' => $this->fullTime->id]);
    $this->engineer = Employee::factory()->create(['branch_id' => $this->cairo->id, 'department_id' => $this->it->id, 'employment_type_id' => $this->interns->id]);
    $this->storekeeper = Employee::factory()->create(['branch_id' => $this->alex->id, 'department_id' => Department::factory()->create(['branch_id' => $this->alex->id])->id, 'employment_type_id' => $this->fullTime->id]);
    $this->storekeeper->groups()->attach($this->field->id, ['created_at' => now()]);
});

function visibleIds(Employee $employee): array
{
    return Announcement::query()->visibleTo($employee)->pluck('id')->all();
}

it('shows a company-wide announcement to everyone', function () {
    $announcement = makeAnnouncement($this->hr);

    foreach ([$this->salesRep, $this->engineer, $this->storekeeper] as $employee) {
        expect(visibleIds($employee))->toContain($announcement->id);
    }
});

it('targets a branch', function () {
    $announcement = makeAnnouncement($this->hr, ['audience' => 'targeted', 'targets' => ['branch' => [$this->alex->id]]]);

    expect(visibleIds($this->storekeeper))->toContain($announcement->id)
        ->and(visibleIds($this->salesRep))->not->toContain($announcement->id)
        ->and(visibleIds($this->engineer))->not->toContain($announcement->id);
});

it('includes the sub-departments of a targeted department', function () {
    $announcement = makeAnnouncement($this->hr, ['audience' => 'targeted', 'targets' => ['department' => [$this->sales->id]]]);

    expect(visibleIds($this->salesRep))->toContain($announcement->id)
        ->and(visibleIds($this->engineer))->not->toContain($announcement->id)
        ->and(app(AnnouncementAudience::class)->employees($announcement)->pluck('employees.id')->all())->toBe([$this->salesRep->id]);
});

it('targets an employee group and an employment type (any target matches)', function () {
    $announcement = makeAnnouncement($this->hr, ['audience' => 'targeted', 'targets' => [
        'employee_group' => [$this->field->id], 'employment_type' => [$this->interns->id],
    ]]);

    expect(visibleIds($this->storekeeper))->toContain($announcement->id)
        ->and(visibleIds($this->engineer))->toContain($announcement->id)
        ->and(visibleIds($this->salesRep))->not->toContain($announcement->id)
        ->and(app(AnnouncementAudience::class)->count($announcement))->toBe(2);
});

it('leaves terminated and inactive employees out of the audience', function () {
    $announcement = makeAnnouncement($this->hr);
    Employee::factory()->terminated()->create();
    Employee::factory()->inactive()->create();

    expect(app(AnnouncementAudience::class)->count($announcement))->toBe(3);
});

it('hides drafts, scheduled, expired and archived announcements', function () {
    $draft = makeAnnouncement($this->hr, publish: false);
    $scheduled = makeAnnouncement($this->hr, ['publish_at' => now()->addDay()->toDateTimeString()]);
    $expired = Announcement::factory()->published(now()->subDays(5)->toDateTimeString())->create(['expires_at' => now()->subDay()]);
    $archived = makeAnnouncement($this->hr);
    app(ManageAnnouncement::class)->archive($archived, $this->hr);
    $live = makeAnnouncement($this->hr);

    expect(visibleIds($this->salesRep))->toBe([$live->id])
        ->and($draft->refresh()->state()->value)->toBe('draft')
        ->and($scheduled->refresh()->state()->value)->toBe('scheduled')
        ->and($expired->state()->value)->toBe('expired')
        ->and($archived->refresh()->state()->value)->toBe('archived');

    $this->travel(2)->days();

    expect(visibleIds($this->salesRep))->toContain($scheduled->id);
});

it('lists pinned announcements first and counts unread ones', function () {
    $older = makeAnnouncement($this->hr, ['is_pinned' => true]);
    $this->travel(1)->hours();
    $newer = makeAnnouncement($this->hr);
    $feed = app(AnnouncementFeed::class);

    expect($feed->forEmployee($this->salesRep)->pluck('id')->all())->toBe([$older->id, $newer->id])
        ->and($feed->unreadCount($this->salesRep))->toBe(2);

    app(ManageAnnouncement::class)->markRead($newer, $this->salesRep);

    expect($feed->unreadCount($this->salesRep))->toBe(1)
        ->and($feed->forEmployee($this->salesRep, unreadOnly: true)->pluck('id')->all())->toBe([$older->id]);
});
