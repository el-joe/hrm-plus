<?php

use App\Enums\DataScopeType;
use App\Enums\Gender;
use App\Enums\LeaveRequestSource;
use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveUnit;
use App\Models\Branch;
use App\Models\Department;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/*
| Leave screens (Phase 6): admin configuration, requests queue and decisions,
| calendar, balances and reports with permissions and data scope; the portal
| "My leaves"; the mobile API me/leave/*.
*/

beforeEach(function () {
    seedLeave();
    app(SettingsService::class)->set('localisation.default_locale', 'en');
    Storage::fake(config('employees.disk'));
    $this->travelTo(CarbonImmutable::parse('2026-11-01 07:00:00'));
    leaveWorkWeek();
    $this->cairo = Branch::factory()->create(['code' => 'CAI', 'timezone' => 'Africa/Cairo']);
    $this->alex = Branch::factory()->create(['code' => 'ALX', 'timezone' => 'Africa/Cairo']);
    $this->cairoSales = Department::factory()->create(['branch_id' => $this->cairo->id]);
    $this->alexOps = Department::factory()->create(['branch_id' => $this->alex->id]);
    $this->annual = leaveTypeWithPolicy(['code' => 'ANNUAL', 'name_en' => 'Annual leave', 'unit' => LeaveUnit::HalfDay]);
    $this->employee = attendanceEmployee(['employee_number' => 'E-CAI', 'branch_id' => $this->cairo->id, 'department_id' => $this->cairoSales->id, 'gender' => Gender::Male]);
    $this->colleague = attendanceEmployee(['employee_number' => 'E-ALX', 'branch_id' => $this->alex->id, 'department_id' => $this->alexOps->id]);
    $this->account = User::factory()->employee()->create(['employee_id' => $this->employee->id]);
    accrueLeave('2026-01-01');
});

function alexManager(): User
{
    $manager = adminWithRoles('Branch Manager');
    $manager->dataScopes()->create(['scope_type' => DataScopeType::Branch, 'scope_id' => Branch::query()->where('code', 'ALX')->value('id')]);

    return $manager->fresh();
}

describe('configuration', function () {
    it('lets HR manage leave types and refuses viewers and outsiders', function () {
        $payload = [
            'code' => 'sick', 'name_ar' => 'مرضية', 'name_en' => 'Sick leave', 'unit' => 'day', 'min_service_months' => 0, 'notice_days' => 0,
            'approval_workflow' => 'leave_request_hr', 'color' => 'chart-5', 'sort_order' => 20, 'is_paid' => '1', 'tracks_balance' => '1',
            'requires_attachment' => '1', 'attachment_after_days' => '2', 'allows_past_dates' => '1', 'counts_rest_days' => '0', 'counts_holidays' => '0', 'is_active' => '1',
        ];

        $this->actingAs(adminWithRoles('HR Manager'), 'admin')->post(route('admin.leave-types.store'), $payload)->assertRedirect(route('admin.leave-types.index'));
        $type = LeaveType::query()->where('code', 'SICK')->sole();
        expect($type->approval_workflow)->toBe('leave_request_hr')->and($type->attachment_after_days)->toBe('2.00');

        $this->actingAs(adminWithRoles('HR Specialist'), 'admin')->get(route('admin.leave-types.index'))->assertOk()->assertSee('Sick leave');
        $this->get(route('admin.leave-types.create'))->assertForbidden();
        $this->post(route('admin.leave-types.store'), [...$payload, 'code' => 'X'])->assertForbidden();
        $this->actingAs(adminWithRoles('Recruiter'), 'admin')->get(route('admin.leave-types.index'))->assertForbidden();
    });

    it('refuses to delete a leave type in use and rejects an unknown workflow', function () {
        $hr = adminWithRoles('HR Manager');

        $this->actingAs($hr, 'admin')->delete(route('admin.leave-types.destroy', $this->annual))->assertSessionHasErrors('record');
        expect($this->annual->fresh())->not->toBeNull();

        $this->post(route('admin.leave-types.store'), ['code' => 'X1', 'name_ar' => 'س', 'name_en' => 'X', 'unit' => 'day', 'min_service_months' => 0,
            'notice_days' => 0, 'approval_workflow' => 'nope', 'color' => 'chart-1', 'sort_order' => 0])->assertSessionHasErrors('approval_workflow');
    });

    it('saves a new policy version that closes the open one', function () {
        $old = LeavePolicy::query()->sole();

        $this->actingAs(adminWithRoles('HR Manager'), 'admin')->post(route('admin.leave-policies.store'), [
            'code' => 'ann-2027', 'name_ar' => 'اعتيادية ٢٠٢٧', 'name_en' => 'Annual 2027', 'leave_type_id' => $this->annual->id, 'scope_type' => 'company',
            'effective_from' => '2027-01-01', 'entitlement_days' => '15', 'accrual_method' => 'service_tiers',
            'service_tiers' => [['min_years' => '0', 'days' => '15'], ['min_years' => '10', 'days' => '30'], ['min_years' => '', 'days' => '']],
            'carry_over_max_days' => '5', 'carry_over_expiry_months' => '3', 'allow_negative_balance' => '0', 'encashable' => '0', 'is_active' => '1',
        ])->assertRedirect(route('admin.leave-policies.index'));

        $new = LeavePolicy::query()->where('code', 'ANN-2027')->sole();
        expect($old->fresh()->effective_to->format('Y-m-d'))->toBe('2026-12-31')
            ->and($new->tiers())->toBe([['min_years' => 0, 'days' => '15.00'], ['min_years' => 10, 'days' => '30.00']]);

        $this->actingAs(adminWithRoles('Branch Manager'), 'admin')->get(route('admin.leave-policies.index'))->assertForbidden();
    });
});

