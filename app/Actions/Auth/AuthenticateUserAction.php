<?php

namespace App\Actions\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Control\AnomalyDetectionService;
use App\Services\Control\AuditLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AuthenticateUserAction
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly AnomalyDetectionService $anomalies,
    ) {}

    public function execute(LoginRequest $request): User
    {
        $login = Str::lower((string) $request->string('login'));
        $key = Str::transliterate($login.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'login' => "Terlalu banyak percobaan login. Coba kembali dalam {$seconds} detik.",
            ]);
        }

        $user = User::query()
            ->where('email', $login)
            ->orWhere('username', $login)
            ->first();

        if (! $user || ! Hash::check((string) $request->string('password'), $user->password)) {
            RateLimiter::hit($key, 60);
            $this->logFailure($request, $user, 'invalid_credentials');

            throw ValidationException::withMessages([
                'login' => 'Email/username atau kata sandi tidak sesuai.',
            ]);
        }

        if (! $user->is_active) {
            RateLimiter::hit($key, 60);
            $this->logFailure($request, $user, 'inactive_account');

            throw ValidationException::withMessages([
                'login' => 'Akun Anda tidak aktif. Hubungi administrator untuk membuka akses.',
            ]);
        }

        Auth::login($user, $request->boolean('remember'));
        RateLimiter::clear($key);
        $this->logSuccess($request, $user);

        return $user;
    }

    private function logSuccess(LoginRequest $request, User $user): void
    {
        Log::info('auth.login_success', [
            'user_id' => $user->getKey(),
            'ip' => $request->ip(),
            'user_agent_hash' => sha1((string) $request->userAgent()),
        ]);

        if (Schema::hasTable('audit_logs')) {
            $this->audit->security($request, 'auth.login_success', $user, ['user_id' => $user->getKey()]);
        }
    }

    private function logFailure(LoginRequest $request, ?User $user, string $reason): void
    {
        Log::warning('auth.login_failed', [
            'user_id' => $user?->getKey(),
            'reason' => $reason,
            'ip' => $request->ip(),
            'user_agent_hash' => sha1((string) $request->userAgent()),
        ]);

        if (Schema::hasTable('audit_logs')) {
            $this->audit->security($request, 'auth.login_failed', $user, ['user_id' => $user?->getKey(), 'reason' => $reason], 'warning');
        }
        if ($user instanceof User && Schema::hasTable('anomaly_alerts')) {
            $this->anomalies->detectLoginFailures($user);
        }
    }
}
