<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the in-app Platform Admin area (/dashboard/admin). Only a signed-in
 * user whose `is_platform_admin` flag is set may enter; everyone else gets a
 * 403. This runs on the normal `web` guard, so no separate login is needed.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user() && $request->user()->is_platform_admin,
            403,
            'Platform admins only.',
        );

        return $next($request);
    }
}