describe('requests', function () {
    it('shows each manager only the requests of employees in their scope', function () {
        fileLeave($this->employee, $this->annual, '2026-11-08');
        $alexLeave = fileLeave($this->colleague, $this->annual, '2026-11-09');
        $cairoLeave = LeaveRequest::query()->where('employee_id', $this->employee->id)->sole();
        $manager = alexManager();

        $this->actingAs($manager, 'admin')->get(route('admin.leave-requests.index', ['mine' => 1]))
            ->assertOk()->assertSee('E-ALX')->assertDontSee('E-CAI');
        $this->get(route('admin.leave-requests.show', $cairoLeave))->assertNotFound();
        $this->post(route('admin.leave-requests.decide', [$cairoLeave, 'approve']))->assertNotFound();

        $this->post(route('admin.leave-requests.decide', [$alexLeave, 'approve']))->assertRedirect();
        expect($alexLeave->fresh()->latestApprovalRequest->currentStep->sequence)->toBe(2);

        // The branch manager may not take the HR step.
        $this->post(route('admin.leave-requests.decide', [$alexLeave, 'approve']))->assertForbidden();
    });

    it('rejects from the queue with a mandatory reason', function () {
        $leave = fileLeave($this->colleague, $this->annual, '2026-11-09');
        $this->actingAs(alexManager(), 'admin');

        $this->post(route('admin.leave-requests.decide', [$leave, 'reject']))->assertSessionHasErrors('reason');
        $this->post(route('admin.leave-requests.decide', [$leave, 'reject']), ['reason' => 'Audit week'])->assertRedirect();

        expect($leave->fresh())->status->toBe(LeaveRequestStatus::Rejected)->rejection_reason->toBe('Audit week');
    });

    it('files leave on an employee\'s behalf with attachments, or records it as approved for HR', function () {
        $sick = leaveTypeWithPolicy(['requires_attachment' => true], ['entitlement_days' => '15']);
        accrueLeave('2026-01-01');
        $specialist = adminWithRoles('HR Specialist');

        $this->actingAs($specialist, 'admin')->get(route('admin.leave-requests.create', ['employee_id' => $this->employee->id]))->assertOk()->assertSee('Annual leave');
        $this->post(route('admin.leave-requests.store'), [
            'employee_id' => $this->employee->id, 'leave_type_id' => $sick->id, 'start_date' => '2026-10-28', 'reason' => 'Flu',
            'attachments' => [UploadedFile::fake()->create('note.pdf', 40, 'application/pdf')],
        ])->assertRedirect();
        $filed = LeaveRequest::query()->where('leave_type_id', $sick->id)->sole();
        expect($filed->status)->toBe(LeaveRequestStatus::Pending)->and($filed->attachments()->count())->toBe(1);

        $this->get(route('admin.leave-requests.attachments.download', [$filed, $filed->attachments()->value('id')]))->assertOk();

        $this->post(route('admin.leave-requests.store'), [
            'employee_id' => $this->employee->id, 'leave_type_id' => $this->annual->id, 'start_date' => '2026-11-08', 'record_as_approved' => '1',
        ])->assertForbidden();

        $this->actingAs(adminWithRoles('HR Manager'), 'admin')->post(route('admin.leave-requests.store'), [
            'employee_id' => $this->employee->id, 'leave_type_id' => $this->annual->id, 'start_date' => '2026-11-08', 'end_date' => '2026-11-09', 'record_as_approved' => '1',
        ])->assertRedirect();
        expect(LeaveRequest::query()->where('leave_type_id', $this->annual->id)->sole()->status)->toBe(LeaveRequestStatus::Approved);
    });

    it('lets HR cancel approved leave and refuses others', function () {
        $leave = fileLeave($this->employee, $this->annual, '2026-11-08', null, by: adminWithRoles('HR Manager'), recordAsApproved: true);

        $this->actingAs(adminWithRoles('Direct Manager'), 'admin')->post(route('admin.leave-requests.cancel', $leave), ['reason' => 'x'])->assertForbidden();
        $this->actingAs(adminWithRoles('HR Manager'), 'admin')->post(route('admin.leave-requests.cancel', $leave), ['reason' => 'Booked twice'])->assertRedirect();

        expect($leave->fresh()->status)->toBe(LeaveRequestStatus::Cancelled);
    });

    it('renders the calendar, balances, reports and the employee leave tab inside the scope', function () {
        fileLeave($this->colleague, $this->annual, '2026-11-09');
        fileLeave($this->employee, $this->annual, '2026-11-10');
        $this->actingAs(alexManager(), 'admin');

        $this->get(route('admin.leave-calendar', ['month' => '2026-11']))->assertOk()->assertSee('E-ALX')->assertDontSee('E-CAI');
        $this->get(route('admin.leave-balances.index'))->assertOk()->assertSee('E-ALX')->assertDontSee('E-CAI');
        $this->get(route('admin.leave-balances.show', $this->colleague))->assertOk()->assertSee('21.00');
        $this->get(route('admin.leave-balances.show', $this->employee))->assertNotFound();
        $this->get(route('admin.employees.tab', [$this->colleague, 'leave']))->assertOk()->assertSee('Annual leave');

        foreach (['balances', 'requests', 'usage_by_type', 'usage_by_department', 'upcoming', 'on_leave'] as $report) {
            $this->get(route('admin.leave-reports.show', $report))->assertOk();
        }

        $export = $this->get(route('admin.leave-reports.export', ['requests', 'format' => 'csv', 'from' => '2026-11-01', 'to' => '2026-11-30']));
        $export->assertOk();
        $csv = file_get_contents($export->baseResponse->getFile()->getPathname());
        expect($csv)->toContain('E-ALX')->not->toContain('E-CAI');

        $this->actingAs(adminWithRoles('Recruiter'), 'admin')->get(route('admin.leave-calendar'))->assertForbidden();
        $this->get(route('admin.leave-reports.index'))->assertForbidden(); // reports.view alone no longer opens leave reports (Phase 11)
        $this->actingAs(adminWithRoles('Reports Viewer'), 'admin')->get(route('admin.leave-reports.index'))->assertOk(); // reports.manage
        $this->actingAs(adminWithPermissions('dashboard.view'), 'admin')->get(route('admin.leave-reports.index'))->assertForbidden();
    });
});

