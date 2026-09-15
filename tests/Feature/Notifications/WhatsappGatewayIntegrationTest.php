<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationChannelType;
use App\Jobs\SendNotificationJob;
use App\Models\NotificationChannel;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\Notifications\NotificationDispatchService;
use App\Services\Notifications\WhatsappGatewayClient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsappGatewayIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        config()->set('whatsapp.gateway', ['enabled' => true, 'base_url' => 'http://wa-gateway.test', 'api_key' => 'secret-test-key', 'session_id' => 'gudangtoko-main', 'timeout' => 10]);
    }

    public function test_admin_config_can_connect_and_qr_is_only_proxied(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin_config');
        Http::fake([
            'wa-gateway.test/api/integrations/pos/session/status' => Http::response(['success' => true, 'data' => ['status' => 'disconnected']], 200),
            'wa-gateway.test/api/integrations/pos/session/connect' => Http::response(['success' => true, 'data' => ['status' => 'qr_ready', 'qr' => 'QR-SECRET', 'qr_expires_at' => now()->addSeconds(20)->toIso8601String()]], 200),
        ]);

        $this->actingAs($admin)->get(route('admin.whatsapp.index'))->assertOk()->assertDontSee('secret-test-key');
        $this->actingAs($admin)->postJson(route('admin.whatsapp.connect'))->assertOk()->assertJsonPath('status', 'qr_ready')->assertJsonPath('qr', 'QR-SECRET');
        $this->assertDatabaseHas('whatsapp_connections', ['session_id' => 'gudangtoko-main', 'status' => 'qr_ready']);
        $this->assertDatabaseMissing('whatsapp_connections', ['last_error' => 'QR-SECRET']);
        Http::assertSent(fn ($request): bool => $request->hasHeader('X-Api-Key', 'secret-test-key'));
    }

    public function test_connected_session_creates_baileys_channel_and_test_message_is_queued(): void
    {
        Queue::fake();
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        Http::fake(['wa-gateway.test/api/integrations/pos/session/status' => Http::response(['success' => true, 'data' => ['status' => 'connected', 'phone' => '628123456789', 'name' => 'GudangToko']], 200)]);

        $this->actingAs($admin)->get(route('admin.whatsapp.index'))->assertOk();
        $this->assertDatabaseHas('notification_channels', ['sender' => 'gudangtoko-main', 'is_active' => true]);
        $channel = NotificationChannel::query()->where('sender', 'gudangtoko-main')->firstOrFail();
        $this->assertSame('baileys_gateway', $channel->metadata['provider']);

        $this->actingAs($admin)->post(route('admin.whatsapp.test'), ['destination' => '0812-3456-7890', 'message' => 'Tes koneksi'])->assertRedirect();
        Queue::assertPushed(SendNotificationJob::class);
        $this->assertDatabaseHas('notification_logs', ['destination' => '0812-3456-7890', 'status' => 'queued']);

        $this->actingAs($admin)->getJson(route('admin.whatsapp.status'))
            ->assertOk()
            ->assertJsonPath('messages.0.destination', '0812-3456-7890')
            ->assertJsonPath('messages.0.type', 'Pesan Uji WhatsApp')
            ->assertJsonPath('messages.0.status_label', 'Dalam Antrian');
    }

    public function test_history_uses_actual_recipient_account_on_connection_and_delivery_log_pages(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin_config');
        $recipient = User::factory()->create(['name' => 'Kepala Toko Melati', 'phone_number' => '0812-3456-7890']);
        $recipient->assignRole('kepala_toko');
        Http::fake(['wa-gateway.test/api/integrations/pos/session/status' => Http::response(['success' => true, 'data' => ['status' => 'connected']], 200)]);

        $log = app(NotificationDispatchService::class)->queueLog(
            NotificationChannelType::WHATSAPP,
            '+62 812-3456-7890',
            "Stok Toko Melati hampir habis.\nSegera lakukan pemeriksaan.",
            actor: $admin,
            payload: ['source' => 'business_event', 'event_key' => 'critical_stock', 'subject_id' => 17, 'api_key' => 'tidak-boleh-tampil'],
            subject: 'Peringatan Stok',
        );

        $this->assertSame($recipient->id, $log->recipient_user_id);
        $this->actingAs($admin)->get(route('admin.whatsapp.index'))
            ->assertOk()
            ->assertSee('Kepala Toko Melati')
            ->assertSee('Stok Kritis atau Habis')
            ->assertSee('Detail Pesan')
            ->assertDontSee('Memuat riwayat pengiriman');
        $this->actingAs($admin)->get(route('admin.notifications.logs.index', ['channel_type' => 'whatsapp']))
            ->assertOk()
            ->assertSee('Kepala Toko Melati')
            ->assertSee('Stok Kritis atau Habis')
            ->assertSee('+62 812-3456-7890');
        $this->actingAs($admin)->get(route('admin.notifications.logs.show', $log))
            ->assertOk()
            ->assertSee('Stok Kritis atau Habis')
            ->assertSee('Kepala Toko Melati')
            ->assertSee('Stok Toko Melati hampir habis.')
            ->assertSee('Segera lakukan pemeriksaan.')
            ->assertSee('Peringatan Stok')
            ->assertSee('[DISEMBUNYIKAN]')
            ->assertDontSee('tidak-boleh-tampil');
    }

    public function test_disconnect_logs_out_clears_identity_and_returns_connect_state(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin_config');
        Http::fake([
            'wa-gateway.test/api/integrations/pos/session/status' => Http::response(['success' => true, 'data' => ['status' => 'connected', 'phone' => '628123456789', 'name' => 'GudangToko']], 200),
            'wa-gateway.test/api/integrations/pos/session' => Http::response(['success' => true, 'data' => ['status' => 'disconnected']], 200),
        ]);

        $this->actingAs($admin)->get(route('admin.whatsapp.index'))->assertOk()->assertSee('Putuskan WhatsApp');
        $this->actingAs($admin)->deleteJson(route('admin.whatsapp.disconnect'))
            ->assertOk()
            ->assertJsonPath('status', 'logged_out')
            ->assertJsonPath('phone', null)
            ->assertJsonPath('name', null);
        $this->assertDatabaseHas('whatsapp_connections', [
            'session_id' => 'gudangtoko-main',
            'status' => 'logged_out',
            'phone_number' => null,
            'account_name' => null,
        ]);
    }

    public function test_baileys_message_is_sent_and_provider_message_id_is_saved(): void
    {
        config(['notifications.dry_run' => false]);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        NotificationChannel::query()->create([
            'name' => 'WhatsApp Perusahaan',
            'channel_type' => 'whatsapp',
            'sender' => 'gudangtoko-main',
            'endpoint' => 'http://wa-gateway.test',
            'auth_type' => 'api_key',
            'credentials' => [],
            'timeout_seconds' => 10,
            'retry_attempts' => 3,
            'is_active' => true,
            'metadata' => ['provider' => 'baileys_gateway'],
        ]);
        Http::fake([
            'wa-gateway.test/api/wa/send' => Http::response(['success' => true, 'data' => ['msg_id' => 'wa-message-123']], 200),
        ]);
        $log = NotificationLog::query()->create([
            'channel_type' => 'whatsapp',
            'destination' => '0812-3456-7890',
            'body' => 'Tes koneksi',
            'status' => 'queued',
            'idempotency_key' => 'wa-provider-message-id',
            'created_by' => $admin->id,
        ]);

        (new SendNotificationJob($log->id))->handle(app(NotificationDispatchService::class));

        $this->assertDatabaseHas('notification_logs', [
            'id' => $log->id,
            'status' => 'sent',
            'provider_message_id' => 'wa-message-123',
        ]);
        Http::assertSent(fn ($request): bool => $request->data()['to'] === '6281234567890');
    }

    public function test_queue_job_throws_only_while_delivery_is_waiting_for_retry(): void
    {
        config(['notifications.dry_run' => false]);
        NotificationChannel::query()->create([
            'name' => 'WhatsApp Perusahaan',
            'channel_type' => 'whatsapp',
            'sender' => 'gudangtoko-main',
            'endpoint' => 'http://wa-gateway.test',
            'auth_type' => 'api_key',
            'credentials' => [],
            'timeout_seconds' => 10,
            'retry_attempts' => 2,
            'is_active' => true,
            'metadata' => ['provider' => 'baileys_gateway'],
        ]);
        Http::fake(['wa-gateway.test/api/wa/send' => Http::response(['error' => 'gateway down'], 500)]);
        $log = NotificationLog::query()->create([
            'channel_type' => 'whatsapp',
            'destination' => '081234567890',
            'body' => 'Tes retry',
            'status' => 'queued',
            'idempotency_key' => 'wa-retry-job',
        ]);
        $job = new SendNotificationJob($log->id);

        try {
            $job->handle(app(NotificationDispatchService::class));
            $this->fail('Percobaan pertama harus dikembalikan ke queue.');
        } catch (\RuntimeException) {
            $this->assertDatabaseHas('notification_logs', ['id' => $log->id, 'status' => 'retry', 'attempts' => 1]);
        }

        $job->handle(app(NotificationDispatchService::class));
        $this->assertDatabaseHas('notification_logs', ['id' => $log->id, 'status' => 'failed', 'attempts' => 2]);
    }

    public function test_other_role_is_forbidden_and_phone_normalization_is_strict(): void
    {
        $cashier = User::factory()->create();
        $cashier->assignRole('kasir');
        $this->actingAs($cashier)->get(route('admin.whatsapp.index'))->assertForbidden();
        $log = NotificationLog::query()->create([
            'channel_type' => 'whatsapp',
            'destination' => '081234567890',
            'body' => 'Pesan internal',
            'status' => 'queued',
            'idempotency_key' => 'wa-forbidden-detail',
        ]);
        $this->actingAs($cashier)->get(route('admin.notifications.logs.show', $log))->assertForbidden();

        $client = app(WhatsappGatewayClient::class);
        $this->assertSame('6281234567890', $client->normalizePhone('+62 812-3456-7890'));
        $this->assertSame('6281234567890', $client->normalizePhone('0812 3456 7890'));
        $this->expectException(\RuntimeException::class);
        $client->normalizePhone('nomor salah');
    }
}
