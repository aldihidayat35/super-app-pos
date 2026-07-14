<?php

namespace App\Http\Controllers\B2B;

use App\Actions\Auth\AuthenticateUserAction;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\B2B\B2bPortalService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class B2bAuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('b2b.auth.login');
    }

    public function store(LoginRequest $request, AuthenticateUserAction $authenticate, B2bPortalService $portal): RedirectResponse
    {
        $user = $authenticate->execute($request);

        try {
            $portal->activeCustomerFor($user);
        } catch (ServiceException $exception) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages(['login' => $exception->getMessage()]);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('langganan.katalog.index'))
            ->with('notification', ['type' => 'success', 'message' => 'Selamat datang di Portal Langganan.']);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('langganan.login')->with('notification', ['type' => 'success', 'message' => 'Anda berhasil keluar dari Portal Langganan.']);
    }
}
