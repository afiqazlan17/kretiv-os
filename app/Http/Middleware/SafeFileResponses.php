<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uploaded files are opened in the browser only when they're plain PDFs or
 * pictures. Anything else (an SVG or HTML page that could carry a script)
 * is sent as a download, and browsers are told not to guess file types.
 */
class SafeFileResponses
{
    private const INLINE_OK = ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'text/plain', 'text/csv'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        $disposition = (string) $response->headers->get('Content-Disposition');
        $type = strtolower(trim(explode(';', (string) $response->headers->get('Content-Type'))[0]));
        if (str_starts_with(strtolower($disposition), 'inline') && ! in_array($type, self::INLINE_OK, true)) {
            $response->headers->set('Content-Disposition', 'attachment'.substr($disposition, 6));
        }

        return $response;
    }
}
