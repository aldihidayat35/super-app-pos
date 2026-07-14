<?php

namespace App\Http\Controllers\Notifications;

use App\Enums\NotificationChannelType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\StoreNotificationChannelRequest;
use App\Models\NotificationChannel;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationChannelController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('notifications.view'), 403);

        return view('notifications.channels.index', [
            'channels' => NotificationChannel::query()->latest('id')->paginate(20)->withQueryString(),
            'types' => NotificationChannelType::cases(),
        ]);
    }

    public function store(StoreNotificationChannelRequest $request): RedirectResponse
    {
        $data = $this->payload($request);
        $data['created_by'] = $request->user()->id;
        NotificationChannel::query()->create($data);

        return back()->with('notification', ['type' => 'success', 'message' => 'Channel notifikasi berhasil disimpan.']);
    }

    public function update(StoreNotificationChannelRequest $request, NotificationChannel $channel): RedirectResponse
    {
        $data = $this->payload($request, $channel);
        $data['updated_by'] = $request->user()->id;
        $channel->update($data);

        return back()->with('notification', ['type' => 'success', 'message' => 'Channel notifikasi berhasil diperbarui.']);
    }

    public function test(StoreNotificationChannelRequest $request, NotificationChannel $channel, NotificationDispatchService $dispatcher): RedirectResponse
    {
        $destination = $request->validated('test_destination') ?: $channel->default_destination;
        abort_unless($destination, 422, 'Tujuan test wajib diisi.');

        $log = $dispatcher->queueLog(
            $channel->type(),
            $destination,
            $request->validated('test_message') ?: 'Test pesan GudangToko berhasil dibuat.',
            null,
            null,
            null,
            $request->user(),
            ['source' => 'channel_test'],
            hash('sha256', 'channel-test|'.$channel->id.'|'.$destination.'|'.now('Asia/Jakarta')->timestamp),
        );

        $dispatcher->send($log);

        return back()->with('notification', ['type' => 'success', 'message' => 'Test message diproses. Cek log pengiriman untuk status akhir.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(StoreNotificationChannelRequest $request, ?NotificationChannel $channel = null): array
    {
        $validated = $request->validated();
        $credentials = $channel instanceof NotificationChannel ? $channel->credentialData() : [];
        if (($validated['token'] ?? null) !== null) {
            $credentials['token'] = $validated['token'];
        }
        if (($validated['bot_token'] ?? null) !== null) {
            $credentials['bot_token'] = $validated['bot_token'];
        }

        return [
            'name' => $validated['name'],
            'channel_type' => $validated['channel_type'],
            'endpoint' => $validated['endpoint'] ?? null,
            'auth_type' => $validated['auth_type'],
            'credentials' => $credentials,
            'sender' => $validated['sender'] ?? null,
            'default_destination' => $validated['default_destination'] ?? null,
            'timeout_seconds' => (int) $validated['timeout_seconds'],
            'retry_attempts' => (int) $validated['retry_attempts'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }
}
