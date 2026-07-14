<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('audit.view'), 403);

        return view('audit.logs.index', [
            'logs' => $this->query($request)->paginate(20)->withQueryString(),
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        abort_unless(request()->user()?->can('audit.view'), 403);

        return view('audit.logs.show', ['log' => $auditLog->load(['actor', 'workLocation', 'subject'])]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('audit.export'), 403);

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Waktu', 'Actor', 'Module', 'Event', 'Subject', 'IP', 'Severity', 'Reason']);
            $this->query($request)->with('actor')->each(function (AuditLog $log) use ($handle): void {
                fputcsv($handle, [$log->occurred_at, $log->actor?->email, $log->module, $log->event, $log->subject_type.'#'.$log->subject_id, $log->ip_address, $log->severity, $log->reason]);
            });
            fclose($handle);
        }, 'audit-logs.csv');
    }

    /** @return Builder<AuditLog> */
    private function query(Request $request): Builder
    {
        return AuditLog::query()
            ->with(['actor', 'workLocation'])
            ->when($request->filled('module'), fn ($query) => $query->where('module', $request->query('module')))
            ->when($request->filled('event'), fn ($query) => $query->where('event', 'like', '%'.$request->query('event').'%'))
            ->when($request->filled('actor_user_id'), fn ($query) => $query->where('actor_user_id', $request->integer('actor_user_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('occurred_at', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('occurred_at', '<=', $request->query('to')))
            ->latest('occurred_at');
    }
}