describe('portal', function () {
    it('shows my balances and requests, and sends a request', function () {
        fileLeave($this->colleague, $this->annual, '2026-11-09'); // someone else's

        $this->actingAs($this->account, 'employee')->get(route('portal.leaves'))
            ->assertOk()->assertSee('Annual leave')->assertSee('21.00');

        $this->post(route('portal.leaves.store'), [
            'leave_type_id' => $this->annual->id, 'start_date' => '2026-11-08', 'end_date' => '2026-11-10', 'reason' => 'Family visit',
        ])->assertRedirect(route('portal.leaves'));

        $leave = LeaveRequest::query()->withoutGlobalScopes()->where('employee_id', $this->employee->id)->sole();
        expect($leave->source->value)->toBe('portal')->and($leave->requested_by)->toBe($this->account->id)->and($leave->days)->toBe('3.00');

        $this->get(route('portal.leaves'))->assertSee('18.00');
    });

    it('cancels my own request but never another employee\'s', function () {
        $mine = fileLeave($this->employee, $this->annual, '2026-11-08', by: $this->account, source: LeaveRequestSource::Portal);
        $theirs = fileLeave($this->colleague, $this->annual, '2026-11-09');

        $this->actingAs($this->account, 'employee')->post(route('portal.leaves.cancel', $theirs->id))->assertNotFound();
        $this->post(route('portal.leaves.cancel', $mine->id))->assertRedirect();

        expect($mine->fresh()->status)->toBe(LeaveRequestStatus::Cancelled)->and($theirs->fresh()->status)->toBe(LeaveRequestStatus::Pending);
    });

    it('validates the request and keeps business-rule errors on the form', function () {
        $this->actingAs($this->account, 'employee')->from(route('portal.leaves'))->post(route('portal.leaves.store'), ['start_date' => 'x'])
            ->assertSessionHasErrors(['leave_type_id', 'start_date']);

        $this->from(route('portal.leaves'))->post(route('portal.leaves.store'), ['leave_type_id' => $this->annual->id, 'start_date' => '2026-11-06'])
            ->assertSessionHasErrors('end_date'); // Friday: no working day
    });
});

