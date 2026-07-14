<?php

namespace Tests\Feature\EndToEnd;

use App\Enums\CashShiftStatus;
use App\Enums\GoodsReceiptStatus;
use App\Enums\PosSaleStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockTransferStatus;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Retail\CashShiftService;
use App\Services\Retail\PosService;
use App\Services\Warehouse\GoodsReceiptService;
use App\Services\Warehouse\StockTransferService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CriticalBusinessJourneyTest extends TestCase
{
    use RefreshDatabase;

    private User $purchasing;

    private User $warehouseHead;

    private User $warehouseStaff;

    private User $cashier;

    private User $storeHead;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->purchasing = $this->userWithRole('purchasing');
        $this->warehouseHead = $this->userWithRole('kepala_gudang');
        $this->warehouseStaff = $this->userWithRole('staff_gudang');
        $this->cashier = $this->userWithRole('kasir');
        $this->storeHead = $this->userWithRole('kepala_toko');
    }

    public function test_e2e_purchase_receipt_transfer_pos_and_closing_reconcile_stock_and_cash(): void
    {
        [$warehouse, $branch, $supplier, $product, $unit, $bin] = $this->scenarioFixture();
        $po = $this->approvedAndSentPo($warehouse, $supplier, $product, $unit, '10', '50');
        $receipt = app(GoodsReceiptService::class)->createDraft([
            'purchase_order_id' => $po->id,
            'received_at' => now('Asia/Jakarta')->toDateString(),
            'delivery_note_number' => 'SJ-E2E-001',
            'actual_freight_cost' => '100',
            'actual_additional_cost' => '0',
            'items' => [[
                'purchase_order_item_id' => $po->items()->firstOrFail()->id,
                'warehouse_location_id' => $bin->id,
                'quantity_received' => '10',
                'quantity_accepted' => '10',
                'quantity_rejected' => '0',
                'quantity_damaged' => '0',
                'quantity_returned_to_supplier' => '0',
                'batch_no' => 'E2E-BATCH-001',
                'qc_notes' => 'Diterima baik.',
            ]],
        ], $this->warehouseStaff);

        $postedReceipt = app(GoodsReceiptService::class)->post($receipt, $this->warehouseStaff);
        $this->assertSame(GoodsReceiptStatus::POSTED, $postedReceipt->status);
        $this->assertSame(PurchaseOrderStatus::COMPLETED, $po->fresh()->status);
        $this->assertSame('60.00', $product->fresh()->cost_price, 'HPP moving average menyerap freight 100 untuk 10 pcs.');

        $transfer = app(StockTransferService::class)->create([
            'source_work_location_id' => $warehouse->work_location_id,
            'source_warehouse_location_id' => $bin->id,
            'destination_work_location_id' => $branch->work_location_id,
            'transfer_date' => now('Asia/Jakarta')->toDateString(),
            'action' => 'submit',
            'items' => [[
                'product_id' => $product->id,
                'quantity_requested' => '4',
                'quantity_approved' => '4',
                'source_warehouse_location_id' => $bin->id,
            ]],
        ], $this->warehouseStaff);
        $transfer = app(StockTransferService::class)->approve($transfer, $this->warehouseHead);
        $transfer = app(StockTransferService::class)->pack($transfer, ['items' => [$transfer->items->firstOrFail()->id => ['quantity_picked' => '4']]], $this->warehouseStaff);
        $transfer = app(StockTransferService::class)->ship($transfer, ['carrier' => 'Internal'], $this->warehouseStaff);
        $transfer = app(StockTransferService::class)->receive($transfer, [
            'idempotency_key' => 'e2e-transfer-receive',
            'items' => [$transfer->items->firstOrFail()->id => ['quantity_received' => '4', 'quantity_damaged' => '0', 'quantity_discrepancy' => '0']],
        ], $this->storeHead);
        $transfer = app(StockTransferService::class)->complete($transfer, $this->storeHead);
        $this->assertSame(StockTransferStatus::COMPLETED, $transfer->status);

        $shift = CashShift::query()->create([
            'number' => 'SHIFT-E2E-001',
            'branch_id' => $branch->id,
            'work_location_id' => $branch->work_location_id,
            'cashier_user_id' => $this->cashier->id,
            'opened_by' => $this->cashier->id,
            'status' => CashShiftStatus::OPEN->value,
            'opening_cash_amount' => '100.00',
            'expected_cash_amount' => '100.00',
            'opened_at' => now('Asia/Jakarta'),
        ]);
        $sale = app(PosService::class)->checkout([
            'branch_id' => $branch->id,
            'idempotency_key' => 'e2e-pos-checkout',
            'items' => [['product_id' => $product->id, 'unit_id' => $unit->id, 'quantity' => '2']],
            'payments' => [['method' => 'cash', 'amount' => '240']],
        ], $this->cashier);
        $this->assertSame(PosSaleStatus::COMPLETED, $sale->status);

        $warehouseStock = Stock::query()->where('product_id', $product->id)->where('work_location_id', $warehouse->work_location_id)->firstOrFail();
        $branchStock = Stock::query()->where('product_id', $product->id)->where('work_location_id', $branch->work_location_id)->firstOrFail();
        $this->assertSame('6.0000', $warehouseStock->quantity_on_hand);
        $this->assertSame('2.0000', $branchStock->quantity_on_hand);
        $this->assertSame('0.0000', $branchStock->quantity_reserved);
        $this->assertSame(6, StockMutation::query()->where('product_id', $product->id)->count());

        $summary = app(CashShiftService::class)->summary($shift->fresh());
        $this->assertSame('240.00', $summary['cash_sales']);
        $this->assertSame('340.00', $summary['expected_cash']);
        app(CashShiftService::class)->submitClosing($shift->fresh(), ['actual_cash_amount' => '340.00'], $this->cashier);
        app(CashShiftService::class)->approve($shift->fresh(), $this->storeHead, 'E2E closing sesuai.');
        $this->assertSame(CashShiftStatus::CLOSED, $shift->fresh()->status);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::findOrCreate($role));

        return $user;
    }

    /** @return array{Warehouse, Branch, Supplier, Product, Unit, WarehouseLocation} */
    private function scenarioFixture(): array
    {
        $warehouseLocation = WorkLocation::factory()->create(['type' => 'warehouse', 'code' => 'GDG-E2E']);
        $branchLocation = WorkLocation::factory()->create(['type' => 'branch', 'code' => 'TKO-E2E']);
        $warehouse = Warehouse::factory()->create(['work_location_id' => $warehouseLocation->id]);
        $branch = Branch::factory()->create(['work_location_id' => $branchLocation->id, 'primary_warehouse_id' => $warehouse->id]);
        $supplier = Supplier::factory()->create(['is_active' => true]);
        $unit = Unit::factory()->create(['code' => 'PCS-E2E', 'name' => 'PCS E2E']);
        $product = Product::factory()->create([
            'base_unit_id' => $unit->id,
            'status' => 'active',
            'cost_price' => '0.00',
            'minimum_price' => '0.00',
            'minimum_stock' => '1.0000',
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'name' => 'PCS',
            'conversion_factor' => '1.000000',
            'is_base' => true,
            'is_sellable' => true,
            'is_active' => true,
        ]);
        PriceRule::query()->create([
            'name' => 'E2E Retail Rule',
            'channel' => 'all',
            'margin_method' => 'percent',
            'minimum_margin_percent' => '20.00',
            'minimum_margin_amount' => '0.00',
            'overpricing_tolerance_percent' => '100.00',
            'max_discount_percent' => '10.00',
            'priority' => 1,
            'is_active' => true,
        ]);
        $bin = WarehouseLocation::factory()->create(['warehouse_id' => $warehouse->id]);

        foreach ([$this->purchasing, $this->warehouseHead, $this->warehouseStaff] as $user) {
            $user->workLocations()->syncWithoutDetaching([$warehouseLocation->id => ['is_default' => true, 'is_active' => true]]);
        }
        $this->warehouseHead->workLocations()->syncWithoutDetaching([$branchLocation->id => ['is_default' => false, 'is_active' => true]]);
        foreach ([$this->cashier, $this->storeHead] as $user) {
            $user->workLocations()->syncWithoutDetaching([$branchLocation->id => ['is_default' => true, 'is_active' => true]]);
        }

        return [$warehouse, $branch, $supplier, $product, $unit, $bin];
    }

    private function approvedAndSentPo(Warehouse $warehouse, Supplier $supplier, Product $product, Unit $unit, string $quantity, string $price): PurchaseOrder
    {
        $service = app(PurchaseOrderService::class);
        $po = $service->create([
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'order_date' => now('Asia/Jakarta')->toDateString(),
            'expected_at' => now('Asia/Jakarta')->addDay()->toDateString(),
            'payment_term_days' => 7,
            'items' => [[
                'product_id' => $product->id,
                'unit_id' => $unit->id,
                'quantity_ordered' => $quantity,
                'unit_price' => $price,
                'discount_amount' => '0',
                'tax_amount' => '0',
            ]],
        ], $this->purchasing);

        $service->submit($po, $this->purchasing);
        $service->approve($po->fresh(), $this->warehouseHead);

        return $service->markSent($po->fresh(), $this->purchasing)->fresh(['items']);
    }
}
