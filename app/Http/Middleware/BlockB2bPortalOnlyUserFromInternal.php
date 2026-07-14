<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockB2bPortalOnlyUserFromInternal
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->hasOnlyB2bPortalRoles()) {
            return redirect()->route('langganan.dashboard')
                ->with('notification', ['type' => 'warning', 'message' => 'Akun langganan hanya dapat mengakses portal pelanggan.']);
        }

        return $next($request);
    }
}
