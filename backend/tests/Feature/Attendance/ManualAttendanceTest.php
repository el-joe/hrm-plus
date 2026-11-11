<?php

use App\Actions\Attendance\ManageManualBatch;
use App\Actions\Onboarding\StartOnboarding;
use App\Enums\AttendanceDayStatus;
use App\Enums\AttendanceScope;
use App\Enums\CorrectionStatus;
use App\Enums\ManualBatchStatus;
use App\Enums\OnboardingTaskAction;
use App\Enums\PunchDirection;
use App\Enums\RawEventSource;
use App\Models\AttendanceAdjustment;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceDay;
use App\Models\AttendanceManualBatch;
use App\Models\AttendancePolicyVersion;
use App\Models\AttendanceRawEvent;
use App\Models\AttendanceStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use App\Services\Attendance\Data\RawEventData;
use App\Services\Attendance\RawEventRecorder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

const WORK_DATE = '2026-11-01'; // a Sunday (working day of workWeek())

beforeEach(function () {
    seedRoles();
    seedAttendance();
    attendancePolicy(['manual_entry_requires_approval' => false, 'correction_requires_approval' => false, 'late_grace_minutes' => 10]);

    $this->branch = Branch::factory()->create(['code' => 'CAI', 'timezone' => 'Africa/Cairo']);
    $this->other = Branch::factory()->create(['code' => 'ALX', 'timezone' => 'Africa/Cairo']);
    $this->sales = Department::factory()->create(['code' => 'SAL', 'branch_id' => $this->branch->id]);
    $this->ops = Department::factory()->create(['code' => 'OPS', 'branch_id' => $this->other->id]);
    $this->shift = Shift::factory()->between('08:00', '16:00')->create(['code' => 'MOR']);
    assignSchedule(workWeek($this->shift), AttendanceScope::Company, null);

    $this->staff = collect(range(1, 5))->map(fn (int $n) => attendanceEmployee([
        'employee_number' => 'E-'.$n, 'branch_id' => $this->branch->id, 'department_id' => $this->sales->id,
    ]));
    $this->alexandria = attendanceEmployee(['employee_number' => 'E-ALX', 'branch_id' => $this->other->id, 'department_id' => $this->ops->id]);
    $this->officer = adminWithRoles('Attendance Officer');
});

function statusId(string $code): int
{
    return AttendanceStatus::query()->where('code', $code)->value('id');
}

/**
 * Bulk form payload: everyone selected as present unless overridden.
 *
 * @param  array<int, array<string, mixed>>  $exceptions
 */
function bulkPayload(iterable $employees, array $exceptions = [], array $filters = []): array
{
    $rows = [];

    foreach ($employees as $employee) {
        $rows[$employee->id] = ['selected' => '1', 'action' => 'present', ...($exceptions[$employee->id] ?? [])];
    }

    return ['date' => WORK_DATE, ...$filters, 'employees' => $rows, 'note' => 'Morning roll call'];
}

