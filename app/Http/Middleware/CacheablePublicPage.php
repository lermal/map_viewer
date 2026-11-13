<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Catalog pages are the same for every visitor: no session, no cookies, and browsers
 * (or a CDN, if configured to cache HTML) may keep a successful response for a while.
 */
class CacheablePublicPage
{
    public const MAX_AGE = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethodCacheable() && $response->getStatusCode() === 200) {
            $response->setPublic();
            $response->setMaxAge(self::MAX_AGE);
        }

        return $response;
    }
}
