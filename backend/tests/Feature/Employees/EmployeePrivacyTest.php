<?php

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\JobGrade;
use App\Services\Employees\SensitiveDataAccessLog;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->employee = Employee::factory()->create(['national_id' => '29001011234567', 'passport_number' => 'A12345678']);
});

it('stores identity numbers and bank details encrypted, with a keyed hash for lookups', function () {
    $raw = DB::table('employees')->where('id', $this->employee->id)->first();

    expect($raw->national_id)->not->toContain('29001011234567')
        ->and($raw->national_id_hash)->toHaveLength(64)
        ->and($raw->national_id_hash)->not->toContain('29001011234567')
        ->and($this->employee->fresh()->national_id)->toBe('29001011234567');

    $account = $this->employee->bankAccounts()->create(['bank_name' => 'NBE', 'account_holder' => 'X', 'iban' => 'eg38 0019 0005 0000 0000 2631 8000 2']);
    $rawAccount = DB::table('employee_bank_accounts')->where('id', $account->id)->first();

    expect($rawAccount->iban)->not->toContain('EG38')
        ->and(EmployeeBankAccount::query()->find($account->id)->iban)->toBe('EG380019000500000000263180002');
});

it('masks identity numbers and logs every reveal', function () {
    $admin = adminWithPermissions('employees.view', 'employee_sensitive.view');

    $this->actingAs($admin, 'admin')->get("/admin/employees/{$this->employee->id}")
        ->assertOk()->assertDontSee('29001011234567')->assertSee('4567');

    expect(AuditLog::query()->where('event', SensitiveDataAccessLog::IDENTITY)->count())->toBe(0);

    $this->actingAs($admin, 'admin')->get("/admin/employees/{$this->employee->id}?reveal=1")
        ->assertOk()->assertSee('29001011234567')->assertSee('A12345678');

    $log = AuditLog::query()->where('event', SensitiveDataAccessLog::IDENTITY)->sole();
    expect($log->user_id)->toBe($admin->id)
        ->and($log->auditable_id)->toBe($this->employee->id)
        ->and($log->meta['fields'])->toBe(['national_id', 'passport_number'])
        ->and(json_encode($log->toArray()))->not->toContain('29001011234567');
});

it('never reveals identity numbers without employee_sensitive.view', function () {
    $this->actingAs(adminWithPermissions('employees.view'), 'admin')->get("/admin/employees/{$this->employee->id}?reveal=1")
        ->assertOk()->assertDontSee('29001011234567')->assertDontSee('A12345678');

    expect(AuditLog::query()->where('event', SensitiveDataAccessLog::IDENTITY)->exists())->toBeFalse();
});

it('keeps the audit trail of employee changes free of the protected values', function () {
    $this->actingAs(superAdmin(), 'admin')->put("/admin/employees/{$this->employee->id}", [
        'employee_number' => $this->employee->employee_number, 'name_ar' => 'س', 'name_en' => 'S', 'national_id' => '28512121234567',
    ])->assertSessionHasNoErrors();

    $log = AuditLog::query()->where('auditable_type', 'employee')->where('event', 'updated')->latest('id')->first();
    expect($log->new_values['national_id'])->toBe('[REDACTED]')
        ->and(AuditLog::query()->get()->toJson())->not->toContain('28512121234567');
});

it('drops identity numbers sent by an editor without employee_sensitive.update', function () {
    $this->actingAs(adminWithPermissions('employees.view', 'employees.update', 'employee_sensitive.view'), 'admin')
        ->put("/admin/employees/{$this->employee->id}", [
            'employee_number' => $this->employee->employee_number, 'name_ar' => 'س', 'name_en' => 'S', 'national_id' => '28512121234567',
        ])->assertSessionHasNoErrors();

    expect($this->employee->fresh()->national_id)->toBe('29001011234567');
});

it('guards the financial tab with employee_financials.view and logs each view', function () {
    $this->actingAs(adminWithPermissions('employees.view'), 'admin')
        ->get("/admin/employees/{$this->employee->id}/tab/financial")->assertForbidden();

    $finance = adminWithPermissions('employees.view', 'employee_financials.view');
    $this->actingAs($finance, 'admin')->get("/admin/employees/{$this->employee->id}/tab/financial")->assertOk();

    expect(AuditLog::query()->where('event', SensitiveDataAccessLog::FINANCIAL)->where('user_id', $finance->id)->count())->toBe(1);
});

it('hides salary rows from the history of non-financial viewers', function () {
    $this->actingAs(superAdmin(), 'admin')->post("/admin/employees/{$this->employee->id}/salaries", [
        'basic_salary' => '12345.67', 'effective_date' => today()->toDateString(), 'reason' => 'Annual review',
    ])->assertSessionHasNoErrors();

    $this->actingAs(adminWithPermissions('employees.view'), 'admin')
        ->get("/admin/employees/{$this->employee->id}/tab/history")->assertOk()->assertDontSee('12,345.67');
    $this->actingAs(adminWithPermissions('employees.view', 'employee_financials.view'), 'admin')
        ->get("/admin/employees/{$this->employee->id}/tab/history")->assertOk()->assertSee('12,345.67');
});

it('requires employee_financials.update to change a salary or a bank account', function () {
    $admin = adminWithPermissions('employees.view', 'employees.update', 'employee_financials.view');

    $this->actingAs($admin, 'admin')->post("/admin/employees/{$this->employee->id}/salaries", [
        'basic_salary' => '100', 'effective_date' => today()->toDateString(), 'reason' => 'x',
    ])->assertForbidden();
    $this->actingAs($admin, 'admin')->post("/admin/employees/{$this->employee->id}/bank-accounts", [
        'bank_name' => 'NBE', 'account_holder' => 'X', 'account_number' => '123',
    ])->assertForbidden();
});

it('shows job-grade salary bands only with the financial permission', function () {
    JobGrade::factory()->create(['name_en' => 'Band grade', 'min_salary' => '12345.00', 'max_salary' => '23456.00']);

    $this->actingAs(adminWithPermissions('job_grades.view'), 'admin')->get('/admin/job-grades')->assertOk()->assertDontSee('12,345.00');
    $this->actingAs(adminWithPermissions('job_grades.view', 'employee_financials.view'), 'admin')->get('/admin/job-grades')->assertOk()->assertSee('12,345.00');
});

it('ignores salary bands submitted without the financial permission', function () {
    $grade = JobGrade::factory()->create(['min_salary' => '1000.00', 'max_salary' => '2000.00']);

    $this->actingAs(adminWithPermissions('job_grades.view', 'job_grades.update'), 'admin')
        ->put("/admin/job-grades/{$grade->id}", [...$grade->only(['code', 'name_ar', 'name_en', 'level']), 'min_salary' => '1', 'max_salary' => '2'])
        ->assertSessionHasNoErrors();

    expect($grade->fresh()->min_salary)->toBe('1000.00');
});
