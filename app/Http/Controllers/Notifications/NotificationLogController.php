<?php

namespace App\Http\Controllers\Notifications;

use App\Enums\NotificationChannelType;
use App\Enums\NotificationLogStatus;
use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Services\Notifications\NotificationDispatchService;
use App\Services\Notifications\NotificationLogPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationLogController extends Controller
{
    public function __construct(private readonly NotificationLogPresenter $presenter) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('notifications.view') || $request->user()->can('audit.view'), 403);

        $query = NotificationLog::query()
            ->with(['channel', 'template', 'recipient', 'recipientUser', 'dailyReport', 'secureToken'])
            ->when($request->filled('channel_type'), fn ($query) => $query->where('channel_type', $request->string('channel_type')->toString()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('recipient'), function ($query) use ($request): void {
                $term = '%'.$request->string('recipient')->toString().'%';
                $query->where(function ($recipient) use ($term): void {
                    $recipient->where('recipient_name', 'like', $term)
                        ->orWhere('destination', 'like', $term)
                        ->orWhereHas('recipientUser', fn ($user) => $user->where('name', 'like', $term));
                });
            })
            ->latest('id');

        return view('notifications.logs.index', [
            'logs' => $query->paginate(20)->withQueryString(),
            'statuses' => NotificationLogStatus::cases(),
            'types' => NotificationChannelType::cases(),
            'presenter' => $this->presenter,
        ]);
    }

    public function show(Request $request, NotificationLog $log): View
    {
        abort_unless($request->user()->can('notifications.view') || $request->user()->can('audit.view'), 403);

        $log->load(['channel', 'template', 'recipient', 'recipientUser', 'creator', 'dailyReport', 'secureToken']);

        return view('notifications.logs.show', [
            'log' => $log,
            'notificationType' => $this->presenter->typeLabel($log),
            'notificationTypeKey' => $this->presenter->typeKey($log),
            'safePayload' => $this->presenter->safeMetadata($this->presenter->payload($log)),
            'safeResponse' => $this->presenter->safeMetadata($this->presenter->response($log)),
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