it('runs scenario 3: officer marks all present except, confirms, and days plus audit history are written', function () {
    [$a, $b, $c, $d, $e] = $this->staff->all();

    // 1. Select a date, branch and shift: only the scheduled employees of that branch are listed.
    $this->actingAs($this->officer, 'admin')
        ->get(route('admin.attendance.bulk.create', ['date' => WORK_DATE, 'branch_id' => $this->branch->id, 'shift_id' => $this->shift->id]))
        ->assertOk()
        ->assertSee('E-1')->assertSee('E-5')->assertDontSee('E-ALX');

    // 2–3. Mark all present except: absent, late with a time, sick leave, one left out.
    $response = $this->post(route('admin.attendance.bulk.store'), bulkPayload([$a, $b, $c, $d, $e], [
        $b->id => ['action' => 'status:'.statusId('absent')],
        $c->id => ['action' => 'late', 'check_in' => '09:20'],
        $d->id => ['action' => 'status:'.statusId('sick_leave'), 'note' => 'Doctor note'],
        $e->id => ['selected' => '0'],
    ], ['branch_id' => $this->branch->id, 'shift_id' => $this->shift->id]));

    $batch = AttendanceManualBatch::query()->sole();
    $response->assertRedirect(route('admin.attendance.batches.show', $batch));

    // Confirmation screen: summary of counts, nothing written yet.
    expect($batch->status)->toBe(ManualBatchStatus::Draft)
        ->and($batch->summary)->toMatchArray(['selected' => 4, 'present' => 1, 'late' => 1, 'statuses' => ['absent' => 1, 'sick_leave' => 1]])
        ->and(AttendanceRawEvent::query()->count())->toBe(0);
    $this->get(route('admin.attendance.batches.show', $batch))->assertOk()->assertSee($batch->reference());

    // 4. Save the batch.
    $this->post(route('admin.attendance.batches.confirm', $batch))->assertRedirect();

    // 5. Records and audit history.
    $days = AttendanceDay::query()->whereDate('work_date', WORK_DATE)->get()->keyBy('employee_id');

    expect($batch->fresh()->status)->toBe(ManualBatchStatus::Applied)
        ->and($days[$a->id]->status)->toBe(AttendanceDayStatus::Present)
        ->and($days[$a->id]->worked_minutes)->toBe(480)
        ->and($days[$a->id]->is_manual)->toBeTrue()
        ->and($days[$a->id]->sources)->toBe(['manual'])
        ->and($days[$b->id]->status)->toBe(AttendanceDayStatus::Absent)
        ->and($days[$c->id]->status)->toBe(AttendanceDayStatus::Late)
        ->and($days[$c->id]->late_minutes)->toBe(80)
        ->and($days[$d->id]->status)->toBe(AttendanceDayStatus::OnLeave)
        ->and($days[$d->id]->attendanceStatus->code)->toBe('sick_leave')
        ->and($days->has($e->id))->toBeFalse()
        ->and(AttendanceRawEvent::query()->where('source', '!=', 'manual')->exists())->toBeFalse();

    $audit = AuditLog::query()->where('event', 'attendance.batch_applied')->sole();
    expect($audit->user_id)->toBe($this->officer->id)
        ->and($audit->meta['summary']['selected'])->toBe(4)
        ->and($audit->meta['work_date'])->toBe(WORK_DATE);

    // Manual entries are marked as manual in the daily view.
    $this->get(route('admin.attendance.daily', ['date' => WORK_DATE]))->assertOk()->assertSee('data-manual-marker', false);

    // Applying the same entries again never duplicates raw events or days.
    app(ManageManualBatch::class)->apply($batch->fresh(), $this->officer);
    expect(AttendanceDay::query()->whereDate('work_date', WORK_DATE)->count())->toBe(4)
        ->and(AttendanceRawEvent::query()->whereNull('voided_at')->count())->toBe(6);
});

it('keeps employees with device attendance as recorded when marking all present', function () {
    [$a, $b] = $this->staff->all();
    punchAt($a, WORK_DATE.' 08:40', 'in');
    punchAt($a, WORK_DATE.' 16:00', 'out');
    processDay($a, WORK_DATE);

    $this->actingAs($this->officer, 'admin')->post(route('admin.attendance.bulk.store'), bulkPayload([$a, $b]));
    $batch = AttendanceManualBatch::query()->sole();

    expect($batch->summary['skipped_existing'])->toBe(1)->and($batch->entries()->pluck('employee_id')->all())->toBe([$b->id]);

    $this->post(route('admin.attendance.batches.confirm', $batch));
    $day = AttendanceDay::query()->where('employee_id', $a->id)->sole();

    expect($day->status)->toBe(AttendanceDayStatus::Late)->and($day->sources)->toBe(['device'])->and($day->is_manual)->toBeFalse();
});

it('lists only scheduled employees matching the filters and the admin data scope', function () {
    $manager = adminWithRoles('Department Manager');

    $this->actingAs($this->officer, 'admin')
        ->get(route('admin.attendance.bulk.create', ['date' => WORK_DATE, 'department_id' => $this->ops->id]))
        ->assertSee('E-ALX')->assertDontSee('E-1');

    // A Friday is a rest day: nobody is listed.
    $this->get(route('admin.attendance.bulk.create', ['date' => '2026-11-06']))->assertSee(__('attendance.bulk.no_eligible'));

    // A Department Manager has no attendance.create; an Attendance Officer restricted to a branch only sees that branch.
    $this->actingAs($manager, 'admin')->get(route('admin.attendance.bulk.create'))->assertForbidden();

    $scopedOfficer = adminWithRoles('Attendance Officer');
    $scopedOfficer->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => $this->other->id]);
    $this->actingAs($scopedOfficer, 'admin')->get(route('admin.attendance.bulk.create', ['date' => WORK_DATE]))->assertSee('E-ALX')->assertDontSee('E-1');

    // An employee outside the scope cannot be smuggled into the batch.
    $this->post(route('admin.attendance.bulk.store'), bulkPayload([$this->staff->first()]))->assertSessionHasErrors();
    expect(AttendanceManualBatch::query()->count())->toBe(0);
});

