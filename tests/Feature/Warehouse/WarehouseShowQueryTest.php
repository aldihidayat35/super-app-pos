<?php

namespace Tests\Feature\Warehouse;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\Inventory\InventoryService;
use App\Services\Organization\WorkLocationSyncService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WarehouseShowQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_handles_no_one_and_multiple_bins_without_leaking_other_warehouse_mutations(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::findOrCreate('super_admin'));
        $sync = app(WorkLocationSyncService::class);
        $inventory = app(InventoryService::class);

        $withoutBin = Warehouse::factory()->create(['name' => 'Gudang Tanpa Bin']);
        $oneBin = Warehouse::factory()->create(['name' => 'Gudang Satu Bin']);
        $manyBins = Warehouse::factory()->create(['name' => 'Gudang Banyak Bin']);
        foreach ([$withoutBin, $oneBin, $manyBins] as $warehouse) {
            $sync->syncWarehouse($warehouse);
            $warehouse->refresh();
        }

        $one = WarehouseLocation::factory()->create(['warehouse_id' => $oneBin->id, 'full_code' => 'ONE-01']);
        $manyA = WarehouseLocation::factory()->create(['warehouse_id' => $manyBins->id, 'full_code' => 'MANY-01']);
        $manyB = WarehouseLocation::factory()->create(['warehouse_id' => $manyBins->id, 'full_code' => 'MANY-02']);
        $otherProduct = Product::factory()->create(['name' => 'Produk Gudang Lain']);
        $targetProduct = Product::factory()->create(['name' => 'Produk Gudang Target']);
        $inventory->receive($otherProduct, $oneBin->workLocation, $one, '1', $user, ['type' => 'test', 'no' => 'OTHER']);
        $inventory->receive($targetProduct, $manyBins->workLocation, $manyA, '1', $user, ['type' => 'test', 'no' => 'TARGET-A']);
        $inventory->receive($targetProduct, $manyBins->workLocation, $manyB, '1', $user, ['type' => 'test', 'no' => 'TARGET-B']);

        $this->actingAs($user)->get(route('admin.warehouses.show', $withoutBin))->assertOk()->assertSee('Gudang Tanpa Bin');
        $this->get(route('admin.warehouses.show', $oneBin))->assertOk()->assertSee('Gudang Satu Bin');
        $this->get(route('admin.warehouses.show', $manyBins))->assertOk()->assertSee('Produk Gudang Target')->assertDontSee('Produk Gudang Lain');
    }
}
