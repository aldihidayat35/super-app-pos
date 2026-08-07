<?php

namespace Tests\Feature\Purchasing;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkLocation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $warehouseStaff;

    private User $approver;

    private User $purchasing;

    private Warehouse $warehouse;

    private Product $product;

    private Unit $unit;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $workLocation = WorkLocation::factory()->create(['type' => 'warehouse']);
        $this->warehouse = Warehouse::factory()->create(['work_location_id' => $workLocation->id, 'is_active' => true]);
        $this->unit = Unit::factory()->create(['is_active' => true]);
        $this->product = Product::factory()->create(['base_unit_id' => $this->unit->id, 'status' => 'active']);
        $this->supplier = Supplier::factory()->create(['is_active' => true]);

        $this->warehouseStaff = $this->userWithRole('staff_gudang');
        $this->approver = $this->userWithRole('kepala_gudang');
        $this->purchasing = $this->userWithRole('purchasing');

        foreach ([$this->warehouseStaff, $this->approver, $this->purchasing] as $user) {
            $user->workLocations()->syncWithoutDetaching([$workLocation->id => ['is_default' => true, 'is_active' => true]]);
        }
    }

    public function test_warehouse_staff_can_open_page_and_submit_purchase_request(): void
    {
        $this->actingAs($this->warehouseStaff)
            ->get(route('purchasing.requests.index'))
            ->assertOk()
            ->assertSee('Buat Permintaan Internal')
            ->assertSee('Rekomendasi untuk kebutuhan internal gudang');

        $this->actingAs($this->warehouseStaff)
            ->post(route('purchasing.requests.store'), $this->payload())
            ->assertRedirect(route('purchasing.requests.index'));

        $purchaseRequest = PurchaseRequest::query()->sole();
        $this->assertSame(PurchaseRequestStatus::SUBMITTED, $purchaseRequest->status);
        $this->assertSame($this->warehouseStaff->id, $purchaseRequest->requester_user_id);
        $this->assertDatabaseHas('purchase_request_items', [
            'purchase_request_id' => $purchaseRequest->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);
        $this->assertDatabaseHas('document_status_histories', [
            'document_type' => 'purchase_request',
            'document_id' => $purchaseRequest->id,
            'to_status' => PurchaseRequestStatus::SUBMITTED->value,
        ]);
    }

    public function test_user_without_purchasing_or_stock_permission_is_denied(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get(route('purchasing.requests.index'))->assertForbidden();
        $this->actingAs($user)->post(route('purchasing.requests.store'), $this->payload())->assertForbidden();
    }

    public function test_purchase_request_cannot_be_created_for_unpermitted_work_location(): void
    {
        $otherLocation = WorkLocation::factory()->create(['type' => 'warehouse']);
        $otherWarehouse = Warehouse::factory()->create(['work_location_id' => $otherLocation->id, 'is_active' => true]);

        $this->actingAs($this->warehouseStaff)
            ->post(route('purchasing.requests.store'), $this->payload(['warehouse_id' => $otherWarehouse->id]))
            ->assertSessionHasErrors('warehouse_id');

        $this->assertDatabaseCount('purchase_requests', 0);
    }

    public function test_approval_and_rejection_follow_permission_and_status(): void
    {
        $purchaseRequest = $this->createRequest();

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.requests.approve', $purchaseRequest))
            ->assertForbidden();

        $this->actingAs($this->approver)
            ->post(route('purchasing.requests.approve', $purchaseRequest))
            ->assertRedirect();

        $this->assertSame(PurchaseRequestStatus::APPROVED, $purchaseRequest->fresh()->status);

        $this->actingAs($this->approver)
            ->post(route('purchasing.requests.reject', $purchaseRequest), ['reason' => 'Terlambat'])
            ->assertForbidden();
    }

    public function test_rejection_requires_reason_and_is_audited(): void
    {
        $purchaseRequest = $this->createRequest();

        $this->actingAs($this->approver)
            ->post(route('purchasing.requests.reject', $purchaseRequest))
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->approver)
            ->post(route('purchasing.requests.reject', $purchaseRequest), ['reason' => 'Kebutuhan belum terverifikasi.'])
            ->assertRedirect();

        $this->assertSame(PurchaseRequestStatus::REJECTED, $purchaseRequest->fresh()->status);
        $this->assertDatabaseHas('document_status_histories', [
            'document_type' => 'purchase_request',
            'document_id' => $purchaseRequest->id,
            'to_status' => PurchaseRequestStatus::REJECTED->value,
            'notes' => 'Kebutuhan belum terverifikasi.',
        ]);
    }

    public function test_convert_requires_approved_status_and_active_supplier(): void
    {
        $purchaseRequest = $this->createRequest();

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.requests.convert', $purchaseRequest), ['supplier_id' => $this->supplier->id])
            ->assertForbidden();

        $this->approve($purchaseRequest);

        $this->actingAs($this->warehouseStaff)
            ->post(route('purchasing.requests.convert', $purchaseRequest), ['supplier_id' => $this->supplier->id])
            ->assertForbidden();

        $inactiveSupplier = Supplier::factory()->create(['is_active' => false]);

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.requests.convert', $purchaseRequest), ['supplier_id' => $inactiveSupplier->id])
            ->assertSessionHasErrors('supplier_id');

        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_convert_creates_related_draft_po_with_supplier_and_items_only_once(): void
    {
        $purchaseRequest = $this->createRequest();
        $this->approve($purchaseRequest);

        $response = $this->actingAs($this->purchasing)
            ->post(route('purchasing.requests.convert', $purchaseRequest), ['supplier_id' => $this->supplier->id]);

        $purchaseOrder = PurchaseOrder::query()->sole();
        $response->assertRedirect(route('purchasing.purchase-orders.show', $purchaseOrder));
        $this->assertSame(PurchaseOrderStatus::DRAFT, $purchaseOrder->status);
        $this->assertSame($this->supplier->id, $purchaseOrder->supplier_id);
        $this->assertSame($this->warehouse->id, $purchaseOrder->warehouse_id);
        $this->assertSame($purchaseRequest->id, $purchaseOrder->purchase_request_id);
        $this->assertSame('5.0000', $purchaseOrder->items->sole()->quantity_ordered);

        $purchaseRequest->refresh();
        $this->assertSame(PurchaseRequestStatus::CONVERTED, $purchaseRequest->status);
        $this->assertSame($purchaseOrder->id, $purchaseRequest->converted_purchase_order_id);

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.requests.convert', $purchaseRequest), ['supplier_id' => $this->supplier->id])
            ->assertForbidden();

        $this->assertDatabaseCount('purchase_orders', 1);
    }

    public function test_purchase_request_detail_respects_work_location_scope(): void
    {
        $purchaseRequest = $this->createRequest();
        $otherLocation = WorkLocation::factory()->create(['type' => 'warehouse']);
        $otherPurchasing = $this->userWithRole('purchasing');
        $otherPurchasing->workLocations()->sync([$otherLocation->id => ['is_default' => true, 'is_active' => true]]);

        $this->actingAs($this->purchasing)
            ->get(route('purchasing.requests.show', $purchaseRequest))
            ->assertOk()
            ->assertSee($purchaseRequest->number)
            ->assertSee('Posisi Proses');

        $this->actingAs($otherPurchasing)
            ->get(route('purchasing.requests.show', $purchaseRequest))
            ->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::findOrCreate($role));

        return $user;
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'warehouse_id' => $this->warehouse->id,
            'priority' => 'normal',
            'reason' => 'Stok operasional menipis.',
            'items' => [[
                'product_id' => $this->product->id,
                'unit_id' => $this->unit->id,
                'quantity' => '5',
                'reason' => 'Kebutuhan mingguan.',
            ]],
        ], $overrides);
    }

    private function createRequest(): PurchaseRequest
    {
        $this->actingAs($this->warehouseStaff)
            ->post(route('purchasing.requests.store'), $this->payload())
            ->assertRedirect();

        return PurchaseRequest::query()->sole();
    }

    private function approve(PurchaseRequest $purchaseRequest): void
    {
        $this->actingAs($this->approver)
            ->post(route('purchasing.requests.approve', $purchaseRequest))
            ->assertRedirect();
    }
}