it('validates exception times and requires at least one selected employee', function () {
    [$a] = $this->staff->all();

    $this->actingAs($this->officer, 'admin')
        ->post(route('admin.attendance.bulk.store'), bulkPayload([$a], [$a->id => ['action' => 'custom_times', 'check_in' => '08:00']]))
        ->assertSessionHasErrors("employees.{$a->id}.check_out");

    $this->post(route('admin.attendance.bulk.store'), bulkPayload([$a], [$a->id => ['selected' => '0']]))->assertSessionHasErrors('employees');
});

it('sends a bulk batch for approval when the policy requires it, and applies it once approved by someone else', function () {
    AttendancePolicyVersion::query()->update(['manual_entry_requires_approval' => true]);
    [$a] = $this->staff->all();

    $this->actingAs($this->officer, 'admin')->post(route('admin.attendance.bulk.store'), bulkPayload([$a]));
    $batch = AttendanceManualBatch::query()->sole();
    $this->post(route('admin.attendance.batches.confirm', $batch));

    expect($batch->fresh()->status)->toBe(ManualBatchStatus::PendingApproval)
        ->and(AttendanceDay::query()->count())->toBe(0);

    // The requester cannot approve their own batch.
    $this->post(route('admin.attendance.batches.decide', [$batch, 'approve']))->assertForbidden();

    $this->actingAs(adminWithRoles('HR Manager'), 'admin')->post(route('admin.attendance.batches.decide', [$batch, 'approve']))->assertRedirect();

    expect($batch->fresh()->status)->toBe(ManualBatchStatus::Applied)
        ->and(AttendanceDay::query()->where('employee_id', $a->id)->sole()->status)->toBe(AttendanceDayStatus::Present);
});

it('applies an admin correction directly, replacing the recorded check-in, when the policy allows', function () {
    $employee = $this->staff->first();
    $wrong = punchAt($employee, WORK_DATE.' 10:00', 'in');
    punchAt($employee, WORK_DATE.' 16:00', 'out');
    processDay($employee, WORK_DATE);

    $this->actingAs($this->officer, 'admin')->post(route('admin.attendance.corrections.store'), [
        'employee_id' => $employee->id, 'work_date' => WORK_DATE, 'shift_id' => $this->shift->id,
        'check_in' => '08:05', 'reason' => 'Device was offline in the morning',
    ])->assertRedirect();

    $correction = AttendanceCorrection::query()->sole();
    $day = AttendanceDay::query()->where('employee_id', $employee->id)->sole();

    expect($correction->status)->toBe(CorrectionStatus::Applied)
        ->and($wrong->fresh()->isVoided())->toBeTrue()
        ->and($day->status)->toBe(AttendanceDayStatus::Present)
        ->and($day->late_minutes)->toBe(0)
        ->and($day->sources)->toBe(['device', 'manual'])
        ->and($day->is_manual)->toBeTrue();
});

it('runs the correction approval flow for an employee request from the portal', function () {
    $employee = $this->staff->first();
    $account = User::factory()->employee()->create(['employee_id' => $employee->id]);
    punchAt($employee, WORK_DATE.' 08:00', 'in');
    processDay($employee, WORK_DATE);

    $this->actingAs($account, 'employee')->post(route('portal.attendance.corrections.store'), [
        'work_date' => WORK_DATE, 'check_out' => '16:10', 'reason' => 'Forgot to punch out',
    ])->assertRedirect();

    $correction = AttendanceCorrection::query()->sole();
    expect($correction->status)->toBe(CorrectionStatus::Pending)
        ->and(AttendanceDay::query()->sole()->status)->toBe(AttendanceDayStatus::Incomplete);

    // The officer sees it in the queue and approves it.
    $this->actingAs($this->officer, 'admin')->get(route('admin.attendance.corrections.index'))->assertOk()->assertSee($employee->employee_number);
    $this->post(route('admin.attendance.corrections.decide', [$correction, 'approve']))->assertRedirect();

    $day = AttendanceDay::query()->sole();
    expect($correction->fresh()->status)->toBe(CorrectionStatus::Applied)
        ->and($day->status)->toBe(AttendanceDayStatus::Present)
        ->and($day->last_out_at->setTimezone('Africa/Cairo')->format('H:i'))->toBe('16:10');

    // A rejected request changes nothing and needs a reason.
    $this->actingAs($account, 'employee')->post(route('portal.attendance.corrections.store'), ['work_date' => '2026-11-02', 'check_in' => '08:00', 'reason' => 'x']);
    $second = AttendanceCorrection::query()->latest('id')->first();
    $this->actingAs($this->officer, 'admin')->post(route('admin.attendance.corrections.decide', [$second, 'reject']))->assertSessionHasErrors('reason');
    $this->post(route('admin.attendance.corrections.decide', [$second, 'reject']), ['reason' => 'No evidence'])->assertRedirect();
    expect($second->fresh()->status)->toBe(CorrectionStatus::Rejected)
        ->and(AttendanceRawEvent::query()->whereDate('work_date', '2026-11-02')->exists())->toBeFalse();
});

