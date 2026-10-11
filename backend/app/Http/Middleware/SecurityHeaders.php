<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Emits baseline security headers on every response.
 *
 * A restrictive Content-Security-Policy is added only to HTML responses so it
 * does not interfere with JSON API payloads, while still hardening any browser
 * rendered page (including the OpenAPI UI and the welcome page).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');

        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $contentType = (string) $response->headers->get('content-type');

        if (str_contains($contentType, 'text/html')) {
            $response->headers->set('Content-Security-Policy',
                "default-src 'self'; "
                .'script-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://unpkg.com; '
                .'style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://unpkg.com; '
                ."img-src 'self' data: https:; "
                ."font-src 'self' data: https:; "
                ."connect-src 'self'"
            );
        }

        return $response;
    }
}
