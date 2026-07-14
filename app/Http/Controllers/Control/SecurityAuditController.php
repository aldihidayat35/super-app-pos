<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SecurityAuditController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('audit.view'), 403);

        return view('audit.security.index', [
            'logs' => AuditLog::query()
                ->with('actor')
                ->where('module', 'security')
                ->when($request->filled('event'), fn ($query) => $query->where('event', $request->query('event')))
                ->latest('occurred_at')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }
}
