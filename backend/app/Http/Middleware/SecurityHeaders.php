<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security response headers (Phase 11, config/security.php): clickjacking,
 * MIME sniffing, referrer leakage, browser features, HSTS on HTTPS and a
 * Content-Security-Policy that only allows the app's own (Vite) assets and
 * nonce-tagged inline scripts.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.headers_enabled')) {
            return $next($request);
        }

        $nonce = Vite::useCspNonce();

        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Frame-Options', 'SAMEORIGIN', false);
        $headers->set('X-Content-Type-Options', 'nosniff', false);
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        // The portal's check-in card needs the browser's location and camera (same origin only).
        $selfFeatures = $request->is('portal', 'portal/*') ? '(self)' : '()';
        $headers->set('Permissions-Policy', "camera={$selfFeatures}, microphone=(), geolocation={$selfFeatures}, payment=(), usb=()", false);
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin', false);

        $hsts = (int) config('security.hsts_max_age');

        if ($request->isSecure() && $hsts > 0) {
            $headers->set('Strict-Transport-Security', 'max-age='.$hsts.'; includeSubDomains', false);
        }

        // Only HTML documents get a policy: a CSP on an inline PDF (payslips) can break the browser's viewer.
        $isHtml = str_contains((string) $headers->get('Content-Type', 'text/html'), 'text/html');

        if ($isHtml && config('security.csp_enabled') && ! $request->is(...(array) config('security.csp_excluded_paths'))) {
            $headers->set('Content-Security-Policy', $this->policy($nonce), false);
        }

        return $response;
    }

    private function policy(string $nonce): string
    {
        $hot = Vite::isRunningHot() ? rtrim((string) file_get_contents(Vite::hotFile())) : null;
        $dev = $hot !== null ? ' '.$hot.' '.preg_replace('#^http#', 'ws', $hot) : '';
        $images = implode(' ', array_filter(['\'self\'', 'data:', 'blob:', ...(array) config('security.csp_extra_img_src')]));
        $connect = implode(' ', array_filter(['\'self\'', ...(array) config('security.csp_extra_connect_src')]));

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'".($hot !== null ? ' '.$hot : ''),
            "style-src 'self' 'unsafe-inline'".($hot !== null ? ' '.$hot : ''),
            'img-src '.$images,
            "font-src 'self' data:".($hot !== null ? ' '.$hot : ''),
            'connect-src '.$connect.$dev,
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }
}
