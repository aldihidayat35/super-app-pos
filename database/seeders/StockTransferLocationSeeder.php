<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\Inventory\InventoryService;
use App\Services\Organization\WorkLocationSyncService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class StockTransferLocationSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 403, 'Seeder lokasi transfer hanya boleh dijalankan pada local/testing.');

        $this->call(RolePermissionSeeder::class);

        DB::transaction(function (): void {
            $sync = app(WorkLocationSyncService::class);
            $warehouse = $this->warehouse($sync);
            $branch = $this->branch($sync, $warehouse);
            $bin = $this->locations($warehouse);

            $this->assignDemoUsers($warehouse, $branch);
            $this->moveBranchDemoStockIntoBin($branch, $bin);
        });
    }

    private function warehouse(WorkLocationSyncService $sync): Warehouse
    {
        $warehouse = Warehouse::query()->updateOrCreate(
            ['code' => 'GDG-DEMO'],
            [
                'name' => 'Gudang Demo Utama',
                'address' => 'Jl. Gudang Demo No. 1',
                'city' => 'Jakarta',
                'phone_number' => '021-7700001',
                'capacity' => 10000,
                'service_area' => 'Demo',
                'is_active' => true,
            ],
        );

        $sync->syncWarehouse($warehouse);

        return $warehouse->fresh('workLocation');
    }

    private function branch(WorkLocationSyncService $sync, Warehouse $warehouse): Branch
    {
        $branch = Branch::query()->updateOrCreate(
            ['code' => 'TKO-DEMO'],
            [
                'primary_warehouse_id' => $warehouse->id,
                'name' => 'Toko Demo Pusat',
                'address' => 'Jl. Toko Demo No. 1',
                'phone_number' => '021-7700002',
                'sales_target' => 25000000,
                'price_configuration' => 'standard',
                'closing_configuration' => 'daily',
                'is_closing_required' => true,
                'is_active' => true,
            ],
        );

        $sync->syncBranch($branch);

        return $branch->fresh(['workLocation', 'primaryWarehouse']);
    }

    private function locations(Warehouse $warehouse): WarehouseLocation
    {
        $zone = WarehouseLocation::query()->updateOrCreate(
            ['warehouse_id' => $warehouse->id, 'code' => 'ZONA-A'],
            [
                'parent_id' => null,
                'type' => 'zone',
                'full_code' => "{$warehouse->code}-ZONA-A",
                'name' => 'Zona A Demo',
                'capacity' => 1000,
                'is_active' => true,
            ],
        );

        $rack = WarehouseLocation::query()->updateOrCreate(
            ['warehouse_id' => $warehouse->id, 'code' => 'RAK-01'],
            [
                'parent_id' => $zone->id,
                'type' => 'rack',
                'full_code' => "{$warehouse->code}-ZONA-A-RAK-01",
                'name' => 'Rak 01 Demo',
                'capacity' => 500,
                'is_active' => true,
            ],
        );

        return WarehouseLocation::query()->updateOrCreate(
            ['warehouse_id' => $warehouse->id, 'code' => 'BIN-01'],
            [
                'parent_id' => $rack->id,
                'type' => 'bin',
                'full_code' => "{$warehouse->code}-ZONA-A-RAK-01-BIN-01",
                'name' => 'Bin 01 Demo',
                'capacity' => 250,
                'is_active' => true,
            ],
        );
    }

    private function assignDemoUsers(Warehouse $warehouse, Branch $branch): void
    {
        $assignments = [
            'staff-gudang' => [$warehouse->work_location_id, ['staff_gudang']],
            'manajemen-gudang' => [$warehouse->work_location_id, ['kepala_gudang', 'purchasing']],
            'toko-internal' => [$branch->work_location_id, ['kepala_toko']],
            'kasir' => [$branch->work_location_id, ['kasir', 'kepala_toko']],
        ];

        foreach ($assignments as $username => [$workLocationId, $roles]) {
            $user = User::query()->where('username', $username)->first();

            if (! $user) {
                continue;
            }

            $user->forceFill(['is_active' => true])->save();
            foreach ($roles as $role) {
                $user->assignRole(Role::findOrCreate($role));
            }
            $user->workLocations()->syncWithoutDetaching([
                $workLocationId => ['is_default' => true, 'is_active' => true],
            ]);
        }
    }

    private function moveBranchDemoStockIntoBin(Branch $branch, WarehouseLocation $bin): void
    {
        if (! $branch->workLocation) {
            return;
        }

        $actor = User::query()->where('username', 'toko-internal')->first()
            ?? User::query()->where('username', 'staff-gudang')->first();
        $inventory = app(InventoryService::class);

        Stock::query()
            ->with('product')
            ->where('work_location_id', $branch->work_location_id)
            ->whereNull('warehouse_location_id')
            ->whereRaw('(quantity_on_hand - quantity_reserved - quantity_damaged) > 0')
            ->get()
            ->each(function (Stock $stock) use ($branch, $bin, $actor, $inventory): void {
                if (! $stock->product instanceof Product) {
                    return;
                }

                $inventory->transferInternal(
                    product: $stock->product,
                    sourceWorkLocation: $branch->workLocation,
                    sourceWarehouseLocation: null,
                    destinationWorkLocation: $branch->workLocation,
                    destinationWarehouseLocation: $bin,
                    quantity: (string) $stock->available_quantity,
                    actor: $actor,
                    reference: ['type' => 'stock_transfer_location_seed', 'no' => 'SEED-STOCK-TRANSFER-LOC'],
                    reason: 'Penempatan stok demo cabang ke bin untuk form transfer stok.',
                    idempotencyKey: "seed-stock-transfer-location-{$branch->work_location_id}-{$stock->product_id}-{$bin->id}",
                );
            });
    }
}
