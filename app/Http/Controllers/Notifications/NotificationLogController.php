<?php

namespace App\Http\Controllers\Notifications;

use App\Enums\NotificationChannelType;
use App\Enums\NotificationLogStatus;
use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('notifications.view') || $request->user()->can('audit.view'), 403);

        $query = NotificationLog::query()
            ->with(['channel', 'template', 'recipient', 'dailyReport', 'secureToken'])
            ->when($request->filled('channel_type'), fn ($query) => $query->where('channel_type', $request->string('channel_type')->toString()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('recipient'), fn ($query) => $query->where('recipient_name', 'like', '%'.$request->string('recipient')->toString().'%'))
            ->latest('id');

        return view('notifications.logs.index', [
            'logs' => $query->paginate(20)->withQueryString(),
            'statuses' => NotificationLogStatus::cases(),
            'types' => NotificationChannelType::cases(),
        ]);
    }

    public function retry(Request $request, NotificationLog $log, NotificationDispatchService $dispatcher): RedirectResponse
    {
        abort_unless($request->user()->can('notifications.send'), 403);
        $log->update(['status' => NotificationLogStatus::QUEUED->value, 'next_retry_at' => null]);
        $dispatcher->send($log);

        return back()->with('notification', ['type' => 'success', 'message' => 'Retry manual diproses.']);
    }
}
