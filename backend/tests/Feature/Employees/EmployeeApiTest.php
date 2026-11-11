<?php

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Services\Employees\SensitiveDataAccessLog;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake(config('employees.disk'));
    $this->manager = Employee::factory()->create(['name_en' => 'Big Boss']);
    $this->employee = Employee::factory()->create(['name_en' => 'Sara Adel', 'national_id' => '29001011234567', 'manager_id' => $this->manager->id, 'basic_salary' => '12000.00']);
    $this->user = User::factory()->employee()->create(['employee_id' => $this->employee->id]);
});

function apiDocument(Employee $employee, bool $shared, string $title = 'Passport'): EmployeeDocument
{
    Storage::disk(config('employees.disk'))->put("{$employee->id}/documents/{$title}.pdf", '%PDF-1.4 test');

    return $employee->documents()->create([
        'category' => 'passport', 'title' => $title, 'visible_to_employee' => $shared, 'expiry_date' => now()->addMonth(),
        'file_path' => "{$employee->id}/documents/{$title}.pdf", 'file_name' => 'passport.pdf', 'file_mime' => 'application/pdf', 'file_size' => 13,
    ]);
}

it('returns the signed-in employee\'s own profile with masked identity and no salary', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/v1/me/profile', ['Accept-Language' => 'en'])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.employee_number', $this->employee->employee_number)
        ->assertJsonPath('data.name', 'Sara Adel')
        ->assertJsonPath('data.employment.manager.name', 'Big Boss')
        ->assertJsonPath('data.personal.national_id_masked', '••••••••••4567');

    expect($response->getContent())->not->toContain('29001011234567')->not->toContain('12000');
});

it('lists only documents shared with the employee and serves their files', function () {
    $shared = apiDocument($this->employee, true);
    $private = apiDocument($this->employee, false, 'Internal');
    $foreign = apiDocument($this->manager, true, 'Boss');
    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/me/documents')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $shared->id)
        ->assertJsonPath('meta.pagination.total', 1);

    $this->getJson('/api/v1/me/documents?filter[category]=cv')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/api/v1/me/documents/{$shared->id}")->assertOk()->assertJsonPath('data.title', 'Passport');
    $this->get("/api/v1/me/documents/{$shared->id}/download")->assertOk()->assertDownload('passport.pdf');
    $this->getJson("/api/v1/me/documents/{$private->id}")->assertNotFound()->assertJsonPath('success', false);
    $this->getJson("/api/v1/me/documents/{$foreign->id}/download")->assertNotFound();

    expect(AuditLog::query()->where('event', SensitiveDataAccessLog::DOCUMENT)->count())->toBe(1);
});

it('refuses admin tokens and unauthenticated calls', function () {
    $this->getJson('/api/v1/me/profile')->assertUnauthorized();

    Sanctum::actingAs(User::factory()->admin()->create());
    $this->getJson('/api/v1/me/profile')->assertForbidden();
});

it('answers 404 for an employee account without a profile', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/me/profile')->assertNotFound();
});
