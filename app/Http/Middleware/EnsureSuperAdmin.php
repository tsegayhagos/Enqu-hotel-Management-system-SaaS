<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Restricts SaaS-platform routes (hotel onboarding, global metrics) to Super Admins. */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_super_admin === true, 403, 'Super Admin access required.');

        return $next($request);
    }
}
