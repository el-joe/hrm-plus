<?php

use App\Actions\HrRequests\DecideProfileChanges;
use App\Enums\HrProfileChangeStatus;
use App\Enums\HrRequestStatus;
use App\Models\AuditLog;
use App\Models\EmployeeBankAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/HrRequestHelpers.php';

/*
| Profile change requests (Phase 8b): the employee proposes values, HR applies them through the employee
| actions (audited) or rejects them; identity numbers need employee_sensitive.update, bank details
| employee_financials.update.
*/

beforeEach(function () {
    seedHrRequests();
    Notification::fake();

    [$this->employee, $this->user] = hrRequestEmployee(['phone' => '+20 100 000 0001', 'national_id' => '29001011234567']);
    $this->hr = adminWithRoles('HR Manager');
});

it('keeps only real changes, stores old and new values encrypted and masks identity numbers on the portal', function () {
    $request = fileHrRequest($this->employee, $this->user, 'PROFILE_CORRECTION', ['changes' => [
        'phone' => '+20 100 000 0001', 'city' => 'Giza', 'national_id' => '29001011234568',
    ]]);

    expect($request->profileChanges->pluck('field')->all())->toBe(['city', 'national_id']);
    $raw = DB::table('hr_profile_changes')->where('field', 'national_id')->value('new_value');
    expect($raw)->not->toContain('29001011234568');

    $this->actingAs($this->user, 'employee')->get(route('portal.requests.show', $request->id))
        ->assertOk()->assertSee('Giza')->assertDontSee('29001011234568');
});

it('refuses a request without any change and a second open correction', function () {
    expect(fn () => fileHrRequest($this->employee, $this->user, 'PROFILE_CORRECTION', ['changes' => ['phone' => '+20 100 000 0001']]))
        ->toThrow(ValidationException::class);

    fileHrRequest($this->employee, $this->user, 'PROFILE_CORRECTION', ['changes' => ['city' => 'Giza']]);
    expect(fn () => fileHrRequest($this->employee, $this->user, 'PROFILE_CORRECTION', ['changes' => ['city' => 'Cairo']]))
        ->toThrow(ValidationException::class);
});

it('applies the changes through the employee update (audited) and resolves the request', function () {
    $request = fileHrRequest($this->employee, $this->user, 'PROFILE_CORRECTION', ['changes' => ['city' => 'Giza', 'national_id' => '29001011234568']]);

    $this->actingAs($this->hr, 'admin')->post(route('admin.hr-requests.changes.apply', $request))->assertRedirect();

    $employee = $this->employee->refresh();
    expect($employee->city)->toBe('Giza')->and($employee->national_id)->toBe('29001011234568')
        ->and($request->refresh()->status)->toBe(HrRequestStatus::Resolved)
        ->and($request->profileChanges->every(fn ($change) => $change->status === HrProfileChangeStatus::Applied))->toBeTrue();

    $audit = AuditLog::query()->where('auditable_type', 'employee')->where('auditable_id', $employee->id)->latest('id')->first();
    expect($audit)->not->toBeNull()->and(json_encode($audit->new_values))->not->toContain('29001011234568');
});

it('needs the sensitive permission for identity numbers', function () {
    $request = fileHrRequest($this->employee, $this->user, 'PROFILE_CORRECTION', ['changes' => ['national_id' => '29001011234568']]);
    $admin = adminWithPermissions('hr_requests.view', 'hr_requests.update', 'hr_requests.approve', 'employees.view', 'employees.update');

    expect(fn () => app(DecideProfileChanges::class)->apply($request, $admin))->toThrow(ValidationException::class);
    expect($this->employee->refresh()->national_id)->toBe('29001011234567');
});

it('needs the financial permission for bank details and updates the primary account', function () {
    EmployeeBankAccount::query()->create(['employee_id' => $this->employee->id, 'bank_name' => 'Bank Misr', 'account_holder' => 'X', 'iban' => 'EG110001000100000000000000001', 'is_primary' => true]);
    $request = fileHrRequest($this->employee, $this->user, 'PROFILE_CORRECTION', ['changes' => ['bank_iban' => 'EG380019000500000000263180002']]);
    $noFinance = adminWithPermissions('hr_requests.view', 'hr_requests.update', 'hr_requests.approve', 'employees.view', 'employees.update');

    expect(fn () => app(DecideProfileChanges::class)->apply($request, $noFinance))->toThrow(ValidationException::class);

    // HR Manager holds employee_financials.view only; applying bank details needs .update.
    expect(fn () => app(DecideProfileChanges::class)->apply($request->refresh(), $this->hr))->toThrow(ValidationException::class);

    app(DecideProfileChanges::class)->apply($request->refresh(), superAdmin());

    expect($this->employee->bankAccounts()->where('is_primary', true)->first()->iban)->toBe('EG380019000500000000263180002')
        ->and($this->employee->bankAccounts()->count())->toBe(1);
});

it('masks bank values on the request page for admins without financial access', function () {
    $request = fileHrRequest($this->employee, $this->user, 'PROFILE_CORRECTION', ['changes' => [
        'bank_name' => 'CIB', 'bank_account_holder' => 'Mona', 'bank_iban' => 'EG380019000500000000263180002',
    ]]);

    $this->actingAs(adminWithRoles('HR Specialist'), 'admin')->get(route('admin.hr-requests.show', $request))
        ->assertOk()->assertDontSee('EG380019000500000000263180002');
    $this->actingAs($this->hr, 'admin')->get(route('admin.hr-requests.show', $request))
        ->assertOk()->assertSee('EG380019000500000000263180002');
});

it('rejects the changes with a reason without touching the profile', function () {
    $request = fileHrRequest($this->employee, $this->user, 'PROFILE_CORRECTION', ['changes' => ['city' => 'Giza']]);

    $this->actingAs($this->hr, 'admin')->post(route('admin.hr-requests.changes.reject', $request), ['rejection_reason' => 'Send proof of address.'])->assertRedirect();

    expect($this->employee->refresh()->city)->not->toBe('Giza')
        ->and($request->refresh()->status)->toBe(HrRequestStatus::Rejected)
        ->and($request->profileChanges->first()->status)->toBe(HrProfileChangeStatus::Rejected);
});

it('applies the primary emergency contact through the employee details', function () {
    $this->employee->emergencyContacts()->create(['name' => 'Old', 'relationship' => 'Brother', 'phone' => '+20 111 111 1111', 'sort_order' => 0]);
    $request = fileHrRequest($this->employee, $this->user, 'PROFILE_CORRECTION', ['changes' => ['emergency_phone' => '+20 122 222 2222']]);

    app(DecideProfileChanges::class)->apply($request, $this->hr);

    $contact = $this->employee->emergencyContacts()->first();
    expect($contact->name)->toBe('Old')->and($contact->phone)->toBe('+20 122 222 2222');
});
