<?php

use App\Models\AuditLog;
use App\Services\Employees\SensitiveDataAccessLog;
use Symfony\Component\Finder\Finder;

it('lists audit entries for an admin with the audit permission', function () {
    AuditLog::factory()->create(['event' => 'payroll.exported']);

    $this->actingAs(adminWithPermissions('audit_logs.view'), 'admin')
        ->get('/admin/audit-logs')
        ->assertOk()
        ->assertSee('payroll.exported');
});

it('returns 403 without the audit permission', function (string $uri) {
    AuditLog::factory()->create();

    $this->actingAs(adminWithPermissions('settings.view'), 'admin')
        ->get(str_replace('{id}', (string) AuditLog::query()->value('id'), $uri))
        ->assertForbidden();
})->with(['/admin/audit-logs', '/admin/audit-logs/{id}']);

it('filters by event, actor and date range', function () {
    $actor = adminWithPermissions('audit_logs.view');
    AuditLog::factory()->create(['event' => 'leave.approved', 'user_id' => $actor->id, 'created_at' => '2026-03-10 09:00:00']);
    AuditLog::factory()->create(['event' => 'leave.rejected', 'user_id' => $actor->id, 'created_at' => '2026-03-10 09:00:00']);
    AuditLog::factory()->create(['event' => 'leave.approved', 'user_id' => $actor->id, 'created_at' => '2026-01-01 09:00:00']);

    $response = $this->actingAs($actor, 'admin')->get('/admin/audit-logs?'.http_build_query([
        'event' => 'leave.approved',
        'user_id' => $actor->id,
        'date_from' => '2026-03-01',
        'date_to' => '2026-03-31',
    ]));

    $response->assertOk();

    expect($response->viewData('logs')->pluck('created_at')->map->toDateString()->all())->toBe(['2026-03-10'])
        ->and($response->viewData('logs')->first()->event)->toBe('leave.approved');
});

it('rejects a date range that ends before it starts', function () {
    $this->actingAs(adminWithPermissions('audit_logs.view'), 'admin')
        ->get('/admin/audit-logs?date_from=2026-03-10&date_to=2026-03-01')
        ->assertSessionHasErrors('date_to');
});

it('shows the old and new values of one entry and escapes them', function () {
    $log = AuditLog::factory()->create([
        'event' => 'updated',
        'old_values' => ['name' => 'Old'],
        'new_values' => ['name' => '<script>alert(1)</script>'],
    ]);

    $this->actingAs(adminWithPermissions('audit_logs.view'), 'admin')
        ->get('/admin/audit-logs/'.$log->id)
        ->assertOk()
        ->assertSee('Old')
        ->assertSee('<script>alert(1)</script>')
        ->assertDontSee('<script>alert(1)</script>', false);
});

it('shows translated event labels and humanises unknown keys', function () {
    AuditLog::factory()->create(['event' => 'sensitive_access.employee_financial']);
    AuditLog::factory()->create(['event' => 'custom_module.thing_happened']);
    $admin = adminWithPermissions('audit_logs.view');

    $this->actingAs($admin, 'admin')->withSession(['locale' => 'en'])->get('/admin/audit-logs')
        ->assertOk()
        ->assertSee('Viewed employee financial data')
        ->assertSee('Custom module › Thing happened');

    app()->setLocale('en');
    expect(AuditLog::eventLabel('sensitive_access.employee_financial'))->toBe('Viewed employee financial data');

    app()->setLocale('ar');
    expect(AuditLog::eventLabel('sensitive_access.employee_financial'))->toBe('عرض البيانات المالية لموظف')
        ->and(AuditLog::eventLabel('custom_module.thing_happened'))->toBe('Custom module › Thing happened');
});

it('has an Arabic and English label for every audit event written in the code', function () {
    $events = collect(Finder::create()->files()->in(app_path())->name('*.php'))
        ->flatMap(function (SplFileInfo $file): array {
            preg_match_all("/->log\\(\\s*(?:\\\$\\w+\\s*\\?\\s*)?'([a-z_]+(?:\\.[a-z_]+)?)'(?:\\s*:\\s*'([a-z_]+\\.[a-z_]+)')?/", (string) file_get_contents($file->getPathname()), $matches);

            return array_filter([...$matches[1], ...$matches[2]]);
        })
        ->merge((new ReflectionClass(SensitiveDataAccessLog::class))->getConstants())
        ->reject(fn (string $event): bool => str_ends_with($event, '.') || str_ends_with($event, '_'))
        ->unique();

    expect($events->count())->toBeGreaterThan(150);

    foreach (['ar', 'en'] as $locale) {
        $labels = Lang::get('audit.events', [], $locale, false);
        $missing = $events->reject(fn (string $event): bool => isset($labels[$event]))->values()->all();
        expect($missing)->toBe([], "Missing {$locale} audit labels");
    }
});
