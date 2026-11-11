<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public career page exists only while recruitment.career_page_enabled is on.
 */
class EnsureCareerPageEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) setting('recruitment.career_page_enabled'), 404);

        return $next($request);
    }
}
