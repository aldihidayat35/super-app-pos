<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHealthAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local') || $request->user()?->can('system.health.view')) {
            return $next($request);
        }

        abort(403, 'Anda tidak memiliki akses ke halaman kesehatan sistem.');
    }
}