it('freezes locked days and records later corrections as adjustments, never overwriting', function () {
    $employee = $this->staff->first();
    punchAt($employee, WORK_DATE.' 08:30', 'in');
    punchAt($employee, WORK_DATE.' 16:00', 'out');
    processDay($employee, WORK_DATE);

    $this->actingAs(adminWithRoles('HR Manager'), 'admin')->post(route('admin.attendance.locks.store'), [
        'start_date' => '2026-11-01', 'end_date' => '2026-11-07',
    ])->assertSessionHasNoErrors();

    $day = AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('work_date', WORK_DATE)->sole();
    expect($day->isLocked())->toBeTrue()->and($day->late_minutes)->toBe(30);

    // Correction after lock: the day row stays, an adjustment holds the difference.
    $this->post(route('admin.attendance.corrections.store'), [
        'employee_id' => $employee->id, 'work_date' => WORK_DATE, 'check_in' => '08:00', 'reason' => 'Late badge reader',
    ]);

    $fresh = $day->fresh();
    $adjustment = AttendanceAdjustment::query()->sole();

    expect($fresh->late_minutes)->toBe(30)
        ->and($fresh->status)->toBe(AttendanceDayStatus::Late)
        ->and($adjustment->status_before)->toBe('late')
        ->and($adjustment->status_after)->toBe('present')
        ->and($adjustment->late_minutes_delta)->toBe(-30)
        ->and($adjustment->worked_minutes_delta)->toBe(30);

    // Reprocessing is safe: no second adjustment.
    processDay($employee, WORK_DATE);
    expect(AttendanceAdjustment::query()->count())->toBe(1);

    // Unlocking supersedes adjustments and recalculates the row.
    $lock = $fresh->lock;
    $this->post(route('admin.attendance.locks.unlock', $lock))->assertRedirect();

    expect($day->fresh()->late_minutes)->toBe(0)->and($day->fresh()->isLocked())->toBeFalse()
        ->and(AttendanceAdjustment::query()->sole()->superseded_at)->not->toBeNull();
});

it('imports attendance from a file with a dry run first, then confirms it as a batch', function () {
    [$a, $b] = $this->staff->all();
    $csv = fn (array $lines) => UploadedFile::fake()->createWithContent('attendance.csv',
        "employee_number,date,shift_code,check_in,check_out,status_code,note\n".implode("\n", $lines)."\n");

    $this->actingAs($this->officer, 'admin');

    // Dry run with errors: nothing is stored.
    $this->post(route('admin.attendance.import.analyse'), ['file' => $csv([
        'E-1,'.WORK_DATE.',,08:00,16:00,,',
        'NOPE,'.WORK_DATE.',,08:00,16:00,,',
        'E-2,'.WORK_DATE.',,25:00,,,',
        'E-2,'.WORK_DATE.',,,,unknown_code,',
    ])])->assertOk()->assertSee('NOPE')->assertSee('unknown_code');
    expect(AttendanceManualBatch::query()->count())->toBe(0);

    // Valid file: dry run, then a draft batch, then confirmation.
    $response = $this->post(route('admin.attendance.import.analyse'), ['file' => $csv([
        'E-1,'.WORK_DATE.',MOR,08:15,16:00,,From paper sheet',
        'E-2,'.WORK_DATE.',,,,official_mission,',
    ])])->assertOk();
    $token = $response->viewData('token');
    expect($response->viewData('report')['valid'])->toBe(2)->and(AttendanceManualBatch::query()->count())->toBe(0);

    $this->post(route('admin.attendance.import.store', $token), ['file_name' => 'attendance.csv']);
    $batch = AttendanceManualBatch::query()->sole();
    expect($batch->source)->toBe(RawEventSource::Import)->and($batch->status)->toBe(ManualBatchStatus::Draft);

    $this->post(route('admin.attendance.batches.confirm', $batch));
    $days = AttendanceDay::query()->get()->keyBy('employee_id');

    expect($days[$a->id]->status)->toBe(AttendanceDayStatus::Late)
        ->and($days[$a->id]->sources)->toBe(['import'])
        ->and($days[$b->id]->status)->toBe(AttendanceDayStatus::Present)
        ->and($days[$b->id]->worked_minutes)->toBe(480)
        ->and($days[$b->id]->attendanceStatus->code)->toBe('official_mission');
});

