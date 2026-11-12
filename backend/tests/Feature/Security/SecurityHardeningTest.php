<?php

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

test('every state-changing browser route runs the web group with CSRF protection', function () {
    expect(app('router')->getMiddlewareGroups()['web'])->toContain(PreventRequestForgery::class);

    $unprotected = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [])
        ->reject(fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'api/'))
        ->reject(fn (RoutingRoute $route): bool => in_array('web', $route->gatherMiddleware(), true))
        ->map(fn (RoutingRoute $route): string => $route->uri())
        ->values();

    expect($unprotected->all())->toBe([]);
});

test('a form post without a CSRF token is rejected outside the test environment', function () {
    // The CSRF middleware is skipped while running unit tests; pretend to be production.
    app()['env'] = 'production';

    $this->post(route('admin.login'), ['email' => 'someone@example.com', 'password' => 'secret'])
        ->assertStatus(419);
});

test('web responses carry the security headers and a nonce-based content security policy', function () {
    $response = $this->get(route('admin.login'))->assertOk();

    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    $policy = (string) $response->headers->get('Content-Security-Policy');
    preg_match("/script-src 'self' 'nonce-([^']+)'/", $policy, $nonce);

    expect($nonce)->not->toBeEmpty()
        ->and($policy)->toContain("object-src 'none'")
        ->and($policy)->toContain("frame-ancestors 'self'")
        ->and($policy)->not->toContain("script-src 'self' 'unsafe-inline'")
        // The inline theme bootstrap carries the request's nonce, so the browser runs it.
        ->and($response->getContent())->toContain('<script nonce="'.$nonce[1].'">');
});

test('API responses carry the security headers too', function () {
    $this->getJson('/api/v1/app/config')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('HSTS is only sent over HTTPS', function () {
    $this->get(route('admin.login'))->assertHeaderMissing('Strict-Transport-Security');
    $this->get(str_replace('http://', 'https://', route('admin.login')))->assertHeader('Strict-Transport-Security');
});

test('exports are rate limited per user', function () {
    config(['security.exports_per_minute' => 2]);
    $admin = adminWithPermissions('employees.view', 'employees.export');

    $this->actingAs($admin, 'admin');
    $this->get(route('admin.employees.export'))->assertOk();
    $this->get(route('admin.employees.export'))->assertOk();
    $this->get(route('admin.employees.export'))->assertStatus(429);
});

test('the session cookie is http-only and secure when the app is served over https', function () {
    expect(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax');

    $config = require config_path('session.php');
    expect($config['secure'])->toBe(str_starts_with((string) env('APP_URL'), 'https://'));
})->skip(fn (): bool => env('SESSION_SECURE_COOKIE') !== null, 'SESSION_SECURE_COOKIE is set explicitly');

test('personal identifiers are redacted from audit metadata', function () {
    $user = User::factory()->admin()->create();
    $log = app(AuditLogger::class)->log('test.event', $user, meta: ['iban' => 'EG38...', 'national_id' => '2900', 'nested' => ['account_number' => '123'], 'note' => 'kept']);

    expect($log->meta)->toBe(['iban' => '[REDACTED]', 'national_id' => '[REDACTED]', 'nested' => ['account_number' => '[REDACTED]'], 'note' => 'kept']);
});