describe('api', function () {
    it('lists types, balances and own requests', function () {
        leaveTypeWithPolicy(['code' => 'MATERNITY', 'gender' => Gender::Female], ['entitlement_days' => '90']);
        fileLeave($this->employee, $this->annual, '2026-11-08');
        fileLeave($this->colleague, $this->annual, '2026-11-09');
        Sanctum::actingAs($this->account);

        $this->getJson(route('api.v1.me.leave.types'))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'ANNUAL')->assertJsonPath('data.0.allows_half_day', true);

        $this->getJson(route('api.v1.me.leave.balances'))->assertOk()
            ->assertJsonPath('meta.year', 2026)->assertJsonPath('data.0.available', '20.00')->assertJsonPath('data.0.pending', '1.00');

        $this->getJson(route('api.v1.me.leave.requests.index'))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'pending')->assertJsonPath('meta.pagination.total', 1);
    });

    it('previews, submits with an attachment, shows and cancels a request', function () {
        Sanctum::actingAs($this->account);

        $this->postJson(route('api.v1.me.leave.requests.preview'), ['leave_type_id' => $this->annual->id, 'start_date' => '2026-11-05', 'end_date' => '2026-11-09'])
            ->assertOk()->assertJsonPath('data.total', '3.00')->assertJsonPath('data.available_after.2026', '18.00');

        $response = $this->post(route('api.v1.me.leave.requests.store'), [
            'leave_type_id' => $this->annual->id, 'start_date' => '2026-11-08', 'day_part' => 'first_half',
            'attachments' => [UploadedFile::fake()->create('ticket.pdf', 20, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.days', '0.50')->assertJsonPath('data.current_step', 'Manager approval');

        $id = $response->json('data.id');
        $attachment = $response->json('data.attachments.0.id');

        $this->getJson(route('api.v1.me.leave.requests.show', $id))->assertOk()->assertJsonPath('data.day_breakdown.0.amount', '0.50');
        $this->get(route('api.v1.me.leave.requests.attachments.show', [$id, $attachment]))->assertOk();
        $this->postJson(route('api.v1.me.leave.requests.cancel', $id))->assertOk()->assertJsonPath('data.status', 'cancelled');
    });

    it('refuses invalid and overlapping requests with the API error envelope', function () {
        fileLeave($this->employee, $this->annual, '2026-11-08');
        Sanctum::actingAs($this->account);

        $this->postJson(route('api.v1.me.leave.requests.store'), ['leave_type_id' => $this->annual->id, 'start_date' => '2026-11-08'])
            ->assertUnprocessable()->assertJsonPath('success', false)->assertJsonPath('code', 'validation_error')->assertJsonValidationErrors('start_date');
    });

    it('never exposes another employee\'s request and refuses admin tokens', function () {
        $theirs = fileLeave($this->colleague, $this->annual, '2026-11-09');
        Sanctum::actingAs($this->account);

        $this->getJson(route('api.v1.me.leave.requests.show', $theirs->id))->assertNotFound();
        $this->postJson(route('api.v1.me.leave.requests.cancel', $theirs->id))->assertNotFound();

        Sanctum::actingAs(adminWithRoles('HR Manager'));
        $this->getJson(route('api.v1.me.leave.balances'))->assertForbidden();
    });
});
