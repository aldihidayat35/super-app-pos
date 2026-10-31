<?php

namespace Tests\Feature\Warehouse;

use App\Enums\StockTransferStatus;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationTransferTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    private User $warehouseStaff;

    private WorkLocation $warehouseWorkLocation;

    private WarehouseLocation $sourceBin;

    private WarehouseLocation $destBin;

    private Product $productA;

    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->warehouseWorkLocation = WorkLocation::query()->create([
            'code' => 'WHLOC01',
            'name' => 'Gudang Pusat',
            'type' => 'warehouse',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::query()->create([
            'work_location_id' => $this->warehouseWorkLocation->id,
            'code' => 'WH01',
            'name' => 'Gudang Induk',
            'is_active' => true,
        ]);

        $this->sourceBin = WarehouseLocation::query()->create([
            'warehouse_id' => $this->warehouse->id,
            'code' => 'RAK-A',
            'name' => 'Rak A',
            'full_code' => 'RAK-A',
            'is_active' => true,
        ]);

        $this->destBin = WarehouseLocation::query()->create([
            'warehouse_id' => $this->warehouse->id,
            'code' => 'RAK-B',
            'name' => 'Rak B',
            'full_code' => 'RAK-B',
            'is_active' => true,
        ]);

        $baseUnit = Unit::factory()->create([
            'code' => 'PCS',
            'name' => 'Pieces',
            'symbol' => 'Pcs',
            'is_active' => true,
        ]);

        $this->productA = Product::factory()->create([
            'sku' => 'PRD-A',
            'name' => 'Produk A',
            'base_unit_id' => $baseUnit->id,
            'status' => 'active',
        ]);

        $this->productB = Product::factory()->create([
            'sku' => 'PRD-B',
            'name' => 'Produk B',
            'base_unit_id' => $baseUnit->id,
            'status' => 'active',
        ]);

        $this->warehouseStaff = User::factory()->create(['is_active' => true]);
        $this->warehouseStaff->assignRole('staff_gudang');
        $this->warehouseStaff->workLocations()->attach($this->warehouseWorkLocation->id);

        /** @var InventoryService $inventory */
        $inventory = app(InventoryService::class);
        $inventory->receive(
            product: $this->productA,
            workLocation: $this->warehouseWorkLocation,
            warehouseLocation: $this->sourceBin,
            quantity: 50,
            actor: $this->warehouseStaff,
            reference: ['type' => 'opening', 'no' => 'INIT-A'],
            reason: 'Saldo awal produk A',
        );
        $inventory->receive(
            product: $this->productB,
            workLocation: $this->warehouseWorkLocation,
            warehouseLocation: $this->sourceBin,
            quantity: 30,
            actor: $this->warehouseStaff,
            reference: ['type' => 'opening', 'no' => 'INIT-B'],
            reason: 'Saldo awal produk B',
        );
    }

    public function test_pindah_rak_internal_page_loads_cleanly(): void
    {
        $response = $this->actingAs($this->warehouseStaff)->get(route('warehouse.location-transfers.index'));

        $response->assertOk();
        $response->assertSee('Pindah Rak / Bin Internal');
        $response->assertSee($this->warehouseStaff->name);
    }

    public function test_pindah_rak_internal_multi_item_executes_atomically_without_approval(): void
    {
        $response = $this->actingAs($this->warehouseStaff)->post(route('warehouse.location-transfers.store'), [
            'source_work_location_id' => $this->warehouseWorkLocation->id,
            'destination_work_location_id' => $this->warehouseWorkLocation->id,
            'source_warehouse_location_id' => $this->sourceBin->id,
            'destination_warehouse_location_id' => $this->destBin->id,
            'reason' => 'Pindah display rak',
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 10],
                ['product_id' => $this->productB->id, 'quantity' => 5],
            ],
        ]);

        $response->assertRedirect(route('warehouse.location-transfers.index'));
        $response->assertSessionHas('notification');

        // Verifikasi mutasi stok produk A
        $sourceStockA = Stock::query()->where('product_id', $this->productA->id)->where('warehouse_location_id', $this->sourceBin->id)->first();
        $destStockA = Stock::query()->where('product_id', $this->productA->id)->where('warehouse_location_id', $this->destBin->id)->first();
        $this->assertEquals(40.0, (float) $sourceStockA?->quantity_on_hand);
        $this->assertEquals(10.0, (float) $destStockA?->quantity_on_hand);

        // Verifikasi mutasi stok produk B
        $sourceStockB = Stock::query()->where('product_id', $this->productB->id)->where('warehouse_location_id', $this->sourceBin->id)->first();
        $destStockB = Stock::query()->where('product_id', $this->productB->id)->where('warehouse_location_id', $this->destBin->id)->first();
        $this->assertEquals(25.0, (float) $sourceStockB?->quantity_on_hand);
        $this->assertEquals(5.0, (float) $destStockB?->quantity_on_hand);

        // Verifikasi actor tercatat sebagai penanggung jawab
        $mutation = StockMutation::query()->where('product_id', $this->productA->id)->latest('id')->first();
        $this->assertEquals($this->warehouseStaff->id, $mutation?->actor_user_id);
    }

    public function test_transfer_antar_cabang_direct_ship_bypasses_approval(): void
    {
        $branchWorkLocation = WorkLocation::query()->create([
            'code' => 'BRLOC01',
            'name' => 'Toko Cabang 1',
            'type' => 'branch',
            'is_active' => true,
        ]);
        Branch::query()->create([
            'work_location_id' => $branchWorkLocation->id,
            'primary_warehouse_id' => $this->warehouse->id,
            'code' => 'BR01',
            'name' => 'Cabang 1',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->warehouseStaff)->post(route('warehouse.stock-transfers.store'), [
            'source_work_location_id' => $this->warehouseWorkLocation->id,
            'destination_work_location_id' => $branchWorkLocation->id,
            'transfer_date' => now()->toDateString(),
            'action' => 'ship',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity_requested' => 15,
                    'source_warehouse_location_id' => $this->sourceBin->id,
                ],
            ],
        ]);

        $transfer = StockTransfer::query()->latest('id')->first();
        $this->assertNotNull($transfer);
        $this->assertEquals(StockTransferStatus::SHIPPED, $transfer->status);
        $this->assertEquals($this->warehouseStaff->id, $transfer->shipper_by);
        $this->assertNotNull($transfer->shipped_at);

        // Stok sumber langsung dipotong
        $sourceStockA = Stock::query()->where('product_id', $this->productA->id)->where('warehouse_location_id', $this->sourceBin->id)->first();
        $this->assertEquals(35.0, (float) $sourceStockA?->quantity_on_hand);
    }
}
