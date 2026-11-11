<?php

use App\Enums\AccountType;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Services\Employees\SensitiveDataAccessLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('employees.disk'));
    $this->cairo = Branch::factory()->create();
    $this->alex = Branch::factory()->create();
    $this->employee = Employee::factory()->inDepartment(Department::factory()->create(['branch_id' => $this->cairo->id]))->create();
    $this->outsider = Employee::factory()->inDepartment(Department::factory()->create(['branch_id' => $this->alex->id]))->create();
});

function uploadDocument(Employee $employee, array $overrides = []): EmployeeDocument
{
    test()->actingAs(superAdmin(), 'admin')->post("/admin/employees/{$employee->id}/documents", [
        'category' => 'passport',
        'title' => 'Passport copy',
        'expiry_date' => now()->addYear()->toDateString(),
        'file' => UploadedFile::fake()->create('passport.pdf', 120, 'application/pdf'),
        ...$overrides,
    ])->assertSessionHasNoErrors();

    return EmployeeDocument::query()->latest('id')->firstOrFail();
}

it('stores uploads on the private disk under a random name', function () {
    $document = uploadDocument($this->employee);

    Storage::disk(config('employees.disk'))->assertExists($document->file_path);
    expect($document->file_path)->toStartWith($this->employee->id.'/documents/')
        ->and($document->file_path)->not->toContain('passport')
        ->and($document->file_name)->toBe('passport.pdf')
        ->and(config('filesystems.disks.'.config('employees.disk').'.root'))->not->toContain('public');
});

it('rejects file types that are not allowed by the documents settings', function () {
    $this->actingAs(superAdmin(), 'admin')->post("/admin/employees/{$this->employee->id}/documents", [
        'category' => 'cv', 'title' => 'Script', 'file' => UploadedFile::fake()->create('run.php', 1, 'application/x-php'),
    ])->assertSessionHasErrors('file');

    expect(EmployeeDocument::query()->count())->toBe(0);
});

it('serves a document only through the authorised controller and logs the download', function () {
    $document = uploadDocument($this->employee);
    $hr = adminWithPermissions('employees.view', 'employee_documents.view');

    $this->actingAs($hr, 'admin')->get("/admin/employees/{$this->employee->id}/documents/{$document->id}/download")
        ->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertDownload('passport.pdf');

    expect(AuditLog::query()->where('event', SensitiveDataAccessLog::DOCUMENT)->where('user_id', $hr->id)->where('auditable_id', $document->id)->exists())->toBeTrue();
});

it('refuses downloads without employee_documents.view, outside the scope or through another employee', function () {
    $document = uploadDocument($this->employee);
    $restricted = adminWithPermissions('employees.view', 'employee_documents.view');
    $restricted->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => $this->alex->id]);

    $this->actingAs(adminWithPermissions('employees.view'), 'admin')
        ->get("/admin/employees/{$this->employee->id}/documents/{$document->id}/download")->assertForbidden();
    $this->actingAs($restricted->fresh(), 'admin')
        ->get("/admin/employees/{$this->employee->id}/documents/{$document->id}/download")->assertNotFound();
    $this->actingAs(superAdmin(), 'admin')
        ->get("/admin/employees/{$this->outsider->id}/documents/{$document->id}/download")->assertNotFound();
});

it('does not expose the files under any public URL', function () {
    $document = uploadDocument($this->employee);

    // The framework's local-disk route only answers signed URLs; nothing is ever served unauthenticated.
    expect($this->get('/storage/'.$document->file_path)->status())->toBeIn([403, 404])
        ->and($this->get('/storage/employees/'.$document->file_path)->status())->toBeIn([403, 404])
        ->and($this->get('/employees/'.$document->file_path)->status())->toBe(404);
});

it('soft deletes documents and keeps the file', function () {
    $document = uploadDocument($this->employee);

    $this->actingAs(adminWithPermissions('employees.view', 'employee_documents.view'), 'admin')
        ->delete("/admin/employees/{$this->employee->id}/documents/{$document->id}")->assertForbidden();
    $this->actingAs(superAdmin(), 'admin')->delete("/admin/employees/{$this->employee->id}/documents/{$document->id}")->assertRedirect();

    expect(EmployeeDocument::withTrashed()->find($document->id)->trashed())->toBeTrue();
    Storage::disk(config('employees.disk'))->assertExists($document->file_path);
});

it('lets an employee download their own shared documents only', function () {
    $shared = uploadDocument($this->employee, ['visible_to_employee' => '1']);
    $private = uploadDocument($this->employee, ['visible_to_employee' => '0', 'title' => 'HR note']);
    $foreign = uploadDocument($this->outsider, ['visible_to_employee' => '1']);
    $user = User::factory()->employee()->create(['employee_id' => $this->employee->id]);

    $this->actingAs($user, 'employee')->get("/portal/documents/{$shared->id}/download")->assertOk();
    $this->actingAs($user, 'employee')->get("/portal/documents/{$private->id}/download")->assertNotFound();
    $this->actingAs($user, 'employee')->get("/portal/documents/{$foreign->id}/download")->assertNotFound();
    $this->actingAs($user, 'employee')->get('/portal/profile/documents')->assertOk()->assertSee('Passport copy')->assertDontSee('HR note');

    expect($user->account_type)->toBe(AccountType::Employee);
});

it('stores contracts with their signed copy and serves it to employee viewers only', function () {
    $this->actingAs(superAdmin(), 'admin')->post("/admin/employees/{$this->employee->id}/contracts", [
        'contract_type' => 'fixed_term', 'start_date' => '2025-01-01', 'end_date' => '2025-12-31',
        'file' => UploadedFile::fake()->create('contract.pdf', 50, 'application/pdf'),
    ])->assertSessionHasNoErrors();
    $contract = $this->employee->contracts()->sole();

    $this->actingAs(adminWithPermissions('dashboard.view'), 'admin')
        ->get("/admin/employees/{$this->employee->id}/contracts/{$contract->id}/download")->assertForbidden();
    $this->actingAs(superAdmin(), 'admin')
        ->get("/admin/employees/{$this->employee->id}/contracts/{$contract->id}/download")->assertOk()->assertDownload('contract.pdf');

    expect(AuditLog::query()->where('event', SensitiveDataAccessLog::CONTRACT)->count())->toBe(1);
});

it('renews a contract as a new row and keeps the old one unchanged', function () {
    $contract = $this->employee->contracts()->create(['contract_type' => 'fixed_term', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31']);

    $this->actingAs(superAdmin(), 'admin')->post("/admin/employees/{$this->employee->id}/contracts/{$contract->id}/renew", [
        'contract_type' => 'fixed_term', 'start_date' => '2024-06-01', 'end_date' => '2025-12-31',
    ])->assertSessionHasErrors('start_date');

    $this->actingAs(superAdmin(), 'admin')->post("/admin/employees/{$this->employee->id}/contracts/{$contract->id}/renew", [
        'contract_type' => 'fixed_term', 'start_date' => '2025-01-01', 'end_date' => '2025-12-31',
    ])->assertSessionHasNoErrors();

    expect($this->employee->contracts()->count())->toBe(2)
        ->and($contract->fresh()->end_date->toDateString())->toBe('2024-12-31')
        ->and($contract->fresh()->renewedBy->start_date->toDateString())->toBe('2025-01-01');
});
