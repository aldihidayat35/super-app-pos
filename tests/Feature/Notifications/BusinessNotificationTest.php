<?php

namespace Tests\Feature\Notifications;

use App\Enums\WorkChecklistFrequency;
use App\Enums\WorkChecklistItemStatus;
use App\Enums\WorkChecklistStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Branch;
use App\Models\BusinessNotificationSetting;
use App\Models\NotificationLog;
use App\Models\User;
use App\Models\WorkChecklist;
use App\Models\WorkLocation;
use App\Services\Notifications\BusinessNotificationService;
use App\Services\WorkChecklist\WorkChecklistService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BusinessNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Queue::fake();
    }

    public function test_owner_can_manage_business_notification_status(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner_approver');
        $setting = BusinessNotificationSetting::query()->where('event_key', 'critical_stock')->firstOrFail();

        $this->actingAs($owner)
            ->get(route('admin.notifications.business-settings.index'))
            ->assertOk()
            ->assertSee('Stok Kritis atau Habis');

        $this->actingAs($owner)->put(route('admin.notifications.business-settings.update', $setting), [
            'is_active' => '0',
            'cooldown_minutes' => 120,
        ])->assertRedirect();

        $this->assertDatabaseHas('business_notification_settings', [
            'event_key' => 'critical_stock',
            'is_active' => false,
            'cooldown_minutes' => 120,
            'updated_by' => $owner->id,
        ]);
    }

    public function test_event_respects_location_and_prevents_duplicate_subject_messages(): void
    {
        $firstLocation = WorkLocation::factory()->create(['type' => 'branch']);
        $secondLocation = WorkLocation::factory()->create(['type' => 'branch']);
        $firstHead = $this->locationUser('kepala_toko', $firstLocation, '081234567801');
        $this->locationUser('kepala_toko', $secondLocation, '081234567802');

        $service = app(BusinessNotificationService::class);
        $this->assertSame(1, $service->send('critical_stock', 'Stok Habis', 'Produk A habis.', $firstLocation->id, subjectId: 99));
        $this->assertSame(0, $service->send('critical_stock', 'Stok Habis', 'Produk A habis.', $firstLocation->id, subjectId: 99));

        $this->assertDatabaseCount('notification_logs', 1);
        $this->assertDatabaseHas('notification_logs', ['destination' => $firstHead->phone_number, 'status' => 'queued']);
        Queue::assertPushed(SendNotificationJob::class, 1);
    }

    public function test_nightly_owner_report_contains_store_dashboard_links(): void
    {
        $owner = User::factory()->create(['phone_number' => '081234567803']);
        $owner->assignRole('owner_viewer');
        $location = WorkLocation::factory()->create(['type' => 'branch', 'name' => 'Toko Pusat']);
        $branch = Branch::factory()->create(['work_location_id' => $location->id, 'name' => 'Toko Pusat']);

        app(BusinessNotificationService::class)->ownerReport('nightly');

        $log = NotificationLog::query()->firstOrFail();
        $this->assertStringContainsString('Dashboard toko:', $log->body);
        $this->assertStringContainsString(route('retail.dashboard', ['branch_id' => $branch->id]), $log->body);
        $this->assertStringContainsString(route('reports.daily.index', ['report_scope' => 'all']), $log->body);
    }

    public function test_daily_head_checklist_queues_owner_report_for_its_location(): void
    {
        $owner = User::factory()->create(['phone_number' => '081234567804']);
        $owner->assignRole('owner_approver');
        $location = WorkLocation::factory()->create(['type' => 'branch']);
        $head = $this->locationUser('kepala_toko', $location, '081234567805');
        $checklist = WorkChecklist::query()->create([
            'user_id' => $head->id,
            'work_location_id' => $location->id,
            'scope_key' => 'location:'.$location->id,
            'frequency' => WorkChecklistFrequency::DAILY,
            'period_start' => now('Asia/Jakarta')->toDateString(),
            'period_end' => now('Asia/Jakarta')->toDateString(),
            'due_at' => now('Asia/Jakarta')->endOfDay(),
            'status' => WorkChecklistStatus::OPEN,
            'role_snapshot' => ['kepala_toko'],
            'template_snapshot' => [],
        ]);
        $checklist->items()->create([
            'item_key' => 'close-store',
            'label' => 'Periksa penutupan toko',
            'role_snapshot' => ['kepala_toko'],
            'is_required' => true,
            'status' => WorkChecklistItemStatus::DONE,
            'responded_at' => now(),
        ]);

        app(WorkChecklistService::class)->complete($checklist, $head, Request::create('/checklist-kerja', 'POST'));

        $this->assertDatabaseHas('notification_logs', [
            'destination' => $owner->phone_number,
            'subject' => 'Checklist Kepala Lokasi Selesai',
            'status' => 'queued',
        ]);
    }

    public function test_connected_page_displays_disconnect_as_primary_action(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin_config');
        config()->set('whatsapp.gateway', ['enabled' => true, 'base_url' => 'http://wa-gateway.test', 'api_key' => 'secret', 'session_id' => 'gudangtoko-main', 'timeout' => 10]);
        Http::fake(['wa-gateway.test/*' => Http::response(['success' => true, 'data' => ['status' => 'connected', 'phone' => '628123456789']], 200)]);

        $this->actingAs($admin)
            ->get(route('admin.whatsapp.index'))
            ->assertOk()
            ->assertSee('Putuskan WhatsApp')
            ->assertSee('data-wa-action="disconnect"', false);
    }

    private function locationUser(string $role, WorkLocation $location, string $phone): User
    {
        $user = User::factory()->create(['phone_number' => $phone]);
        $user->assignRole($role);
        $user->workLocations()->attach($location->id, ['is_default' => true, 'is_active' => true]);

        return $user;
    }
}
