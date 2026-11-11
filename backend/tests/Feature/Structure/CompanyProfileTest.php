<?php

use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function companyPayload(array $overrides = []): array
{
    return [
        'name_ar' => 'شركة الأفق', 'name_en' => 'Horizon', 'legal_name_en' => 'Horizon LLC', 'tax_number' => '123-456',
        'email' => 'info@horizon.test', 'website' => 'https://horizon.test', 'country_code' => 'eg', 'founded_on' => '2015-01-01',
        ...$overrides,
    ];
}

beforeEach(fn () => Storage::fake('public'));

it('shows the application name until the profile is saved, with the regional settings', function () {
    $admin = adminWithPermissions('company.view');

    $this->actingAs($admin, 'admin')->get('/admin/company')
        ->assertOk()
        ->assertSee(__('structure.company.not_saved'))
        ->assertSee(setting('localisation.currency_code'))
        ->assertDontSee(route('admin.company.edit'));
});

it('saves the profile with a logo, audits it and serves the logo publicly', function () {
    $admin = adminWithPermissions('company.view', 'company.update');

    $this->actingAs($admin, 'admin')
        ->put('/admin/company', companyPayload(['logo' => UploadedFile::fake()->image('logo.png', 300, 120)]))
        ->assertRedirect(route('admin.company.show'))
        ->assertSessionHasNoErrors();

    $company = Company::current();
    expect($company)->exists->toBeTrue()->name_en->toBe('Horizon')->country_code->toBe('EG');
    Storage::disk('public')->assertExists($company->logo_path);
    expect(AuditLog::query()->where('auditable_type', 'company')->where('event', 'created')->sole()->user_id)->toBe($admin->id);

    auth()->guard('admin')->logout();
    $this->get(route('company.logo'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('replaces and removes the logo without leaving files behind', function () {
    $admin = adminWithPermissions('company.view', 'company.update');
    $this->actingAs($admin, 'admin')->put('/admin/company', companyPayload(['logo' => UploadedFile::fake()->image('a.png')]));
    $first = Company::current()->logo_path;

    $this->actingAs($admin, 'admin')->put('/admin/company', companyPayload(['logo' => UploadedFile::fake()->image('b.png')]));
    $second = Company::current()->logo_path;
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);

    $this->actingAs($admin, 'admin')->put('/admin/company', companyPayload(['remove_logo' => '1']));
    expect(Company::current()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($second);
    $this->get(route('company.logo'))->assertNotFound();
});

it('rejects an SVG or oversized logo and invalid details', function (array $input, string $error) {
    $this->actingAs(superAdmin(), 'admin')
        ->put('/admin/company', companyPayload($input))
        ->assertSessionHasErrors($error);

    expect(Company::query()->exists())->toBeFalse();
})->with([
    'svg logo' => [fn () => ['logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')], 'logo'],
    'huge logo' => [fn () => ['logo' => UploadedFile::fake()->image('big.png')->size(5000)], 'logo'],
    'missing Arabic name' => [['name_ar' => ''], 'name_ar'],
    'bad website' => [['website' => 'javascript:alert(1)'], 'website'],
    'future founding date' => [['founded_on' => '2999-01-01'], 'founded_on'],
]);

it('refuses to edit the profile without company.update', function () {
    $admin = adminWithPermissions('company.view');

    $this->actingAs($admin, 'admin')->get('/admin/company/edit')->assertForbidden();
    $this->actingAs($admin, 'admin')->put('/admin/company', companyPayload())->assertForbidden();
    $this->actingAs(adminWithPermissions('dashboard.view'), 'admin')->get('/admin/company')->assertForbidden();
});
