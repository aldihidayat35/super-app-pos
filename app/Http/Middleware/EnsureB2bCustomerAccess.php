<?php

namespace App\Http\Middleware;

use App\Exceptions\ServiceException;
use App\Services\B2B\B2bPortalService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureB2bCustomerAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            app()->instance('b2b.customer', app(B2bPortalService::class)->activeCustomerFor($request->user()));
        } catch (ServiceException) {
            abort(403, 'Akun langganan belum aktif, belum terverifikasi, atau sedang diblokir.');
        }

        return $next($request);
    }
}