it('enforces permissions and data scope on the attendance screens', function () {
    $scoped = adminWithRoles('Attendance Officer');
    $scoped->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => $this->other->id]);
    $cairo = processDay($this->staff->first(), WORK_DATE)->sole();
    processDay($this->alexandria, WORK_DATE);

    $this->actingAs(adminWithRoles('Recruiter'), 'admin')->get(route('admin.attendance.daily'))->assertForbidden();
    $this->actingAs(adminWithRoles('Recruiter'), 'admin')->get(route('admin.attendance.locks.index'))->assertForbidden();

    $this->actingAs($scoped, 'admin')->get(route('admin.attendance.daily', ['date' => WORK_DATE]))
        ->assertOk()->assertSee('E-ALX')->assertDontSee('E-1');
    $this->get(route('admin.attendance.days.show', $cairo))->assertNotFound();

    $this->actingAs($this->officer, 'admin')->get(route('admin.attendance.days.show', $cairo))->assertOk()->assertSee(__('attendance.days.raw_events'));
});

it('shows the day breakdown from raw events to calculation, and lets officers void a punch and decide overtime', function () {
    $employee = $this->staff->first();
    $in = punchAt($employee, WORK_DATE.' 07:58', 'in');
    punchAt($employee, WORK_DATE.' 18:00', 'out');
    $day = processDay($employee, WORK_DATE)->sole();

    expect($day->overtime_minutes)->toBe(120);

    $this->actingAs($this->officer, 'admin')->get(route('admin.attendance.days.show', $day))
        ->assertOk()->assertSee(route('admin.attendance.events.void', $in), false)->assertSee(__('attendance.steps.worked_minutes'));

    $this->post(route('admin.attendance.days.overtime', $day), ['decision' => 'approve', 'minutes' => 90])->assertRedirect();
    expect($day->fresh()->overtime_approved_minutes)->toBe(90);

    // The decision survives a recalculation with the same overtime.
    processDay($employee, WORK_DATE);
    expect($day->fresh()->overtime_approved_minutes)->toBe(90);

    $this->post(route('admin.attendance.events.void', $in), ['reason' => 'Wrong person'])->assertRedirect();
    expect($in->fresh()->isVoided())->toBeTrue()->and($day->fresh()->status)->toBe(AttendanceDayStatus::Incomplete)
        ->and(AuditLog::query()->where('event', 'attendance.event_voided')->exists())->toBeTrue();
});

it('runs the server side of scenario 2: a mobile check-in is stored and shown to HR', function () {
    $employee = $this->staff->first();
    Carbon::setTestNow(Carbon::parse(WORK_DATE.' 08:03', 'Africa/Cairo'));

    // Phase 5b validates GPS / face first, then records through the one entry point.
    $event = app(RawEventRecorder::class)->record(RawEventData::punch(
        RawEventSource::MobileGps, $employee->id, now(), PunchDirection::In, 'Africa/Cairo', 'mobile:'.fake()->uuid(),
        sourceReference: 'app 1.0', payload: ['latitude' => 30.0444, 'longitude' => 31.2357, 'accuracy' => 12],
    ));
    Carbon::setTestNow();

    expect($event->fresh()->status->value)->toBe('processed');

    $this->actingAs(adminWithRoles('HR Manager'), 'admin')->get(route('admin.attendance.daily', ['date' => WORK_DATE, 'source' => 'mobile_gps']))
        ->assertOk()->assertSee($employee->employee_number)->assertSee(__('attendance.enums.sources.mobile_gps'));
});

