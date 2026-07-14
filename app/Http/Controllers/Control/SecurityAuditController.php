<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SecurityAuditController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('audit.view'), 403);

        $query = $this->query($request);

        return view('audit.security.index', [
            'logs' => $query
                ->latest('occurred_at')
                ->paginate(20)
                ->withQueryString(),
            'events' => $this->events(),
            'severities' => ['info', 'warning', 'high', 'critical'],
            'users' => $this->users(),
            'summary' => $this->summary(),
            'alerts' => $this->alerts(),
        ]);
    }

    /** @return Builder<AuditLog> */
    private function query(Request $request): Builder
    {
        return AuditLog::query()
            ->with('actor')
            ->where('module', 'security')
            ->when($request->filled('event'), fn (Builder $query) => $query->where('event', $request->string('event')->toString()))
            ->when($request->filled('severity'), fn (Builder $query) => $query->where('severity', $request->string('severity')->toString()))
            ->when($request->filled('user_id'), fn (Builder $query) => $query->where('actor_user_id', $request->integer('user_id')))
            ->when($request->filled('ip_address'), fn (Builder $query) => $query->where('ip_address', 'like', '%'.$request->string('ip_address')->toString().'%'))
            ->when($request->filled('start_date'), fn (Builder $query) => $query->whereDate('occurred_at', '>=', $request->date('start_date')?->toDateString()))
            ->when($request->filled('end_date'), fn (Builder $query) => $query->whereDate('occurred_at', '<=', $request->date('end_date')?->toDateString()));
    }

    /** @return list<string> */
    private function events(): array
    {
        $defaults = [
            'auth.login_success',
            'auth.login_failed',
            'auth.rate_limited',
            'auth.logout',
            'password.reset',
            'session.revoked',
            'admin.role.updated',
            'admin.user.updated',
        ];

        $events = AuditLog::query()
            ->where('module', 'security')
            ->distinct()
            ->orderBy('event')
            ->pluck('event')
            ->all();

        return array_values(array_unique(array_merge($defaults, $events)));
    }

    /** @return Collection<int, User> */
    private function users(): Collection
    {
        return User::query()
            ->whereIn('id', AuditLog::query()->select('actor_user_id')->where('module', 'security')->whereNotNull('actor_user_id'))
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'email']);
    }

    /** @return array<string, int> */
    private function summary(): array
    {
        $since = now()->subDay();

        return [
            'login_success' => $this->countSince('auth.login_success', $since),
            'login_failed' => $this->countSince('auth.login_failed', $since),
            'rate_limited' => $this->countSince('auth.rate_limited', $since),
            'password_reset' => $this->countSince('password.reset', $since),
            'session_revoked' => $this->countSince('session.revoked', $since),
            'role_change' => AuditLog::query()
                ->where('module', 'security')
                ->whereIn('event', ['admin.role.updated', 'admin.user.updated'])
                ->where('occurred_at', '>=', $since)
                ->count(),
        ];
    }

    /** @return list<array{level: string, title: string, message: string}> */
    private function alerts(): array
    {
        $alerts = [];

        $failedLoginLastHour = $this->countSince('auth.login_failed', now()->subHour());
        if ($failedLoginLastHour >= 10) {
            $alerts[] = [
                'level' => 'danger',
                'title' => 'Login gagal tinggi',
                'message' => "Terdapat {$failedLoginLastHour} login gagal dalam 1 jam terakhir. Periksa IP dan user terkait.",
            ];
        }

        $noisiestIp = DB::table('audit_logs')
            ->select('ip_address', DB::raw('COUNT(*) as attempts'))
            ->where('module', 'security')
            ->where('event', 'auth.login_failed')
            ->whereNotNull('ip_address')
            ->where('occurred_at', '>=', now()->subDay())
            ->groupBy('ip_address')
            ->orderByDesc('attempts')
            ->first();

        if ($noisiestIp && (int) $noisiestIp->attempts >= 5) {
            $alerts[] = [
                'level' => 'warning',
                'title' => 'IP perlu diawasi',
                'message' => "IP {$noisiestIp->ip_address} memiliki {$noisiestIp->attempts} percobaan login gagal dalam 24 jam.",
            ];
        }

        return $alerts;
    }

    private function countSince(string $event, \DateTimeInterface $since): int
    {
        return AuditLog::query()
            ->where('module', 'security')
            ->where('event', $event)
            ->where('occurred_at', '>=', $since)
            ->count();
    }
}
