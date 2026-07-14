<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\SecureReportToken;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SecureDailyReportController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $secureToken = SecureReportToken::query()
            ->with(['dailyReport.schedule', 'recipient'])
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();

        abort_unless($secureToken->isUsable() || ($request->user()?->can('notifications.view') ?? false), 403);

        $secureToken->increment('access_count');
        if ($secureToken->read_at === null) {
            $secureToken->update(['read_at' => now('Asia/Jakarta')]);
        }

        return view('notifications.daily-report.secure', [
            'token' => $secureToken->refresh(),
            'report' => $secureToken->dailyReport,
        ]);
    }

    public function revoke(Request $request, SecureReportToken $token): RedirectResponse
    {
        abort_unless($request->user()->can('notifications.update'), 403);
        $token->update(['revoked_at' => now('Asia/Jakarta')]);

        return back()->with('notification', ['type' => 'success', 'message' => 'Token laporan sudah dicabut.']);
    }
}
