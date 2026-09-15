<?php

namespace App\Http\Controllers\Notifications;

use App\Enums\NotificationChannelType;
use App\Http\Controllers\Controller;
use App\Jobs\SendNotificationJob;
use App\Models\NotificationLog;
use App\Models\WhatsappConnection;
use App\Services\Control\AuditLogService;
use App\Services\Notifications\NotificationDispatchService;
use App\Services\Notifications\NotificationLogPresenter;
use App\Services\Notifications\WhatsappConnectionService;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class WhatsappConnectionController extends Controller
{
    public function __construct(private readonly NotificationLogPresenter $logPresenter) {}

    public function index(Request $request, WhatsappConnectionService $connections): View
    {
        abort_unless($request->user()->can('whatsapp_connection.view'), 403);

        $data = $connections->refresh($request->user());
        $data['messages'] = $this->recentMessages();

        return view('notifications.whatsapp-connection', $data);
    }

    public function status(Request $request, WhatsappConnectionService $connections): JsonResponse
    {
        abort_unless($request->user()->can('whatsapp_connection.view'), 403);

        return response()->json($this->payload($connections->refresh($request->user())));
    }

    public function connect(Request $request, WhatsappConnectionService $connections, AuditLogService $audit): JsonResponse
    {
        abort_unless($request->user()->can('whatsapp_connection.manage'), 403);

        return $this->perform(function () use ($request, $connections, $audit): array {
            $result = $connections->connect($request->user());
            $audit->record('whatsapp.connection.connect_requested', 'notifications', $request->user(), $result['connection'], request: $request);

            return $result;
        });
    }

    public function reconnect(Request $request, WhatsappConnectionService $connections, AuditLogService $audit): JsonResponse
    {
        abort_unless($request->user()->can('whatsapp_connection.manage'), 403);

        return $this->perform(function () use ($request, $connections, $audit): array {
            $result = $connections->reconnect($request->user());
            $audit->record('whatsapp.connection.reconnect_requested', 'notifications', $request->user(), $result['connection'], request: $request);

            return $result;
        });
    }

    public function disconnect(Request $request, WhatsappConnectionService $connections, AuditLogService $audit): JsonResponse
    {
        abort_unless($request->user()->can('whatsapp_connection.manage'), 403);

        return $this->perform(function () use ($request, $connections, $audit): array {
            $result = $connections->disconnect($request->user());
            $audit->record('whatsapp.connection.logged_out', 'notifications', $request->user(), $result['connection'], request: $request, severity: 'warning');

            return $result;
        });
    }

    public function test(Request $request, WhatsappConnectionService $connections, NotificationDispatchService $dispatcher, AuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user()->can('whatsapp_connection.manage'), 403);
        $data = $request->validate(['destination' => ['required', 'string', 'max:30'], 'message' => ['required', 'string', 'max:1000']], [], ['destination' => 'nomor tujuan', 'message' => 'pesan']);
        $connection = $connections->refresh($request->user())['connection'];
        abort_unless($connection->status === 'connected', 422, 'WhatsApp belum terhubung.');
        $log = $dispatcher->queueLog(NotificationChannelType::WHATSAPP, $data['destination'], $data['message'], actor: $request->user(), payload: ['source' => 'whatsapp_connection_test'], idempotencyKey: hash('sha256', 'wa-test|'.$request->user()->id.'|'.now()->timestamp));
        SendNotificationJob::dispatch($log->id)->afterCommit();
        $audit->record('whatsapp.connection.test_queued', 'notifications', $request->user(), $connection, request: $request);

        return back()->with('notification', ['type' => 'success', 'message' => 'Pesan uji masuk antrean pengiriman.']);
    }

    /**
     * @param  array{connection: WhatsappConnection, qr: ?string, qr_expires_at: ?string, configured: bool}  $result
     * @return array<string, mixed>
     */
    private function payload(array $result): array
    {
        $connection = $result['connection'];

        return ['configured' => $result['configured'], 'status' => $connection->status, 'phone' => $connection->phone_number, 'name' => $connection->account_name, 'connected_at' => $this->isoDate($connection->getAttribute('connected_at')), 'last_checked_at' => $this->isoDate($connection->getAttribute('last_checked_at')), 'last_error' => $connection->last_error, 'qr' => $result['qr'], 'qr_expires_at' => $result['qr_expires_at'], 'messages' => $this->recentMessages()];
    }

    /** @return list<array<string, mixed>> */
    private function recentMessages(): array
    {
        return NotificationLog::query()
            ->with('recipientUser')
            ->with('template')
            ->where('channel_type', NotificationChannelType::WHATSAPP->value)
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (NotificationLog $log): array => [
                'id' => $log->id,
                'type' => $this->logPresenter->typeLabel($log),
                'detail_url' => route('admin.notifications.logs.show', $log),
                'recipient_name' => $log->recipient_user_id !== null ? $log->recipientUser->name : $log->recipient_name,
                'recipient_linked' => $log->recipient_user_id !== null,
                'destination' => $log->destination,
                'message' => str($log->body)->limit(80)->toString(),
                'status' => $log->deliveryStatus()->value,
                'status_label' => $log->deliveryStatus()->label(),
                'attempts' => $log->attempts,
                'provider_message_id' => $log->provider_message_id,
                'error' => $log->error_message,
                'created_at' => $this->localDateTime($log->getAttribute('created_at')),
                'sent_at' => $this->localDateTime($log->getAttribute('sent_at')),
            ])
            ->all();
    }

    /** @param callable(): array{connection: WhatsappConnection, qr: ?string, qr_expires_at: ?string, configured: bool} $operation */
    private function perform(callable $operation): JsonResponse
    {
        try {
            return response()->json($this->payload($operation()));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Gateway WhatsApp tidak dapat memproses permintaan. Periksa service Node.'], 502);
        }
    }

    private function isoDate(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toIso8601String();
        }

        return is_string($value) && $value !== '' ? Carbon::parse($value)->toIso8601String() : null;
    }

    private function localDateTime(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->timezone('Asia/Jakarta')->format('d/m/Y H:i:s');
        }

        return is_string($value) && $value !== ''
            ? Carbon::parse($value)->timezone('Asia/Jakarta')->format('d/m/Y H:i:s')
            : null;
    }
}
