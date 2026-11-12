<?php

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/**
 * Every admin route must be guarded by a permission: either `can:` route middleware,
 * or a controller action that calls the gate / a policy (directly, through a private
 * helper of the same controller, or through a Form Request whose authorize() checks one).
 * Routes any signed-in admin may use by design are listed explicitly below.
 */
const ADMIN_ROUTES_OPEN_TO_EVERY_ADMIN = [
    'admin.logout',
    'admin.dashboard.customise',               // own widget layout; widgets are filtered per permission
    'admin.notifications.index',               // own notification centre (queries are scoped to the user)
    'admin.notifications.unread-count',
    'admin.notifications.read-all',
    'admin.notifications.open',                // owner check: the notification is looked up on the user
    'admin.notifications.read',
    'admin.notification-preferences.edit',     // own preferences
];

/**
 * @return list<string>
 */
function authorizingPatterns(): array
{
    return ['Gate::authorize', 'Gate::allows', 'Gate::denies', 'Gate::check', 'Gate::any', '->authorize(', '->can(', '->cannot(', '->canAny(', 'abort_unless($this->', 'Gate::forUser'];
}

function sourceOf(ReflectionFunctionAbstract $method): string
{
    $lines = file((string) $method->getFileName());

    return implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
}

function actionAuthorizes(ReflectionMethod $method, int $depth = 0): bool
{
    $source = sourceOf($method);

    if (str_contains($source, 'Gate::') || collect(authorizingPatterns())->contains(fn (string $pattern): bool => str_contains($source, $pattern))) {
        return true;
    }

    foreach ($method->getParameters() as $parameter) {
        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && is_subclass_of($type->getName(), FormRequest::class)) {
            $request = new ReflectionClass($type->getName());

            if ($request->hasMethod('authorize')) {
                $authorize = $request->getMethod('authorize');

                if ($authorize->getDeclaringClass()->getName() !== FormRequest::class && ! preg_match('/return\s+true\s*;/', sourceOf($authorize))) {
                    return true;
                }
            }
        }
    }

    if ($depth < 2 && preg_match_all('/\$this->(\w+)\(/', $source, $calls)) {
        $class = $method->getDeclaringClass();

        foreach (array_unique($calls[1]) as $helper) {
            if ($class->hasMethod($helper) && actionAuthorizes($class->getMethod($helper), $depth + 1)) {
                return true;
            }
        }
    }

    return false;
}

function routeIsProtected(RoutingRoute $route): bool
{
    if (collect($route->gatherMiddleware())->contains(fn ($middleware): bool => is_string($middleware) && (str_starts_with($middleware, 'can:') || str_starts_with($middleware, 'Illuminate\Auth\Middleware\Authorize:')))) {
        return true;
    }

    $action = $route->getAction('uses');

    if (! is_string($action)) {
        return false;
    }

    [$controller, $method] = str_contains($action, '@') ? explode('@', $action) : [$action, '__invoke'];

    return actionAuthorizes(new ReflectionMethod($controller, $method));
}

test('every admin route requires a permission or calls a policy', function () {
    $unprotected = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => in_array('admin', $route->gatherMiddleware(), true))
        ->reject(fn (RoutingRoute $route): bool => in_array($route->getName(), ADMIN_ROUTES_OPEN_TO_EVERY_ADMIN, true))
        ->reject(fn (RoutingRoute $route): bool => routeIsProtected($route))
        ->map(fn (RoutingRoute $route): string => implode('|', $route->methods()).' '.$route->uri().' ('.$route->getName().')')
        ->values();

    expect($unprotected->all())->toBe([]);
});

test('the allow-list only names routes that exist', function () {
    foreach (ADMIN_ROUTES_OPEN_TO_EVERY_ADMIN as $name) {
        expect(Route::has($name))->toBeTrue("Route [{$name}] is allow-listed but does not exist");
    }
});
