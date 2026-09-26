<?php

namespace Tests\Feature\Retail;

use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetailLocationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $kepalaToko;

    private Branch $branch;

    private WorkLocation $branchWorkLocation;

    private Warehouse $warehouse;

    private WorkLocation $warehouseWorkLocation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->warehouseWorkLocation = WorkLocation::factory()->create(['type' => 'warehouse', 'name' => 'Gudang Pusat']);
        $this->warehouse = Warehouse::factory()->create(['work_location_id' => $this->warehouseWorkLocation->id, 'code' => 'GDG-PST', 'name' => 'Gudang Pusat']);

        $this->branchWorkLocation = WorkLocation::factory()->create(['type' => 'branch', 'name' => 'Toko Cabang Utama']);
        $this->branch = Branch::factory()->create([
            'work_location_id' => $this->branchWorkLocation->id,
            'primary_warehouse_id' => $this->warehouse->id,
            'code' => 'TKO-CBG',
            'name' => 'Toko Cabang Utama',
        ]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super_admin');
        $this->admin->workLocations()->attach([
            $this->warehouseWorkLocation->id => ['is_default' => true, 'is_active' => true],
            $this->branchWorkLocation->id => ['is_default' => false, 'is_active' => true],
        ]);

        $this->kepalaToko = User::factory()->create(['is_active' => true]);
        $this->kepalaToko->assignRole('kepala_toko');
        $this->kepalaToko->workLocations()->attach($this->branchWorkLocation->id, ['is_default' => true, 'is_active' => true]);
    }

    public function test_can_create_store_location_from_warehouse_locations_page(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('warehouse.locations.store'), [
            'owner_type' => 'branch',
            'branch_id' => $this->branch->id,
            'type' => 'zone',
            'code' => 'ZONA-TOKO',
            'name' => 'Zona Toko Depan',
            'capacity' => 100,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('warehouse.locations.index'));

        $this->assertDatabaseHas('warehouse_locations', [
            'branch_id' => $this->branch->id,
            'warehouse_id' => null,
            'code' => 'ZONA-TOKO',
            'full_code' => 'TKO-CBG-ZONA-TOKO',
            'name' => 'Zona Toko Depan',
        ]);
    }

    public function test_can_view_and_create_store_location_from_retail_locations_page(): void
    {
        $this->actingAs($this->kepalaToko);

        $this->get(route('retail.locations.index'))
            ->assertOk()
            ->assertSee('Lokasi Toko (Zona, Rak, Bin)');

        $this->get(route('retail.locations.create'))
            ->assertOk()
            ->assertSee('Tambah Lokasi Toko');

        $this->post(route('retail.locations.store'), [
            'branch_id' => $this->branch->id,
            'type' => 'zone',
            'code' => 'AREA-KASIR',
            'name' => 'Area Meja Kasir',
            'is_active' => '1',
        ])->assertRedirect(route('retail.locations.index'));

        $zone = WarehouseLocation::query()->where('code', 'AREA-KASIR')->firstOrFail();
        $this->assertEquals('TKO-CBG-AREA-KASIR', $zone->full_code);
        $this->assertEquals($this->branch->id, $zone->branch_id);
        $this->assertNull($zone->warehouse_id);

        // Create child rack
        $this->post(route('retail.locations.store'), [
            'branch_id' => $this->branch->id,
            'parent_id' => $zone->id,
            'type' => 'rack',
            'code' => 'RAK-01',
            'name' => 'Rak Permen Kasir',
            'is_active' => '1',
        ])->assertRedirect(route('retail.locations.index'));

        $rack = WarehouseLocation::query()->where('code', 'RAK-01')->firstOrFail();
        $this->assertEquals('TKO-CBG-AREA-KASIR-RAK-01', $rack->full_code);
        $this->assertEquals($zone->id, $rack->parent_id);
    }

    public function test_can_edit_and_deactivate_store_location(): void
    {
        $this->actingAs($this->kepalaToko);

        $location = WarehouseLocation::query()->create([
            'branch_id' => $this->branch->id,
            'type' => 'zone',
            'code' => 'ZONA-A',
            'full_code' => 'TKO-CBG-ZONA-A',
            'name' => 'Zona A Lama',
            'is_active' => true,
        ]);

        $this->get(route('retail.locations.edit', $location))
            ->assertOk()
            ->assertSee('Zona A Lama');

        $this->put(route('retail.locations.update', $location), [
            'branch_id' => $this->branch->id,
            'type' => 'zone',
            'code' => 'ZONA-A',
            'name' => 'Zona A Baru',
            'is_active' => '1',
        ])->assertRedirect(route('retail.locations.index'));

        $this->assertDatabaseHas('warehouse_locations', [
            'id' => $location->id,
            'name' => 'Zona A Baru',
        ]);

        $this->patch(route('retail.locations.deactivate', $location))
            ->assertRedirect();

        $this->assertDatabaseHas('warehouse_locations', [
            'id' => $location->id,
            'is_active' => false,
        ]);
    }

    public function test_inventory_service_accepts_store_bin_for_branch_stock(): void
    {
        $bin = WarehouseLocation::query()->create([
            'branch_id' => $this->branch->id,
            'type' => 'bin',
            'code' => 'BIN-TOKO-1',
            'full_code' => 'TKO-CBG-BIN-TOKO-1',
            'name' => 'Bin Toko 1',
            'is_active' => true,
        ]);

        $product = Product::factory()->create();

        $inventory = app(InventoryService::class);
        $mutation = $inventory->receive(
            $product,
            $this->branchWorkLocation,
            $bin,
            '10.0000',
            $this->admin,
            ['type' => 'test_receipt', 'no' => 'RCV-001']
        );

        $this->assertNotNull($mutation);
        $this->assertDatabaseHas('stocks', [
            'product_id' => $product->id,
            'work_location_id' => $this->branchWorkLocation->id,
            'warehouse_location_id' => $bin->id,
            'quantity_on_hand' => '10.0000',
        ]);
    }
}
