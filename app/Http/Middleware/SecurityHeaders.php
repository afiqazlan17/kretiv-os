<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response: HTTPS only (HSTS, this host only),
 * no framing by other sites, no full URLs (with tokens) leaked to other
 * sites, and no camera, mic or location. Uploaded files are opened in the
 * browser only when they're plain PDFs or pictures; anything else (an SVG
 * or HTML page that could carry a script) is sent as a download.
 */
class SecurityHeaders
{
    private const INLINE_OK = ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'text/plain', 'text/csv'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        if (config('demo.enabled')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        $disposition = (string) $response->headers->get('Content-Disposition');
        $type = strtolower(trim(explode(';', (string) $response->headers->get('Content-Type'))[0]));
        if (str_starts_with(strtolower($disposition), 'inline') && ! in_array($type, self::INLINE_OK, true)) {
            $response->headers->set('Content-Disposition', 'attachment'.substr($disposition, 6));
        }

        return $response;
    }
}
