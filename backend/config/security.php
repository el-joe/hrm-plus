<?php

/**
 * HTTP hardening (Phase 11). Read by App\Http\Middleware\SecurityHeaders and the
 * "exports" rate limiter (AppServiceProvider).
 */
return [

    // Security response headers on every web and API response.
    'headers_enabled' => env('SECURITY_HEADERS', true),

    /*
     * Content-Security-Policy. Scripts must come from this origin or carry the
     * per-request nonce (Vite::cspNonce()); the inline theme bootstrap in
     * partials/head uses it. Styles allow 'unsafe-inline' for the @font-face
     * block of @fonts and a few style="" attributes. While `npm run dev` is
     * running, the Vite dev server origin is added automatically.
     */
    'csp_enabled' => env('SECURITY_CSP', true),

    // Extra origins for img-src / connect-src (e.g. an object storage CDN), comma-separated.
    'csp_extra_img_src' => array_filter(explode(',', (string) env('SECURITY_CSP_IMG_SRC', ''))),
    'csp_extra_connect_src' => array_filter(explode(',', (string) env('SECURITY_CSP_CONNECT_SRC', ''))),

    // Paths that render third-party UIs and keep their own policy (API docs viewer).
    'csp_excluded_paths' => ['docs/api', 'docs/api/*'],

    // Strict-Transport-Security max-age, sent only on HTTPS requests. 0 disables it.
    'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),

    // Export / download requests per minute per signed-in user ("throttle:exports").
    'exports_per_minute' => (int) env('SECURITY_EXPORTS_PER_MINUTE', 20),

];
