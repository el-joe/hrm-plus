<?php

/**
 * HTTP hardening constants (Phase 11). The switches and limits an administrator may change
 * (security headers, CSP, HSTS max-age, extra CSP origins, exports per minute) are system
 * settings of the "security" group (config/settings.php, Settings → Security), read by
 * App\Http\Middleware\SecurityHeaders and the "exports" rate limiter.
 */
return [

    // Paths that render third-party UIs and keep their own policy (API docs viewer).
    'csp_excluded_paths' => ['docs/api', 'docs/api/*'],

];