it('processes yesterday from the scheduled command, idempotently', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-02 03:00', 'UTC'));

    $this->artisan('attendance:process')->assertSuccessful();
    $count = AttendanceDay::query()->whereDate('work_date', WORK_DATE)->count();
    $this->artisan('attendance:process')->assertSuccessful();

    expect($count)->toBe(6)
        ->and(AttendanceDay::query()->whereDate('work_date', WORK_DATE)->count())->toBe(6)
        ->and(AttendanceDay::query()->whereDate('work_date', WORK_DATE)->pluck('status')->unique()->all())->toBe([AttendanceDayStatus::Absent]);

    Carbon::setTestNow();
});

it('serves the portal page and the mobile API for the employee only', function () {
    $employee = $this->staff->first();
    $account = User::factory()->employee()->create(['employee_id' => $employee->id]);
    punchAt($employee, WORK_DATE.' 08:00', 'in');
    punchAt($employee, WORK_DATE.' 16:00', 'out');
    processDay($employee, WORK_DATE);
    processDay($this->staff->last(), WORK_DATE);

    $this->actingAs($account, 'employee')->get(route('portal.attendance', ['month' => '2026-11']))
        ->assertOk()->assertSee(__('attendance.portal.my_schedule'));

    $token = $account->createToken('test')->plainTextToken;
    $api = $this->withToken($token);

    $api->getJson('/api/v1/me/schedule?from=2026-11-01&to=2026-11-07')->assertOk()
        ->assertJsonCount(7, 'data')
        ->assertJsonPath('data.0.shifts.0.code', 'MOR')
        ->assertJsonPath('data.0.shifts.0.start_at', '2026-11-01T06:00:00Z')
        ->assertJsonPath('data.5.is_rest_day', true);

    $api->getJson('/api/v1/me/attendance?month=2026-11')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'present')
        ->assertJsonPath('data.0.check_in_local', '08:00')
        ->assertJsonPath('meta.summary.present_days', 1);

    $api->postJson('/api/v1/me/attendance/corrections', ['work_date' => '2026-11-02', 'check_in' => '08:00', 'check_out' => '16:00', 'reason' => 'Phone was off'])
        ->assertCreated()->assertJsonPath('data.status', 'pending');
    $api->postJson('/api/v1/me/attendance/corrections', ['work_date' => '2026-11-02', 'reason' => 'x'])->assertUnprocessable();
    $api->getJson('/api/v1/me/attendance/corrections')->assertOk()->assertJsonCount(1, 'data');

    expect(AttendanceCorrection::query()->sole()->source->value)->toBe('api');

});

it('links the onboarding "assign work schedule" task to the schedule assignment screen', function () {
    seedRecruitment();
    seedAttendance();
    $onboarding = app(StartOnboarding::class)->handle($this->staff->first());
    $task = $onboarding->tasks()->where('title_en', 'Assign the work schedule')->sole();

    expect($task->action)->toBe(OnboardingTaskAction::AssignSchedule);

    $this->actingAs(adminWithRoles('HR Manager'), 'admin')->get(route('admin.onboarding.show', $onboarding))
        ->assertOk()->assertSee(e(route('admin.schedule-assignments.create', ['scope_type' => 'employee', 'scope_id' => $this->staff->first()->id])), false);
});

it('imports a split-shift day without one shift superseding the other', function () {
    $employee = $this->staff->first();
    $morning = Shift::factory()->between('09:00', '13:00')->create(['code' => 'SPA']);
    $evening = Shift::factory()->between('17:00', '21:00')->create(['code' => 'SPB']);
    assignSchedule(weeklySchedule([0 => [$morning, $evening]]), AttendanceScope::Employee, $employee->id);
    $csv = UploadedFile::fake()->createWithContent('split.csv', "employee_number,date,shift_code,check_in,check_out\n"
        .'E-1,'.WORK_DATE.",SPA,09:00,13:00\n".'E-1,'.WORK_DATE.",SPB,17:00,21:00\n");

    $this->actingAs($this->officer, 'admin');
    $token = $this->post(route('admin.attendance.import.analyse'), ['file' => $csv])->viewData('token');
    $this->post(route('admin.attendance.import.store', $token));
    $this->post(route('admin.attendance.batches.confirm', AttendanceManualBatch::query()->sole()));

    $days = AttendanceDay::query()->where('employee_id', $employee->id)->get()->keyBy('shift_id');

    expect($days)->toHaveCount(2)
        ->and($days[$morning->id]->status)->toBe(AttendanceDayStatus::Present)
        ->and($days[$evening->id]->status)->toBe(AttendanceDayStatus::Present)
        ->and(AttendanceRawEvent::query()->whereNotNull('voided_at')->count())->toBe(0);
});
