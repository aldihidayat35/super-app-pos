<?php

namespace Tests\Feature\System;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole(Role::findOrCreate('admin_config'));

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole(Role::findOrCreate('kasir'));
    }

    #[Test]
    public function admin_health_page_requires_permission_and_does_not_show_secrets(): void
    {
        $this->actingAs($this->cashier)
            ->get(route('admin.system.health'))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('admin.system.health'))
            ->assertOk()
            ->assertSee('Kesehatan Sistem')
            ->assertSee('database')
            ->assertSee('backup')
            ->assertDontSee('APP_KEY')
            ->assertDontSee('DB_PASSWORD');
    }

    #[Test]
    public function web_responses_include_security_headers(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Content-Security-Policy-Report-Only');
    }

    #[Test]
    public function encrypted_backup_command_can_validate_configuration_without_dumping_database(): void
    {
        config([
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.database' => 'gudangtoko_test',
            'database.connections.mysql.username' => 'root',
            'security.backup.disk' => 'local',
            'security.backup.path' => 'private/backups',
        ]);

        $this->artisan('system:encrypted-backup --connection=mysql --dry-run')
            ->expectsOutput('Konfigurasi backup terenkripsi valid. Dry-run selesai tanpa membuat file backup.')
            ->assertExitCode(0);
    }

    #[Test]
    public function security_audit_page_filters_by_event_severity_user_ip_and_date(): void
    {
        AuditLog::query()->create([
            'actor_user_id' => $this->admin->id,
            'event' => 'auth.login_failed',
            'module' => 'security',
            'route_name' => 'login.store',
            'http_method' => 'POST',
            'ip_address' => '10.10.10.1',
            'user_agent_hash' => hash('sha256', 'test-agent'),
            'new_values' => ['username' => 'admin@example.test', 'password' => '[REDACTED]'],
            'severity' => 'warning',
            'occurred_at' => now(),
        ]);

        AuditLog::query()->create([
            'actor_user_id' => $this->cashier->id,
            'event' => 'auth.login_success',
            'module' => 'security',
            'ip_address' => '10.10.10.2',
            'new_values' => ['username' => 'cashier@example.test'],
            'severity' => 'info',
            'occurred_at' => now()->subDays(2),
        ]);

        $this->actingAs($this->admin)
            ->get(route('audit.security.index', [
                'event' => 'auth.login_failed',
                'severity' => 'warning',
                'user_id' => $this->admin->id,
                'ip_address' => '10.10.10.1',
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('auth.login_failed')
            ->assertSee('10.10.10.1')
            ->assertSee('[REDACTED]')
            ->assertDontSee('10.10.10.2');
    }

    #[Test]
    public function user_without_audit_permission_cannot_open_security_audit(): void
    {
        $this->actingAs($this->cashier)
            ->get(route('audit.security.index'))
            ->assertForbidden();
    }

    #[Test]
    public function expired_signed_payment_proof_url_is_rejected_before_file_access(): void
    {
        $payment = Payment::query()->create([
            'number' => 'PAY-TEST-SEC-001',
            'customer_id' => Customer::factory()->create()->id,
            'method' => 'bank_transfer',
            'status' => 'pending_verification',
            'amount' => '10000.00',
            'payment_date' => now()->toDateString(),
            'proof_path' => 'private/proofs/not-needed.pdf',
        ]);

        $expiredUrl = URL::temporarySignedRoute('payments.proof', now()->subMinute(), ['payment' => $payment]);

        $this->actingAs($this->admin)
            ->get($expiredUrl)
            ->assertForbidden();
    }
}
