<?php

namespace Tests\Feature\Control;

use App\Enums\AnomalyStatus;
use App\Enums\ApprovalRequestStatus;
use App\Exceptions\ServiceException;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\Control\AnomalyDetectionService;
use App\Services\Control\ApprovalWorkflowService;
use App\Services\Control\AuditLogService;
use App\Services\Pricing\PriceManagementService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApprovalAuditAnomalyTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private User $approver;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->requester = User::factory()->create(['is_active' => true]);
        $this->requester->assignRole(Role::findOrCreate('admin_config'));
        $this->approver = User::factory()->create(['is_active' => true]);
        $this->approver->assignRole(Role::findOrCreate('owner_approver'));
        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole(Role::findOrCreate('kasir'));
    }

    public function test_p23_pages_are_available_for_approver_and_auditor(): void
    {
        $approval = $this->approval();
        app(AuditLogService::class)->record('test.event', 'control', $this->requester, $approval, [], ['safe' => true]);
        app(AnomalyDetectionService::class)->flag($approval, 'test_rule', 'Alert Test', 'Anomali untuk pengujian.', 'medium', '120000.00');

        $this->actingAs($this->approver)->get(route('approvals.index'))->assertOk()->assertSee('Kotak Masuk Approval');
        $this->actingAs($this->approver)->get(route('approvals.show', $approval))->assertOk()->assertSee('Detail Approval');
        $this->actingAs($this->approver)->get(route('audit-logs.index'))->assertOk()->assertSee('Audit Log');
        $this->actingAs($this->approver)->get(route('audit.anomalies.index'))->assertOk()->assertSee('Dashboard Anomali');
        $this->actingAs($this->approver)->get(route('audit.security.index'))->assertOk()->assertSee('Log Login dan Keamanan');
    }

    public function test_self_approval_is_blocked(): void
    {
        $selfApprover = User::factory()->create(['is_active' => true]);
        $selfApprover->assignRole(Role::findOrCreate('owner_approver'));
        $approval = $this->approval($selfApprover);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Requester tidak boleh menyetujui permintaannya sendiri.');

        app(ApprovalWorkflowService::class)->approve($approval, $selfApprover, 'Tidak boleh.');
    }

    public function test_duplicate_approve_is_idempotent(): void
    {
        $approval = $this->approval();
        $service = app(ApprovalWorkflowService::class);

        $first = $service->approve($approval, $this->approver, 'Disetujui.');
        $second = $service->approve($approval, $this->approver, 'Klik ulang.');

        $this->assertSame(ApprovalRequestStatus::APPROVED, $first->current_status);
        $this->assertSame(ApprovalRequestStatus::APPROVED, $second->current_status);
        $this->assertSame(1, AuditLog::query()->where('event', 'approval.approved')->where('subject_id', $approval->subject_id)->count());
    }

    public function test_expired_request_cannot_be_approved(): void
    {
        $approval = $this->approval();
        $approval->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Approval sudah kedaluwarsa.');

        app(ApprovalWorkflowService::class)->approve($approval, $this->approver, 'Terlambat.');
    }

    public function test_audit_log_redacts_sensitive_values(): void
    {
        $log = app(AuditLogService::class)->record('security.test', 'security', $this->approver, $this->approver, [], [
            'password' => 'secret',
            'api_token' => 'token-rahasia',
            'nested' => ['attachment_path' => 'private/file.pdf', 'name' => 'Aman'],
        ]);

        $this->assertSame('[REDACTED]', $log->new_values['password']);
        $this->assertSame('[REDACTED]', $log->new_values['api_token']);
        $this->assertSame('[REDACTED]', $log->new_values['nested']['attachment_path']);
        $this->assertSame('Aman', $log->new_values['nested']['name']);
    }

    public function test_user_without_audit_permission_cannot_open_audit_log(): void
    {
        $this->actingAs($this->cashier)->get(route('audit-logs.index'))->assertForbidden();
    }

    public function test_pricing_request_creates_generic_approval_and_anomaly(): void
    {
        [$product, $branch, $customer] = $this->pricingFixture();

        app(PriceManagementService::class)->saveCustomerOverride([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'channel' => 'b2b',
            'price' => '11000.00',
            'minimum_qty' => 1,
            'discount_percent' => 0,
            'starts_at' => now()->toDateString(),
            'reason' => 'Harga kontrak harus direview.',
        ], $this->requester);

        $this->assertDatabaseHas('approval_requests', [
            'module' => 'pricing',
            'handler_key' => 'pricing.approval',
            'current_status' => ApprovalRequestStatus::PENDING->value,
        ]);
        $this->assertDatabaseHas('anomaly_alerts', [
            'rule_key' => 'pricing_sensitive',
            'status' => AnomalyStatus::OPEN->value,
        ]);
    }

    public function test_anomaly_can_be_resolved_with_note(): void
    {
        $alert = app(AnomalyDetectionService::class)->flag($this->approval(), 'manual_rule', 'Manual Alert', 'Perlu dicek.', 'high', '250000.00');

        $this->actingAs($this->approver)->post(route('audit.anomalies.resolve', $alert), [
            'status' => AnomalyStatus::RESOLVED->value,
            'resolution_note' => 'Sudah diverifikasi.',
        ])->assertRedirect();

        $this->assertSame(AnomalyStatus::RESOLVED, $alert->fresh()->status);
        $this->assertSame('Sudah diverifikasi.', $alert->fresh()->resolution_note);
    }

    public function test_handler_rollback_keeps_approval_pending_when_business_action_fails(): void
    {
        $approval = $this->approval();

        try {
            app(ApprovalWorkflowService::class)->approve($approval, $this->approver, 'Coba approve.', function (): void {
                throw new RuntimeException('Simulasi handler gagal.');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulasi handler gagal.', $exception->getMessage());
        }

        $this->assertSame(ApprovalRequestStatus::PENDING, $approval->fresh()->current_status);
        $this->assertSame(ApprovalRequestStatus::PENDING, $approval->steps()->firstOrFail()->fresh()->status);
    }

    private function approval(?User $requester = null): ApprovalRequest
    {
        return app(ApprovalWorkflowService::class)->create(
            subject: $this->approver,
            type: 'sensitive_action',
            module: 'control',
            requester: $requester ?? $this->requester,
            riskValue: '150000.00',
            reason: 'Pengujian approval sensitif.',
            before: ['status' => 'draft'],
            after: ['status' => 'approved'],
        );
    }

    /** @return array{0: Product, 1: Branch, 2: Customer} */
    private function pricingFixture(): array
    {
        $product = Product::factory()->create([
            'base_unit_id' => Unit::factory()->create()->id,
            'cost_price' => '10000.00',
            'minimum_price' => '0.00',
        ]);
        $branch = Branch::factory()->create();
        $customer = Customer::factory()->create(['price_category' => 'grosir']);
        PriceRule::query()->create([
            'name' => 'Default Test Rule',
            'channel' => 'all',
            'margin_method' => 'percent',
            'minimum_margin_percent' => '20.00',
            'minimum_margin_amount' => '0.00',
            'overpricing_tolerance_percent' => '50.00',
            'max_discount_percent' => '10.00',
            'approval_threshold_amount' => '0.00',
            'priority' => 100,
            'is_active' => true,
        ]);

        return [$product, $branch, $customer];
    }
}
