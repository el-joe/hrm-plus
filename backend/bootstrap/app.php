<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsureCareerPageEnabled;
use App\Http\Middleware\EnsureEmployeeApiAccount;
use App\Http\Middleware\EnsurePortalAccess;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Session\Middleware\AuthenticatesSessions;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureAdminAccess::class,
            'portal' => EnsurePortalAccess::class,
            'api.employee' => EnsureEmployeeApiAccount::class,
            'careers' => EnsureCareerPageEnabled::class,
        ]);

        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [SetLocale::class]);
        $middleware->api(prepend: [ForceJsonResponse::class], append: [SetLocale::class]);

        // The locale must be known before authentication can fail, so that
        // "unauthenticated" answers are translated too.
        // Framework default order with three changes: SetLocale runs before
        // authentication (so auth errors are translated), throttling runs
        // before authentication so requests with a missing or guessed token are
        // rate limited too (the limiters resolve the user themselves), and the
        // admin gate runs before route model binding.
        $middleware->priority([
            EnsureFrontendRequestsAreStateful::class,
            HandlePrecognitiveRequests::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            SetLocale::class,
            ThrottleRequests::class,
            ThrottleRequestsWithRedis::class,
            AuthenticatesRequests::class,
            AuthenticatesSessions::class,
            // Switches on the admin's data scopes, so it must run before route
            // model binding: records outside the scope then answer 404.
            EnsureAdminAccess::class,
            SubstituteBindings::class,
            Authorize::class,
        ]);

        // Where the "guest:admin" / "guest:employee" middleware send someone who is already signed in.
        $middleware->redirectUsersTo(
            fn (Request $request): string => $request->is('admin', 'admin/*') ? route('admin.dashboard') : route('portal.home'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($request->is('api/*')) {
                return app(ApiExceptionRenderer::class)->render($exception);
            }
        });
    })->create();
