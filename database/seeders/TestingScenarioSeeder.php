<?php

namespace Database\Seeders;

use App\Models\AlertRule;
use App\Models\NotificationChannel;
use App\Models\NotificationRecipient;
use App\Models\NotificationSchedule;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TestingScenarioSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 403, 'Seeder scenario hanya boleh dijalankan pada environment local/testing.');

        $this->call(RolePermissionSeeder::class);

        $accounts = [
            'super_admin' => ['Testing Super Admin', 'testing-admin', 'testing-admin@gudangtoko.test'],
            'owner_approver' => ['Testing Owner Approver', 'testing-owner', 'testing-owner@gudangtoko.test'],
            'kepala_gudang' => ['Testing Kepala Gudang', 'testing-gudang', 'testing-gudang@gudangtoko.test'],
            'staff_gudang' => ['Testing Staff Gudang', 'testing-staff-gudang', 'testing-staff-gudang@gudangtoko.test'],
            'purchasing' => ['Testing Purchasing', 'testing-purchasing', 'testing-purchasing@gudangtoko.test'],
            'kepala_toko' => ['Testing Kepala Toko', 'testing-retail', 'testing-retail@gudangtoko.test'],
            'kasir' => ['Testing Kasir', 'testing-kasir', 'testing-kasir@gudangtoko.test'],
            'langganan_owner' => ['Testing Langganan Owner', 'testing-b2b-owner', 'testing-b2b-owner@gudangtoko.test'],
        ];

        foreach ($accounts as $roleName => [$name, $username, $email]) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'username' => $username,
                    'password' => 'password',
                    'is_active' => true,
                    'email_verified_at' => now('Asia/Jakarta'),
                ],
            );
            $user->syncRoles([Role::findOrCreate($roleName)]);
        }

        $owner = User::query()->where('email', 'testing-owner@gudangtoko.test')->first();
        NotificationChannel::query()->updateOrCreate(
            ['name' => 'Testing WA Dry Run', 'channel_type' => 'whatsapp'],
            [
                'endpoint' => 'https://example.invalid/testing-wa',
                'auth_type' => 'bearer',
                'credentials' => ['token' => 'testing-only-secret'],
                'sender' => 'testing-session',
                'default_destination' => '628000000000',
                'timeout_seconds' => 5,
                'retry_attempts' => 3,
                'is_active' => true,
                'created_by' => $owner?->id,
            ],
        );

        $template = NotificationTemplate::query()->updateOrCreate(
            ['key' => 'daily_report', 'channel_type' => 'whatsapp', 'version' => 1],
            [
                'name' => 'Testing Daily Report WA',
                'subject' => 'Testing Daily {{ report_date }}',
                'body' => 'Testing report {{ report_date }} omzet {{ revenue }} detail {{ secure_link }}',
                'allowed_variables' => config('notifications.template_variables.daily_report'),
                'is_active' => true,
                'created_by' => $owner?->id,
            ],
        );

        NotificationRecipient::query()->updateOrCreate(
            ['destination' => '628000000000', 'channel_type' => 'whatsapp', 'report_type' => 'daily_report'],
            [
                'name' => 'Testing Owner WA',
                'recipient_type' => 'user',
                'user_id' => $owner?->id,
                'role_name' => 'owner_approver',
                'is_verified' => true,
                'is_active' => true,
            ],
        );

        NotificationSchedule::query()->updateOrCreate(
            ['schedule_key' => 'testing-daily-owner'],
            [
                'name' => 'Testing Daily Owner',
                'frequency' => 'daily',
                'run_time' => '08:00',
                'timezone' => 'Asia/Jakarta',
                'report_type' => 'daily_report',
                'report_period' => 'yesterday',
                'template_id' => $template->id,
                'channel_types' => ['whatsapp'],
                'is_active' => true,
            ],
        );

        AlertRule::query()->updateOrCreate(
            ['rule_key' => 'testing-critical-stock'],
            [
                'name' => 'Testing Stok Kritis',
                'alert_type' => 'critical_stock',
                'severity' => 'high',
                'threshold_value' => '5.0000',
                'cooldown_minutes' => 30,
                'channel_types' => ['whatsapp'],
                'is_active' => true,
            ],
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
