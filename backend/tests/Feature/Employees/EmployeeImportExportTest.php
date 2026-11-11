<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Services\Employees\SensitiveDataAccessLog;
use App\Services\Employees\Spreadsheet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('employees.disk'));
    $this->org = hrStructure();
});

function importCsv(array $rows): UploadedFile
{
    $header = 'name_ar,name_en,email,hire_date,branch_code,department_code,position_code,employment_type_code,national_id,basic_salary';
    $lines = array_map(fn (array $row): string => implode(',', $row), $rows);

    return UploadedFile::fake()->createWithContent('employees.csv', $header."\n".implode("\n", $lines)."\n");
}

it('dry-runs an import, reporting each problem by line without saving anything', function () {
    $file = importCsv([
        ['سارة', 'Sara', 'sara@example.test', '2024-01-01', 'CAI', 'IT', 'DEV', 'FT', '29001011234567', '1000'],
        ['عمر', 'Omar', 'sara@example.test', '2024-13-01', 'XXX', 'IT', '', 'FT', '', ''],
    ]);

    $response = $this->actingAs(superAdmin(), 'admin')->post('/admin/employees/import', ['file' => $file])->assertOk();

    $report = $response->viewData('report');
    expect($report['total'])->toBe(2)->and($report['valid'])->toBe(1)
        ->and(collect($report['errors'])->where('line', 3)->pluck('column')->all())->toContain('branch_code', 'email', 'hire_date')
        ->and(Employee::query()->count())->toBe(0);
    $response->assertSee(__('employees.import.fix_errors'));
});

it('imports every row of a valid file in one go, with numbers, history and salary', function () {
    $file = importCsv([
        ['سارة', 'Sara', 'sara@example.test', '2024-01-01', 'CAI', 'IT', 'DEV', 'FT', '29001011234567', '1000.50'],
        ['عمر', 'Omar', 'omar@example.test', '2023-05-01', 'cai', 'it', '', 'ft', '', ''],
    ]);
    $admin = superAdmin();

    $token = $this->actingAs($admin, 'admin')->post('/admin/employees/import', ['file' => $file])->viewData('token');
    $this->actingAs($admin, 'admin')->post("/admin/employees/import/{$token}")->assertRedirect('/admin/employees');

    expect(Employee::query()->count())->toBe(2);
    $sara = Employee::query()->where('email', 'sara@example.test')->sole();
    expect($sara->position_id)->toBe($this->org['position']->id)
        ->and($sara->basic_salary)->toBe('1000.50')
        ->and($sara->national_id)->toBe('29001011234567')
        ->and($sara->assignments()->count())->toBe(1);
});

it('imports nothing when a row breaks the headcount of a position, counting the rows above it', function () {
    $this->org['position']->update(['headcount' => 1]);
    $file = importCsv([
        ['أ', 'A', 'a@example.test', '2024-01-01', 'CAI', 'IT', 'DEV', 'FT', '', ''],
        ['ب', 'B', 'b@example.test', '2024-01-01', 'CAI', 'IT', 'DEV', 'FT', '', ''],
    ]);
    $admin = superAdmin();

    $report = $this->actingAs($admin, 'admin')->post('/admin/employees/import', ['file' => $file])->viewData('report');
    expect(collect($report['errors'])->pluck('line')->all())->toBe([3]);
});

it('does not let another admin confirm someone else\'s upload', function () {
    $file = importCsv([['أ', 'A', 'a@example.test', '2024-01-01', 'CAI', 'IT', '', 'FT', '', '']]);
    $token = $this->actingAs(superAdmin(), 'admin')->post('/admin/employees/import', ['file' => $file])->viewData('token');

    $this->actingAs(superAdmin(), 'admin')->post("/admin/employees/import/{$token}")
        ->assertRedirect('/admin/employees/import')->assertSessionHas('error');
    expect(Employee::query()->count())->toBe(0);
});

it('resolves import codes inside the admin\'s data scope only', function () {
    $alex = Branch::factory()->create(['code' => 'ALX']);
    Department::factory()->create(['code' => 'OPS', 'branch_id' => $alex->id]);
    $admin = adminWithPermissions('employees.view', 'employees.create', 'branches.view', 'departments.view');
    $admin->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => $this->org['branch']->id]);

    $report = $this->actingAs($admin->fresh(), 'admin')
        ->post('/admin/employees/import', ['file' => importCsv([['أ', 'A', '', '2024-01-01', 'ALX', 'OPS', '', 'FT', '', '']])])
        ->viewData('report');

    expect(collect($report['errors'])->pluck('column')->all())->toContain('branch_code', 'department_code');
});

it('exports only the employees in the admin\'s scope, with protected columns by permission, and logs it', function () {
    $alex = Branch::factory()->create();
    Employee::factory()->inDepartment($this->org['department'])->create(['name_en' => 'Cairo Person', 'national_id' => '29001011234567']);
    Employee::factory()->inDepartment(Department::factory()->create(['branch_id' => $alex->id]))->create(['name_en' => 'Alex Person']);
    $admin = adminWithPermissions('employees.view', 'employees.export');
    $admin->update(['locale' => 'en']);
    $admin->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => $this->org['branch']->id]);

    $response = $this->actingAs($admin->fresh(), 'admin')->get('/admin/employees/export?format=csv')->assertOk()->assertDownload();
    $rows = app(Spreadsheet::class)->read($response->getFile()->getPathname(), 'csv', 100);

    expect($rows)->toHaveCount(1)
        ->and(array_values($rows)[0])->toHaveKey('employee_number')
        ->and(collect(array_values($rows)[0])->contains('Cairo Person'))->toBeTrue()
        ->and(implode(',', array_keys(array_values($rows)[0])))->not->toContain('national_id')->not->toContain('basic_salary');

    $log = AuditLog::query()->where('event', SensitiveDataAccessLog::EXPORT)->sole();
    expect($log->meta['rows'])->toBe(1)->and($log->user_id)->toBe($admin->id);
});

it('adds identity and salary columns for admins allowed to see them', function () {
    Employee::factory()->inDepartment($this->org['department'])->create(['national_id' => '29001011234567']);

    $admin = superAdmin();
    $admin->update(['locale' => 'en']);
    $response = $this->actingAs($admin, 'admin')->get('/admin/employees/export?format=csv')->assertOk();
    $row = array_values(app(Spreadsheet::class)->read($response->getFile()->getPathname(), 'csv', 100))[0];

    expect($row)->toHaveKey('national_id')->toHaveKey('basic_salary')
        ->and($row['national_id'])->toBe('29001011234567');
});

it('needs employees.export and employees.create', function () {
    $viewer = adminWithPermissions('employees.view');

    $this->actingAs($viewer, 'admin')->get('/admin/employees/export')->assertForbidden();
    $this->actingAs($viewer, 'admin')->get('/admin/employees/import')->assertForbidden();
    $this->actingAs($viewer, 'admin')->post('/admin/employees/import', ['file' => importCsv([])])->assertForbidden();
});

it('downloads an xlsx template with the columns the admin may fill', function () {
    $this->actingAs(superAdmin(), 'admin')->get('/admin/employees/import/template')->assertOk()->assertDownload('employee-import-template.xlsx');
});
