<?php

use App\Actions\HrRequests\ManageHrRequest;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\HrRequest;
use App\Models\HrRequestCategory;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/HrRequestHelpers.php';

/*
| Admin access to HR requests (Phase 8b): permissions, data scope, the "assigned / unassigned" rule
| without hr_requests.manage, own requests, configuration screens, export.
*/

beforeEach(function () {
    seedHrRequests();
    Notification::fake();

    [$this->employee, $this->user] = hrRequestEmployee();
    $this->request = fileHrRequest($this->employee, $this->user);
});

it('shows the queue and the request to HR', function () {
    $hr = adminWithRoles('HR Manager');

    $this->actingAs($hr, 'admin')->get(route('admin.hr-requests.index'))->assertOk()->assertSee($this->request->reference);
    $this->actingAs($hr, 'admin')->get(route('admin.hr-requests.show', $this->request))->assertOk();
});

it('refuses admins without the permission', function () {
    $recruiter = adminWithRoles('Recruiter');

    $this->actingAs($recruiter, 'admin')->get(route('admin.hr-requests.index'))->assertForbidden();
    $this->actingAs($recruiter, 'admin')->get(route('admin.hr-requests.show', $this->request))->assertForbidden();
    $this->actingAs($recruiter, 'admin')->get(route('admin.hr-request-categories.index'))->assertForbidden();
});

it('limits admins without manage to their own and unassigned requests', function () {
    $specialist = adminWithRoles('HR Specialist');
    $colleague = adminWithRoles('HR Specialist');
    app(ManageHrRequest::class)->assign($this->request, $colleague, adminWithRoles('HR Manager'));

    $this->actingAs($specialist, 'admin')->get(route('admin.hr-requests.show', $this->request))->assertForbidden();
    $this->actingAs($specialist, 'admin')->get(route('admin.hr-requests.index'))->assertOk()->assertDontSee($this->request->reference);
    $this->actingAs($colleague, 'admin')->get(route('admin.hr-requests.show', $this->request))->assertOk();
});

it('lets hr_requests.update take a request but only manage hand it to someone else', function () {
    $specialist = adminWithRoles('HR Specialist');
    $other = adminWithRoles('HR Specialist');

    $this->actingAs($specialist, 'admin')->post(route('admin.hr-requests.assign', $this->request), ['assigned_to' => $other->id])->assertForbidden();
    $this->actingAs($specialist, 'admin')->post(route('admin.hr-requests.assign', $this->request), ['assigned_to' => $specialist->id])->assertRedirect();

    expect($this->request->refresh()->assigned_to)->toBe($specialist->id);
});

it('hides requests outside the admin\'s data scope', function () {
    $branch = Branch::factory()->create();
    $scoped = adminWithRoles('HR Manager');
    $scoped->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => $branch->id]);

    $this->actingAs($scoped, 'admin')->get(route('admin.hr-requests.show', $this->request))->assertNotFound();
});

it('never shows an admin a request about themselves', function () {
    // One account per employee (users.employee_id is unique): the admin account replaces the portal one.
    $this->user->forceFill(['employee_id' => null])->save();
    $hr = adminWithRoles('HR Manager');
    $hr->update(['employee_id' => $this->employee->id]);

    $this->actingAs($hr, 'admin')->get(route('admin.hr-requests.show', $this->request))->assertForbidden();
});

it('files a request on behalf of an employee', function () {
    $hr = adminWithRoles('HR Manager');
    $employee = Employee::factory()->create();

    $this->actingAs($hr, 'admin')->post(route('admin.hr-requests.store'), [
        'employee_id' => $employee->id, 'hr_request_category_id' => hrCategory('LEAVE_INQUIRY')->id, 'description' => 'Asked by phone.', 'priority' => 'urgent',
    ])->assertRedirect();

    $request = HrRequest::query()->where('employee_id', $employee->id)->firstOrFail();
    expect($request->source->value)->toBe('admin')->and($request->priority->value)->toBe('urgent');
});

it('manages categories with extra fields', function () {
    $hr = adminWithRoles('HR Manager');

    $this->actingAs($hr, 'admin')->post(route('admin.hr-request-categories.store'), [
        'code' => 'bank_letter', 'name_ar' => 'خطاب للبنك', 'name_en' => 'Bank letter', 'type' => 'general', 'default_priority' => 'normal', 'is_active' => 1,
        'fields' => [['key' => 'bank', 'label_ar' => 'البنك', 'label_en' => 'Bank', 'type' => 'select', 'options' => "CIB\nNBE", 'is_required' => 1]],
    ])->assertRedirect(route('admin.hr-request-categories.index'));

    $category = HrRequestCategory::query()->where('code', 'BANK_LETTER')->firstOrFail();
    expect($category->fields->first()->options)->toBe(['CIB', 'NBE'])->and($category->fields->first()->is_required)->toBeTrue();

    $this->actingAs($hr, 'admin')->delete(route('admin.hr-request-categories.destroy', hrCategory('GENERAL_INQUIRY')))->assertSessionHasErrors('record');
    $this->actingAs($hr, 'admin')->delete(route('admin.hr-request-categories.destroy', $category))->assertRedirect();
});

it('exports the queue for admins with the export permission only', function () {
    $this->actingAs(adminWithRoles('HR Manager'), 'admin')->get(route('admin.hr-requests.export', ['format' => 'csv']))->assertOk()->assertDownload();
    $this->actingAs(adminWithRoles('HR Specialist'), 'admin')->get(route('admin.hr-requests.export'))->assertForbidden();
});

it('records internal notes and replies from the request page', function () {
    $hr = adminWithRoles('HR Manager');

    $this->actingAs($hr, 'admin')->post(route('admin.hr-requests.messages.store', $this->request), ['body' => 'Internal remark', 'visibility' => 'internal'])->assertRedirect();
    $this->actingAs($hr, 'admin')->post(route('admin.hr-requests.messages.store', $this->request), ['body' => 'Hello Sara', 'visibility' => 'reply'])->assertRedirect();

    expect($this->request->messages()->pluck('type')->map->value->all())->toBe(['internal_note', 'reply']);
});
