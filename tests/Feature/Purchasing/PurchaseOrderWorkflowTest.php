<?php

namespace Tests\Feature\Purchasing;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\ServiceException;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkLocation;
use App\Services\Purchasing\PurchaseOrderService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseOrderService $service;

    private User $purchasing;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->service = app(PurchaseOrderService::class);
        $this->purchasing = User::factory()->create(['is_active' => true]);
        $this->purchasing->assignRole(Role::findOrCreate('purchasing'));
        $this->approver = User::factory()->create(['is_active' => true]);
        $this->approver->assignRole(Role::findOrCreate('kepala_gudang'));
    }

    public function test_purchasing_pages_can_be_opened(): void
    {
        [$warehouse, $supplier, $product, $unit] = $this->fixture();
        $this->purchasing->workLocations()->syncWithoutDetaching([$warehouse->work_location_id => ['is_default' => true, 'is_active' => true]]);
        $purchaseOrder = $this->makePurchaseOrder($warehouse, $supplier, $product, $unit);

        $this->actingAs($this->purchasing);

        $this->get(route('purchasing.requests.index'))->assertOk()->assertSee('Permintaan Pembelian');
        $this->get(route('purchasing.purchase-orders.index'))->assertOk()->assertSee($purchaseOrder->number);
        $this->get(route('purchasing.purchase-orders.create'))->assertOk()->assertSee('Form Purchase Order');
        $this->get(route('purchasing.purchase-orders.show', $purchaseOrder))->assertOk()->assertSee('Detail dan Approval PO');
        $this->get(route('purchasing.purchase-orders.print', $purchaseOrder))->assertOk()->assertSee('Purchase Order');
    }

    public function test_total_calculation_and_conversion_snapshot_are_saved(): void
    {
        [$warehouse, $supplier, $product, $unit] = $this->fixture(conversion: '12');
        $purchaseOrder = $this->makePurchaseOrder($warehouse, $supplier, $product, $unit);
        $item = $purchaseOrder->items->firstOrFail();

        $this->assertSame('12.000000', $item->conversion_factor_snapshot);
        $this->assertSame('20100.00', $item->subtotal);
        $this->assertSame('21900.00', $purchaseOrder->grand_total);
    }

    public function test_invalid_state_transition_is_rejected(): void
    {
        [$warehouse, $supplier, $product, $unit] = $this->fixture();
        $purchaseOrder = $this->makePurchaseOrder($warehouse, $supplier, $product, $unit);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('tidak valid');

        $this->service->approve($purchaseOrder, $this->approver);
    }

    public function test_approved_po_cannot_be_edited_without_revision(): void
    {
        [$warehouse, $supplier, $product, $unit] = $this->fixture();
        $purchaseOrder = $this->makePurchaseOrder($warehouse, $supplier, $product, $unit);
        $this->service->submit($purchaseOrder, $this->purchasing);
        $approved = $this->service->approve($purchaseOrder->fresh(), $this->approver);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('tidak boleh diedit');

        $this->service->update($approved, $this->payload($warehouse, $supplier, $product, $unit), $this->purchasing);
    }

    public function test_partial_and_completed_status_are_ready_for_goods_receipt(): void
    {
        [$warehouse, $supplier, $product, $unit] = $this->fixture();
        $purchaseOrder = $this->makePurchaseOrder($warehouse, $supplier, $product, $unit);
        $this->service->submit($purchaseOrder, $this->purchasing);
        $this->service->approve($purchaseOrder->fresh(), $this->approver);
        $sent = $this->service->markSent($purchaseOrder->fresh(), $this->purchasing);
        $item = $sent->items->firstOrFail();

        $partial = $this->service->recordReceiptProgress($sent, [$item->id => '1'], $this->purchasing);
        $this->assertSame(PurchaseOrderStatus::PARTIALLY_RECEIVED, $partial->status);

        $completed = $this->service->recordReceiptProgress($partial, [$item->id => '2'], $this->purchasing);
        $this->assertSame(PurchaseOrderStatus::COMPLETED, $completed->status);
    }

    public function test_purchase_order_with_received_item_cannot_be_cancelled(): void
    {
        [$warehouse, $supplier, $product, $unit] = $this->fixture();
        $purchaseOrder = $this->makePurchaseOrder($warehouse, $supplier, $product, $unit);
        $this->service->submit($purchaseOrder, $this->purchasing);
        $approved = $this->service->approve($purchaseOrder->fresh(), $this->approver);
        $item = $approved->items->firstOrFail();
        $partial = $this->service->recordReceiptProgress($approved, [$item->id => '1'], $this->purchasing);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('tidak boleh dibatalkan');

        $this->service->cancel($partial, $this->purchasing, 'Batal setelah terima');
    }

    public function test_approval_permission_is_enforced_on_route(): void
    {
        [$warehouse, $supplier, $product, $unit] = $this->fixture();
        $this->purchasing->workLocations()->syncWithoutDetaching([$warehouse->work_location_id => ['is_default' => true, 'is_active' => true]]);
        $this->approver->workLocations()->syncWithoutDetaching([$warehouse->work_location_id => ['is_default' => true, 'is_active' => true]]);
        $purchaseOrder = $this->makePurchaseOrder($warehouse, $supplier, $product, $unit);
        $this->service->submit($purchaseOrder, $this->purchasing);

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.purchase-orders.approve', $purchaseOrder))
            ->assertForbidden();

        $this->actingAs($this->approver)
            ->post(route('purchasing.purchase-orders.approve', $purchaseOrder))
            ->assertRedirect();
    }

    public function test_document_numbers_are_unique_for_multiple_purchase_orders(): void
    {
        [$warehouse, $supplier, $product, $unit] = $this->fixture();
        $first = $this->makePurchaseOrder($warehouse, $supplier, $product, $unit);
        $second = $this->makePurchaseOrder($warehouse, $supplier, $product, $unit);

        $this->assertNotSame($first->number, $second->number);
        $this->assertDatabaseCount('purchase_orders', 2);
    }

    /** @return array{Warehouse, Supplier, Product, Unit} */
    private function fixture(string $conversion = '1'): array
    {
        $workLocation = WorkLocation::factory()->create(['type' => 'warehouse']);
        $warehouse = Warehouse::factory()->create(['work_location_id' => $workLocation->id]);
        $supplier = Supplier::factory()->create(['is_active' => true]);
        $unit = Unit::factory()->create(['code' => 'PCS']);
        $product = Product::factory()->create(['base_unit_id' => $unit->id, 'status' => 'active']);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'name' => 'Unit pembelian',
            'conversion_factor' => $conversion,
            'is_base' => true,
            'is_sellable' => true,
            'is_active' => true,
        ]);

        return [$warehouse, $supplier, $product, $unit];
    }

    private function makePurchaseOrder(Warehouse $warehouse, Supplier $supplier, Product $product, Unit $unit): PurchaseOrder
    {
        return $this->service->create($this->payload($warehouse, $supplier, $product, $unit), $this->purchasing);
    }

    /** @return array<string, mixed> */
    private function payload(Warehouse $warehouse, Supplier $supplier, Product $product, Unit $unit): array
    {
        return [
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'expected_at' => now()->addDays(3)->toDateString(),
            'payment_term_days' => 14,
            'notes' => 'PO test',
            'header_discount' => '500',
            'freight_cost' => '2000',
            'additional_cost' => '300',
            'items' => [
                [
                    'product_id' => $product->id,
                    'unit_id' => $unit->id,
                    'quantity_ordered' => '2',
                    'unit_price' => '10000',
                    'discount_amount' => '1000',
                    'tax_amount' => '1100',
                ],
            ],
        ];
    }
}
