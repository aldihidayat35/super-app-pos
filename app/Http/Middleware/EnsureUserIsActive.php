<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EnsureUserIsActive
{
    /**
     * @param  Closure(Request): (Response|RedirectResponse|SymfonyResponse)  $next
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse|SymfonyResponse
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'login' => 'Akun Anda tidak aktif. Hubungi administrator untuk membuka akses.',
            ]);
        }

        return $next($request);
    }
}
