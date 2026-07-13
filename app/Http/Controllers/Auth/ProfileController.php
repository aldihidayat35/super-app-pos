<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordUpdateRequest;
use App\Http\Requests\Auth\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('auth.profile', [
            'user' => request()->user(),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        if ($request->hasFile('avatar')) {
            $validated['avatar_path'] = $request->file('avatar')?->store('avatars', 'public');
        }

        unset($validated['avatar']);

        if ($validated['email'] !== $user->email) {
            $validated['email_verified_at'] = null;
        }

        $user->fill($validated)->save();

        return back()->with('notification', [
            'type' => 'success',
            'message' => 'Profil berhasil diperbarui.',
        ]);
    }

    public function updatePassword(PasswordUpdateRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $currentPassword = (string) $request->validated('current_password');

        Auth::logoutOtherDevices($currentPassword);

        $user->forceFill([
            'password' => Hash::make((string) $request->validated('password')),
            'remember_token' => str()->random(60),
        ])->save();

        $request->session()->regenerate();

        return back()->with('notification', [
            'type' => 'success',
            'message' => 'Kata sandi berhasil diperbarui. Sesi lain telah diakhiri.',
        ]);
    }
}
