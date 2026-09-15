<?php

namespace Tests\Feature\Retail;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\ProductRequest;
use App\Models\ProductUnit;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Retail\EmergencyPurchaseService;
use App\Services\Retail\EmergencyStockService;
use App\Services\Warehouse\GoodsReceiptService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StoreInboundWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private WorkLocation $storeLocation;

    private Warehouse $warehouse;

    private Branch $branch;

    private User $storeHead;

    private User $storeStaff;

    private User $masterAdmin;

    private User $purchaseApprover;

    private Supplier $supplier;

    private Unit $unit;

    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $warehouseLocation = WorkLocation::factory()->create(['type' => 'warehouse']);
        $this->storeLocation = WorkLocation::factory()->create(['type' => 'branch']);
        $this->warehouse = Warehouse::factory()->create(['work_location_id' => $warehouseLocation->id]);
        $this->branch = Branch::factory()->create([
            'work_location_id' => $this->storeLocation->id,
            'primary_warehouse_id' => $this->warehouse->id,
        ]);
        $this->storeHead = $this->user('kepala_toko', true);
        $this->storeStaff = $this->user('staf_toko', true);
        $this->masterAdmin = $this->user('admin_config', false);
        $this->purchaseApprover = $this->user('kepala_gudang', false);
        $this->purchaseApprover->workLocations()->sync([$this->warehouse->work_location_id => ['is_default' => true, 'is_active' => true]]);
        $this->supplier = Supplier::factory()->create(['is_active' => true]);
        $this->unit = Unit::factory()->create(['is_active' => true]);
        $this->category = ProductCategory::factory()->create(['is_active' => true]);
    }

    public function test_supplier_receipt_can_post_directly_to_regular_store_stock(): void
    {
        $product = Product::factory()->create(['base_unit_id' => $this->unit->id, 'cost_price' => '0']);
        ProductUnit::query()->create([
            'product_id' => $product->id, 'unit_id' => $this->unit->id, 'conversion_factor' => 1,
            'is_base' => true, 'is_sellable' => true, 'is_active' => true,
        ]);

        $purchaseOrders = app(PurchaseOrderService::class);
        $po = $purchaseOrders->create([
            'destination_work_location_id' => $this->storeLocation->id,
            'supplier_id' => $this->supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id, 'unit_id' => $this->unit->id,
                'quantity_ordered' => '8', 'unit_price' => '12000',
            ]],
        ], $this->storeHead);
        $purchaseOrders->submit($po, $this->storeHead);
        $this->actingAs($this->purchaseApprover)->get(route('purchasing.purchase-orders.show', $po))->assertOk();
        $purchaseOrders->approve($po->fresh(), $this->purchaseApprover);

        $po = $po->fresh('items');
        $receipt = app(GoodsReceiptService::class)->createDraft([
            'purchase_order_id' => $po->id,
            'received_at' => now()->toDateString(),
            'items' => [[
                'purchase_order_item_id' => $po->items->first()->id,
                'warehouse_location_id' => null,
                'quantity_received' => '8', 'quantity_accepted' => '8',
                'quantity_rejected' => '0', 'quantity_damaged' => '0',
                'quantity_returned_to_supplier' => '0',
            ]],
        ], $this->storeHead);
        app(GoodsReceiptService::class)->post($receipt, $this->storeHead);

        $this->assertNull($po->fresh()->warehouse_id);
        $this->assertSame($this->storeLocation->id, $receipt->destination_work_location_id);
        $this->assertSame('8.0000', Stock::query()->where('product_id', $product->id)->where('work_location_id', $this->storeLocation->id)->firstOrFail()->quantity_on_hand);
        $this->assertFalse(Stock::query()->where('product_id', $product->id)->where('work_location_id', $this->warehouse->work_location_id)->exists());
    }

    public function test_store_product_request_creates_active_master_product_after_approval(): void
    {
        $response = $this->actingAs($this->storeStaff)->post(route('retail.product-requests.store'), [
            'branch_id' => $this->branch->id,
            'name' => 'Produk Lokal Baru',
            'proposed_sku' => 'TOKO-LOCAL-01',
            'barcode' => '899000000001',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->unit->id,
            'supplier_id' => $this->supplier->id,
            'purchase_cost' => '10000',
            'proposed_selling_price' => '13000',
        ]);
        $proposal = ProductRequest::query()->firstOrFail();
        $response->assertRedirect(route('retail.product-requests.show', $proposal));

        $this->actingAs($this->masterAdmin)->post(route('retail.product-requests.approve', $proposal), [
            'notes' => 'Identitas produk sudah diperiksa.',
        ])->assertRedirect();

        $product = Product::query()->where('sku', 'TOKO-LOCAL-01')->firstOrFail();
        $this->assertSame('approved', $proposal->fresh()->status);
        $this->assertSame('active', $product->status->value);
        $this->assertDatabaseHas('product_barcodes', ['product_id' => $product->id, 'code' => '899000000001']);
        $this->assertTrue(ProductPrice::query()->where('product_id', $product->id)->where('branch_id', $this->branch->id)->exists());
    }

    public function test_store_product_request_rejects_existing_name_sku_or_barcode(): void
    {
        Product::factory()->create([
            'name' => 'Produk Sudah Ada', 'sku' => 'SKU-LAMA',
            'category_id' => $this->category->id, 'base_unit_id' => $this->unit->id,
        ]);

        $this->actingAs($this->storeStaff)->from(route('retail.product-requests.create'))->post(route('retail.product-requests.store'), [
            'branch_id' => $this->branch->id,
            'name' => 'Produk Sudah Ada',
            'proposed_sku' => 'SKU-BARU',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->unit->id,
            'purchase_cost' => '10000',
            'proposed_selling_price' => '13000',
        ])->assertRedirect(route('retail.product-requests.create'))->assertSessionHasErrors('name');

        $this->assertDatabaseCount('product_requests', 0);
    }

    public function test_free_emergency_pool_can_become_regular_store_stock(): void
    {
        $product = Product::factory()->create(['base_unit_id' => $this->unit->id, 'cost_price' => '100']);
        app(InventoryService::class)->receive($product, $this->storeLocation, null, '2', $this->storeHead, ['type' => 'opening', 'no' => 'OPEN-STORE']);
        $service = app(EmergencyPurchaseService::class);
        $purchase = $service->confirm([
            'purpose' => 'proactive_restock', 'branch_id' => $this->branch->id,
            'items' => [['product_id' => $product->id, 'unit_id' => $this->unit->id, 'quantity' => '4', 'unit_cost' => '150']],
        ], $this->storeStaff);
        $service->purchased($purchase, [
            'supplier_name' => 'Pemasok Sekitar', 'fund_source' => 'personal', 'receipt_path' => 'nota.jpg',
            'items' => [['id' => $purchase->items->first()->id, 'purchased_quantity' => '4', 'unit_cost' => '150']],
        ], $this->storeHead);

        $service->regularize($purchase->fresh(), $this->storeHead);

        $this->assertSame('6.0000', Stock::query()->where('product_id', $product->id)->where('work_location_id', $this->storeLocation->id)->firstOrFail()->quantity_on_hand);
        $this->assertSame('133.33', $product->fresh()->cost_price);
        $this->assertSame('completed', $purchase->fresh()->status);
        $this->assertSame('0.0000', app(EmergencyStockService::class)->available($this->branch, $product->id));
    }

    private function user(string $role, bool $assignedToStore): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::findOrCreate($role));
        if ($assignedToStore) {
            $user->workLocations()->sync([$this->storeLocation->id => ['is_default' => true, 'is_active' => true]]);
        }

        return $user;
    }
}
