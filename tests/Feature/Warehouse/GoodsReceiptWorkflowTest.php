<?php

namespace Tests\Feature\Warehouse;

use App\Enums\GoodsReceiptStatus;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\ServiceException;
use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\ReceiptQcResult;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Warehouse\GoodsReceiptService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoodsReceiptWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private GoodsReceiptService $receipts;

    private PurchaseOrderService $purchaseOrders;

    private InventoryService $inventory;

    private User $purchasing;

    private User $warehouseStaff;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->receipts = app(GoodsReceiptService::class);
        $this->purchaseOrders = app(PurchaseOrderService::class);
        $this->inventory = app(InventoryService::class);

        $this->purchasing = User::factory()->create(['is_active' => true]);
        $this->purchasing->assignRole(Role::findOrCreate('purchasing'));
        $this->warehouseStaff = User::factory()->create(['is_active' => true]);
        $this->warehouseStaff->assignRole(Role::findOrCreate('staff_gudang'));
        $this->approver = User::factory()->create(['is_active' => true]);
        $this->approver->assignRole(Role::findOrCreate('kepala_gudang'));
    }

    public function test_p11_pages_can_be_opened(): void
    {
        [$warehouse, $supplier, $product, $unit, $bin] = $this->fixture();
        $this->assignLocationScope($warehouse);
        $po = $this->makeSentPurchaseOrder($warehouse, $supplier, $product, $unit, '4', '120');
        $receipt = $this->receipts->createDraft($this->receiptPayload($po, $bin, accepted: '2'), $this->warehouseStaff);

        $this->actingAs($this->warehouseStaff);

        $this->get(route('warehouse.goods-receipts.index'))->assertOk()->assertSee('Daftar Penerimaan Barang')->assertSee($receipt->number);
        $this->get(route('warehouse.goods-receipts.create', ['purchase_order_id' => $po->id]))->assertOk()->assertSee('Form Penerimaan dan QC');
        $this->get(route('warehouse.goods-receipts.show', $receipt))->assertOk()->assertSee('Detail Penerimaan Barang');
        $this->get(route('warehouse.goods-receipts.print', $receipt))->assertOk()->assertSee('Berita Penerimaan Barang');
        $this->get(route('pricing.hpp-history.index'))->assertOk()->assertSee('Histori HPP dan Harga Supplier');
        $this->get(route('reports.suppliers.index'))->assertOk()->assertSee('Performa Supplier');
    }

    public function test_partial_receipt_updates_stock_po_status_batch_qc_and_supplier_score(): void
    {
        [$warehouse, $supplier, $product, $unit, $bin] = $this->fixture();
        $this->assignLocationScope($warehouse);
        $po = $this->makeSentPurchaseOrder($warehouse, $supplier, $product, $unit, '10', '100');
        $receipt = $this->receipts->createDraft($this->receiptPayload($po, $bin, accepted: '4'), $this->warehouseStaff);

        $posted = $this->receipts->post($receipt, $this->warehouseStaff);

        $this->assertSame(GoodsReceiptStatus::POSTED, $posted->status);
        $this->assertSame(PurchaseOrderStatus::PARTIALLY_RECEIVED, $po->fresh()->status);
        $this->assertSame('4.0000', $po->items()->firstOrFail()->quantity_received);
        $this->assertSame('4.0000', Stock::query()->where('product_id', $product->id)->firstOrFail()->quantity_on_hand);
        $this->assertDatabaseHas('stock_mutations', ['reference_type' => 'goods_receipt', 'reference_id' => $receipt->id, 'mutation_type' => 'receive']);
        $this->assertDatabaseHas('stock_batches', ['goods_receipt_id' => $receipt->id, 'product_id' => $product->id]);
        $this->assertDatabaseHas('receipt_qc_results', ['qc_status' => 'accepted', 'quantity' => '4.0000']);
        $this->assertDatabaseHas('supplier_scores', ['goods_receipt_id' => $receipt->id, 'quality_score' => '100.00']);
    }

    public function test_duplicate_posting_is_idempotent_and_does_not_double_mutate_stock(): void
    {
        [$warehouse, $supplier, $product, $unit, $bin] = $this->fixture();
        $this->assignLocationScope($warehouse);
        $po = $this->makeSentPurchaseOrder($warehouse, $supplier, $product, $unit, '5', '100');
        $receipt = $this->receipts->createDraft($this->receiptPayload($po, $bin, accepted: '5'), $this->warehouseStaff);

        $this->receipts->post($receipt, $this->warehouseStaff);
        $firstMutationCount = StockMutation::query()->count();

        $this->receipts->post($receipt->fresh(), $this->warehouseStaff);

        $this->assertSame($firstMutationCount, StockMutation::query()->count());
        $this->assertSame('5.0000', Stock::query()->where('product_id', $product->id)->firstOrFail()->quantity_on_hand);
    }

    public function test_landed_cost_allocation_and_moving_average_hpp_are_recorded(): void
    {
        [$warehouse, $supplier, $product, $unit, $bin] = $this->fixture(costPrice: '10');
        $this->assignLocationScope($warehouse);
        $this->inventory->receive($product, $warehouse->workLocation, $bin, '10', $this->warehouseStaff, ['type' => 'opening', 'no' => 'OPEN-1']);
        $po = $this->makeSentPurchaseOrder($warehouse, $supplier, $product, $unit, '10', '20');
        $receipt = $this->receipts->createDraft($this->receiptPayload($po, $bin, accepted: '10', freight: '100'), $this->warehouseStaff);

        $this->receipts->post($receipt, $this->warehouseStaff);
        $history = ProductCostHistory::query()->firstOrFail();

        $this->assertSame('10.00', $history->hpp_before);
        $this->assertSame('300.00', $history->incoming_cost);
        $this->assertSame('100.00', $history->landed_cost_allocated);
        $this->assertSame('20.00', $history->hpp_after);
        $this->assertSame('20.00', $product->fresh()->cost_price);
    }

    public function test_rejected_item_is_recorded_without_stock_or_po_received_qty(): void
    {
        [$warehouse, $supplier, $product, $unit, $bin] = $this->fixture();
        $this->assignLocationScope($warehouse);
        $po = $this->makeSentPurchaseOrder($warehouse, $supplier, $product, $unit, '5', '100');
        $receipt = $this->receipts->createDraft($this->receiptPayload($po, $bin, received: '5', accepted: '0', rejected: '5'), $this->warehouseStaff);

        $this->receipts->post($receipt, $this->warehouseStaff);

        $this->assertDatabaseCount('stocks', 0);
        $this->assertDatabaseCount('stock_mutations', 0);
        $this->assertSame('0.0000', $po->items()->firstOrFail()->quantity_received);
        $this->assertSame(PurchaseOrderStatus::SENT_TO_SUPPLIER, $po->fresh()->status);
        $this->assertSame('rejected', ReceiptQcResult::query()->firstOrFail()->qc_status->value);
    }

    public function test_over_receipt_is_rejected_and_rolls_back_document(): void
    {
        [$warehouse, $supplier, $product, $unit, $bin] = $this->fixture();
        $this->assignLocationScope($warehouse);
        $po = $this->makeSentPurchaseOrder($warehouse, $supplier, $product, $unit, '5', '100');

        try {
            $this->receipts->createDraft($this->receiptPayload($po, $bin, received: '6', accepted: '6'), $this->warehouseStaff);
            $this->fail('Over-receipt seharusnya ditolak.');
        } catch (ServiceException $exception) {
            $this->assertStringContainsString('melebihi outstanding', $exception->getMessage());
        }

        $this->assertDatabaseCount('goods_receipts', 0);
        $this->assertDatabaseCount('goods_receipt_items', 0);
        $this->assertDatabaseCount('stock_mutations', 0);
    }

    public function test_second_partial_receipt_keeps_po_partial_until_completed(): void
    {
        [$warehouse, $supplier, $product, $unit, $bin] = $this->fixture();
        $this->assignLocationScope($warehouse);
        $po = $this->makeSentPurchaseOrder($warehouse, $supplier, $product, $unit, '10', '100');

        $first = $this->receipts->createDraft($this->receiptPayload($po, $bin, accepted: '3'), $this->warehouseStaff);
        $this->receipts->post($first, $this->warehouseStaff);
        $this->assertSame(PurchaseOrderStatus::PARTIALLY_RECEIVED, $po->fresh()->status);

        $second = $this->receipts->createDraft($this->receiptPayload($po->fresh(), $bin, accepted: '2'), $this->warehouseStaff);
        $this->receipts->post($second, $this->warehouseStaff);

        $this->assertSame(PurchaseOrderStatus::PARTIALLY_RECEIVED, $po->fresh()->status);
        $this->assertSame('5.0000', $po->items()->firstOrFail()->quantity_received);
    }

    /** @return array{Warehouse, Supplier, Product, Unit, WarehouseLocation} */
    private function fixture(string $costPrice = '0'): array
    {
        $workLocation = WorkLocation::factory()->create(['type' => 'warehouse']);
        $warehouse = Warehouse::factory()->create(['work_location_id' => $workLocation->id]);
        $supplier = Supplier::factory()->create(['is_active' => true]);
        $unit = Unit::factory()->create(['code' => 'PCS']);
        $product = Product::factory()->create(['base_unit_id' => $unit->id, 'status' => 'active', 'cost_price' => $costPrice]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'name' => 'PCS',
            'conversion_factor' => '1',
            'is_base' => true,
            'is_sellable' => true,
            'is_active' => true,
        ]);
        $bin = WarehouseLocation::factory()->create(['warehouse_id' => $warehouse->id]);

        return [$warehouse, $supplier, $product, $unit, $bin];
    }

    private function assignLocationScope(Warehouse $warehouse): void
    {
        foreach ([$this->purchasing, $this->warehouseStaff, $this->approver] as $user) {
            $user->workLocations()->syncWithoutDetaching([$warehouse->work_location_id => ['is_default' => true, 'is_active' => true]]);
        }
    }

    private function makeSentPurchaseOrder(Warehouse $warehouse, Supplier $supplier, Product $product, Unit $unit, string $qty, string $price): PurchaseOrder
    {
        $po = $this->purchaseOrders->create([
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'expected_at' => now()->addDays(2)->toDateString(),
            'payment_term_days' => 7,
            'items' => [[
                'product_id' => $product->id,
                'unit_id' => $unit->id,
                'quantity_ordered' => $qty,
                'unit_price' => $price,
                'discount_amount' => '0',
                'tax_amount' => '0',
            ]],
        ], $this->purchasing);

        $this->purchaseOrders->submit($po, $this->purchasing);
        $this->purchaseOrders->approve($po->fresh(), $this->approver);

        return $this->purchaseOrders->markSent($po->fresh(), $this->purchasing);
    }

    /** @return array<string, mixed> */
    private function receiptPayload(PurchaseOrder $po, WarehouseLocation $bin, string $received = '', string $accepted = '1', string $rejected = '0', string $damaged = '0', string $freight = '0'): array
    {
        $po->loadMissing('items');
        $item = $po->items->firstOrFail();
        $received = $received !== '' ? $received : $accepted;

        return [
            'purchase_order_id' => $po->id,
            'received_at' => now()->toDateString(),
            'delivery_note_number' => 'SJ-TEST',
            'actual_freight_cost' => $freight,
            'actual_additional_cost' => '0',
            'notes' => 'Receipt test',
            'items' => [[
                'purchase_order_item_id' => $item->id,
                'warehouse_location_id' => $bin->id,
                'quantity_received' => $received,
                'quantity_accepted' => $accepted,
                'quantity_rejected' => $rejected,
                'quantity_damaged' => $damaged,
                'quantity_returned_to_supplier' => '0',
                'batch_no' => 'BATCH-TEST',
                'qc_notes' => 'QC test',
            ]],
        ];
    }
}
