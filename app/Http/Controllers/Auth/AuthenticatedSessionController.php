<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, AuthenticateUserAction $authenticate): RedirectResponse
    {
        $user = $authenticate->execute($request);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        if ($user->hasRole('staf_toko')) {
            $request->session()->forget('url.intended');
        }

        $redirect = $user->hasRole('staf_toko')
            ? redirect()->route('retail.storefront.index')
            : redirect()->intended(route('dashboard'));

        return $redirect->with('notification', [
            'type' => 'success',
            'message' => 'Selamat datang di GudangToko.',
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('notification', [
            'type' => 'success',
            'message' => 'Anda berhasil keluar dari aplikasi.',
        ]);
    }
}
