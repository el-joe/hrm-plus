<?php

use App\Models\AuditLog;

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
