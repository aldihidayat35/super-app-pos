<?php

namespace App\Http\Controllers\Notifications;

use App\Enums\NotificationChannelType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\StoreNotificationRecipientRequest;
use App\Models\NotificationRecipient;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class NotificationRecipientController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('notifications.view'), 403);

        return view('notifications.recipients.index', [
            'recipients' => NotificationRecipient::query()->with(['user', 'workLocation'])->latest('id')->paginate(20)->withQueryString(),
            'types' => NotificationChannelType::cases(),
            'users' => User::query()->where('is_active', true)->orderBy('name')->limit(200)->get(),
            'roles' => Role::query()->orderBy('name')->pluck('name'),
            'workLocations' => WorkLocation::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreNotificationRecipientRequest $request): RedirectResponse
    {
        NotificationRecipient::query()->create($this->payload($request));

        return back()->with('notification', ['type' => 'success', 'message' => 'Penerima notifikasi berhasil disimpan.']);
    }

    public function update(StoreNotificationRecipientRequest $request, NotificationRecipient $recipient): RedirectResponse
    {
        $recipient->update($this->payload($request));

        return back()->with('notification', ['type' => 'success', 'message' => 'Penerima notifikasi berhasil diperbarui.']);
    }

    /** @return array<string, mixed> */
    private function payload(StoreNotificationRecipientRequest $request): array
    {
        $validated = $request->validated();

        return [
            'name' => $validated['name'],
            'recipient_type' => $validated['recipient_type'],
            'user_id' => $validated['user_id'] ?? null,
            'role_name' => $validated['role_name'] ?? null,
            'work_location_id' => $validated['work_location_id'] ?? null,
            'channel_type' => $validated['channel_type'],
            'destination' => $validated['destination'],
            'report_type' => $validated['report_type'],
            'quiet_hours_start' => $validated['quiet_hours_start'] ?? null,
            'quiet_hours_end' => $validated['quiet_hours_end'] ?? null,
            'is_verified' => (bool) ($validated['is_verified'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }
}
