<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-side protections on every response (appended globally in
 * bootstrap/app.php, so error pages and file downloads get them too):
 *
 * - X-Frame-Options: no other site can show these pages in a frame and trick
 *   a signed-in officer into clicking End Term or Delete through it
 *   (clickjacking). Our own previews frame our own pages, which still works.
 * - X-Content-Type-Options: a browser never guesses a file is HTML or script
 *   when it was sent as something else.
 * - Referrer-Policy: other sites see only this site's address, never a full
 *   page address with its query (search words, tokens).
 * - Permissions-Policy: no page here uses the camera, microphone or location
 *   through the browser (a phone's file picker still offers the camera).
 * - Strict-Transport-Security: on the HTTPS server only, so the browser
 *   never tries plain HTTP for this site again.
 *
 * A header a response already set on purpose (a share link's no-referrer, an
 * archived file's CSP) is left as it is.
 */
class SecurityHeaders
{
    private const HEADERS = [
        'X-Frame-Options'        => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy'        => 'strict-origin-when-cross-origin',
        'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=()',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if ($request->isSecure() && app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
