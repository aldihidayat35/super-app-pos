<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationLogStatus;
use App\Models\DailyReport;
use App\Models\NotificationChannel;
use App\Models\NotificationLog;
use App\Models\NotificationRecipient;
use App\Models\NotificationSchedule;
use App\Models\NotificationTemplate;
use App\Models\SecureReportToken;
use App\Models\User;
use App\Services\Notifications\NotificationDispatchService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationDailyReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole(Role::findOrCreate('admin_config'));

        $this->owner = User::factory()->create(['is_active' => true]);
        $this->owner->assignRole(Role::findOrCreate('owner_approver'));
    }

    public function test_p25_notification_pages_are_available(): void
    {
        $this->actingAs($this->admin)->get(route('admin.notifications.channels.index'))->assertOk()->assertSee('Channel WA API dan Telegram');
        $this->actingAs($this->admin)->get(route('admin.notifications.templates.index'))->assertOk()->assertSee('Template Pesan');
        $this->actingAs($this->admin)->get(route('admin.notifications.schedules.index'))->assertOk()->assertSee('Jadwal Laporan dan Alert');
        $this->actingAs($this->admin)->get(route('admin.notifications.recipients.index'))->assertOk()->assertSee('Penerima Notifikasi');
        $this->actingAs($this->admin)->get(route('admin.notifications.logs.index'))->assertOk()->assertSee('Log Pengiriman');
        $this->actingAs($this->admin)->get(route('admin.notifications.alerts.index'))->assertOk()->assertSee('Aturan Alert Bisnis');
    }

    public function test_channel_test_uses_dry_run_and_redacts_secret(): void
    {
        config(['notifications.dry_run' => true]);
        $channel = NotificationChannel::query()->create([
            'name' => 'WA Test',
            'channel_type' => 'whatsapp',
            'endpoint' => 'https://example.invalid/send',
            'auth_type' => 'bearer',
            'credentials' => ['token' => 'super-secret-token'],
            'sender' => 'local',
            'default_destination' => '628111',
            'timeout_seconds' => 5,
            'retry_attempts' => 3,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)->post(route('admin.notifications.channels.test', $channel), [
            'name' => $channel->name,
            'channel_type' => 'whatsapp',
            'endpoint' => $channel->endpoint,
            'auth_type' => 'bearer',
            'sender' => $channel->sender,
            'default_destination' => $channel->default_destination,
            'timeout_seconds' => 5,
            'retry_attempts' => 3,
            'is_active' => 1,
            'test_destination' => '628222',
            'test_message' => 'Halo test',
        ])->assertRedirect();

        $log = NotificationLog::query()->firstOrFail();
        $this->assertSame(NotificationLogStatus::SKIPPED, $log->status);
        $this->assertStringNotContainsString('super-secret-token', (string) DB::table('notification_channels')->whereKey($channel->id)->value('credentials'));
    }

    public function test_daily_report_scheduler_is_idempotent_and_creates_secure_link(): void
    {
        config(['notifications.dry_run' => true]);
        $template = $this->seedDailyNotificationFixture();

        $this->artisan('reports:send-daily', ['--date' => now('Asia/Jakarta')->toDateString(), '--sync' => true])->assertExitCode(0);
        $this->artisan('reports:send-daily', ['--date' => now('Asia/Jakarta')->toDateString(), '--sync' => true])->assertExitCode(0);

        $this->assertSame(1, DailyReport::query()->count());
        $this->assertSame(1, NotificationLog::query()->where('notification_template_id', $template->id)->count());
        $this->assertSame(2, SecureReportToken::query()->count(), 'Token baru boleh dibuat, tetapi report/log tidak boleh dobel.');
    }

    public function test_whatsapp_delivery_success_and_failure_are_logged_with_fake_http(): void
    {
        config(['notifications.dry_run' => false]);
        NotificationChannel::query()->create([
            'name' => 'WA Aktif',
            'channel_type' => 'whatsapp',
            'endpoint' => 'https://wa.test/send',
            'auth_type' => 'bearer',
            'credentials' => ['token' => 'secret-token'],
            'timeout_seconds' => 5,
            'retry_attempts' => 2,
            'is_active' => true,
        ]);

        Http::fakeSequence()
            ->push(['message_id' => 'ok-1', 'token' => 'should-redact'], 200)
            ->push(['error' => 'down'], 500);
        $log = NotificationLog::query()->create([
            'channel_type' => 'whatsapp',
            'destination' => '628111',
            'body' => 'Halo',
            'status' => 'queued',
            'idempotency_key' => 'wa-success',
        ]);
        app(NotificationDispatchService::class)->send($log);

        $log->refresh();
        $this->assertSame(NotificationLogStatus::SENT, $log->status);
        $this->assertSame('***redacted***', $log->sanitized_response['token']);

        $failed = NotificationLog::query()->create([
            'channel_type' => 'whatsapp',
            'destination' => '628111',
            'body' => 'Halo',
            'status' => 'queued',
            'idempotency_key' => 'wa-failed',
        ]);
        app(NotificationDispatchService::class)->send($failed);

        $this->assertSame(NotificationLogStatus::RETRY, $failed->refresh()->status);
        $this->assertNotNull($failed->next_retry_at);
    }

    public function test_secure_report_token_expiry_and_revocation_are_enforced(): void
    {
        $report = DailyReport::query()->create([
            'report_date' => now('Asia/Jakarta')->toDateString(),
            'period_start' => now('Asia/Jakarta')->toDateString(),
            'period_end' => now('Asia/Jakarta')->toDateString(),
            'status' => 'generated',
            'summary' => ['revenue' => '0.00'],
            'idempotency_key' => 'secure-report-test',
        ]);
        $plain = 'secure-token-test';
        $token = SecureReportToken::query()->create([
            'daily_report_id' => $report->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now('Asia/Jakarta')->addHour(),
        ]);

        $this->get(route('reports.daily.secure', ['token' => $plain]))->assertOk()->assertSee('Detail Laporan Aman');

        $token->update(['revoked_at' => now('Asia/Jakarta')]);
        $this->get(route('reports.daily.secure', ['token' => $plain]))->assertForbidden();
    }

    private function seedDailyNotificationFixture(): NotificationTemplate
    {
        NotificationChannel::query()->create([
            'name' => 'WA Dry',
            'channel_type' => 'whatsapp',
            'endpoint' => 'https://wa.test/send',
            'auth_type' => 'bearer',
            'credentials' => ['token' => 'secret-token'],
            'timeout_seconds' => 5,
            'retry_attempts' => 3,
            'is_active' => true,
        ]);

        $template = NotificationTemplate::query()->create([
            'key' => 'daily_report',
            'name' => 'Daily WA',
            'channel_type' => 'whatsapp',
            'subject' => 'Laporan {{ report_date }}',
            'body' => 'Omzet {{ revenue }} link {{ secure_link }}',
            'allowed_variables' => config('notifications.template_variables.daily_report'),
            'is_active' => true,
        ]);

        NotificationRecipient::query()->create([
            'name' => 'Owner WA',
            'recipient_type' => 'user',
            'user_id' => $this->owner->id,
            'role_name' => 'owner_approver',
            'channel_type' => 'whatsapp',
            'destination' => '628111',
            'report_type' => 'daily_report',
            'is_verified' => true,
            'is_active' => true,
        ]);

        NotificationSchedule::query()->create([
            'name' => 'Daily Owner',
            'schedule_key' => 'daily-owner-test',
            'frequency' => 'daily',
            'run_time' => '08:00',
            'timezone' => 'Asia/Jakarta',
            'report_type' => 'daily_report',
            'report_period' => 'yesterday',
            'template_id' => $template->id,
            'channel_types' => ['whatsapp'],
            'is_active' => true,
        ]);

        return $template;
    }
}
