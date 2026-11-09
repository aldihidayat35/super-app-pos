<?php

namespace Database\Seeders;

use App\Enums\EmployeeStatus;
use App\Enums\WarehouseLocationType;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\Organization\WorkLocationSyncService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder Utama Sistem GudangToko.
 *
 * Menyiapkan fondasi operasional dasar baik untuk lokal maupun server/production:
 * 1. Role & Permission RBAC lengkap (18 role, ratusan permission granular)
 * 2. Satuan Standar Sistem (PCS, LSN, GROS, BOX, PACK, SET, KG, GR, MTR)
 * 3. Kategori Master Awal (UMUM)
 * 4. Master Organisasi: Gudang Pusat (GDG-PUSAT) & Toko Utama (TKO-PUSAT) beserta Rak & Bin
 * 5. Akun Pengguna Utama untuk seluruh Role Sistem (password: `password`)
 *    beserta penugasan lokasi kerja (WorkLocation) dan record Employee.
 *
 * Tidak membuat data dummy produk, saldo stok, ataupun transaksi palsu,
 * sehingga siap langsung digunakan untuk import master data riil di server.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private const PASSWORD = 'password';

    /**
     * Akun pengguna operasional utama per role sistem.
     *
     * @var array<string, array{name: string, username: string, email: string, roles: list<string>, work_location?: string, employee_no?: string, position?: string}>
     */
    private array $accounts = [
        'super_admin' => [
            'name' => 'Super Administrator',
            'username' => 'superadmin',
            'email' => 'superadmin@gudangtoko.test',
            'roles' => ['super_admin'],
        ],
        'owner' => [
            'name' => 'Owner',
            'username' => 'owner',
            'email' => 'owner@gudangtoko.test',
            'roles' => ['owner_approver'],
        ],
        'manajemen_gudang' => [
            'name' => 'Manajemen Gudang',
            'username' => 'manajemen-gudang',
            'email' => 'manajemen-gudang@gudangtoko.test',
            'roles' => ['kepala_gudang', 'purchasing'],
            'work_location' => 'both',
            'employee_no' => 'EMP-GDG-001',
            'position' => 'Kepala Bagian Gudang',
        ],
        'staff_gudang' => [
            'name' => 'Staff Gudang',
            'username' => 'staff-gudang',
            'email' => 'staff-gudang@gudangtoko.test',
            'roles' => ['staff_gudang'],
            'work_location' => 'warehouse',
            'employee_no' => 'EMP-GDG-002',
            'position' => 'Staff Operasional Gudang',
        ],
        'toko_internal' => [
            'name' => 'Kepala Toko',
            'username' => 'toko-internal',
            'email' => 'toko@gudangtoko.test',
            'roles' => ['kepala_toko'],
            'work_location' => 'branch',
            'employee_no' => 'EMP-TKO-001',
            'position' => 'Kepala Toko / Cabang',
        ],
        'staf_toko' => [
            'name' => 'Staf Toko',
            'username' => 'staf-toko',
            'email' => 'staf-toko@gudangtoko.test',
            'roles' => ['staf_toko'],
            'work_location' => 'branch',
            'employee_no' => 'EMP-TKO-002',
            'position' => 'Staf Pramuniaga',
        ],
        'kasir_kepala_toko' => [
            'name' => 'Kasir',
            'username' => 'kasir',
            'email' => 'kasir@gudangtoko.test',
            'roles' => ['kasir', 'kepala_toko'],
            'work_location' => 'branch',
            'employee_no' => 'EMP-TKO-003',
            'position' => 'Kasir Toko Utama',
        ],
        'langganan_b2b' => [
            'name' => 'Langganan / B2B',
            'username' => 'langganan-b2b',
            'email' => 'langganan-b2b@gudangtoko.test',
            'roles' => ['langganan_owner'],
        ],
        'akun_pelanggan' => [
            'name' => 'Akun Pelanggan',
            'username' => 'pelanggan',
            'email' => 'pelanggan@gudangtoko.test',
            'roles' => ['langganan_staff'],
        ],
    ];

    public function run(): void
    {
        // 1. Inisialisasi Role & Permission RBAC
        $this->call(RolePermissionSeeder::class);

        // 2. Inisialisasi Satuan Standar
        $this->call(UnitSeeder::class);

        // 3. Inisialisasi Kategori Standar
        ProductCategory::query()->updateOrCreate(
            ['code' => 'UMUM'],
            [
                'name' => 'Kategori Umum',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        // 4. Inisialisasi Master Lokasi Kerja (Gudang Pusat & Toko Utama + Rak/Bin)
        $sync = app(WorkLocationSyncService::class);

        $warehouse = Warehouse::query()->updateOrCreate(
            ['code' => 'GDG-PUSAT'],
            [
                'name' => 'Gudang Pusat',
                'address' => 'Jl. Pergudangan Utama No. 1',
                'city' => 'Jakarta',
                'phone_number' => '021-7700001',
                'capacity' => 10000,
                'service_area' => 'Utama',
                'is_active' => true,
            ],
        );
        $warehouseLocation = $sync->syncWarehouse($warehouse);

        $branch = Branch::query()->updateOrCreate(
            ['code' => 'TKO-PUSAT'],
            [
                'primary_warehouse_id' => $warehouse->id,
                'name' => 'Toko Utama',
                'address' => 'Jl. Pertokoan Utama No. 1',
                'phone_number' => '021-7700002',
                'sales_target' => 25000000,
                'price_configuration' => 'standard',
                'closing_configuration' => 'daily',
                'is_closing_required' => true,
                'is_active' => true,
            ],
        );
        $branchLocation = $sync->syncBranch($branch);

        // Rak / Bin Default Gudang
        $zoneWarehouse = WarehouseLocation::query()->updateOrCreate(
            ['warehouse_id' => $warehouse->id, 'code' => 'ZONA-A'],
            [
                'parent_id' => null,
                'type' => WarehouseLocationType::ZONE,
                'full_code' => "{$warehouse->code}-ZONA-A",
                'name' => 'Zona Utama A',
                'capacity' => 1000,
                'is_active' => true,
            ],
        );
        $rackWarehouse = WarehouseLocation::query()->updateOrCreate(
            ['warehouse_id' => $warehouse->id, 'code' => 'RAK-01'],
            [
                'parent_id' => $zoneWarehouse->id,
                'type' => WarehouseLocationType::RACK,
                'full_code' => "{$warehouse->code}-ZONA-A-RAK-01",
                'name' => 'Rak Utama 01',
                'capacity' => 500,
                'is_active' => true,
            ],
        );
        WarehouseLocation::query()->updateOrCreate(
            ['warehouse_id' => $warehouse->id, 'code' => 'BIN-01'],
            [
                'parent_id' => $rackWarehouse->id,
                'type' => WarehouseLocationType::BIN,
                'full_code' => "{$warehouse->code}-ZONA-A-RAK-01-BIN-01",
                'name' => 'Bin Utama 01',
                'capacity' => 100,
                'is_active' => true,
            ],
        );

        // Rak / Bin Default Toko
        $zoneBranch = WarehouseLocation::query()->updateOrCreate(
            ['branch_id' => $branch->id, 'code' => 'ZONA-DISPLAY'],
            [
                'parent_id' => null,
                'type' => WarehouseLocationType::ZONE,
                'full_code' => "{$branch->code}-ZONA-DISPLAY",
                'name' => 'Zona Display Depan',
                'capacity' => 1000,
                'is_active' => true,
            ],
        );
        $rackBranch = WarehouseLocation::query()->updateOrCreate(
            ['branch_id' => $branch->id, 'code' => 'RAK-DPN'],
            [
                'parent_id' => $zoneBranch->id,
                'type' => WarehouseLocationType::RACK,
                'full_code' => "{$branch->code}-ZONA-DISPLAY-RAK-DPN",
                'name' => 'Rak Display Utama',
                'capacity' => 500,
                'is_active' => true,
            ],
        );
        WarehouseLocation::query()->updateOrCreate(
            ['branch_id' => $branch->id, 'code' => 'BIN-KASIR'],
            [
                'parent_id' => $rackBranch->id,
                'type' => WarehouseLocationType::BIN,
                'full_code' => "{$branch->code}-ZONA-DISPLAY-RAK-DPN-BIN-KASIR",
                'name' => 'Bin Display Kasir',
                'capacity' => 100,
                'is_active' => true,
            ],
        );

        // 5. Inisialisasi Akun Pengguna & Otoritas Lokasi Kerja
        foreach (array_values($this->accounts) as $index => $account) {
            $user = User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'username' => $account['username'],
                    'phone_number' => '628'.str_pad((string) (100000000 + $index), 9, '0', STR_PAD_LEFT),
                    'password' => self::PASSWORD,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles(array_map(
                static fn (string $roleName): Role => Role::findOrCreate($roleName),
                $account['roles'],
            ));

            // Penugasan WorkLocation
            $workLocationType = $account['work_location'] ?? null;
            if ($workLocationType === 'warehouse') {
                $user->workLocations()->syncWithoutDetaching([
                    $warehouseLocation->id => ['is_default' => true, 'is_active' => true],
                ]);
            } elseif ($workLocationType === 'branch') {
                $user->workLocations()->syncWithoutDetaching([
                    $branchLocation->id => ['is_default' => true, 'is_active' => true],
                ]);
            } elseif ($workLocationType === 'both') {
                $user->workLocations()->syncWithoutDetaching([
                    $warehouseLocation->id => ['is_default' => true, 'is_active' => true],
                    $branchLocation->id => ['is_default' => false, 'is_active' => true],
                ]);
            }

            // Record Employee untuk staf operasional
            if (! empty($account['employee_no'])) {
                $primaryLocationId = match ($workLocationType) {
                    'warehouse', 'both' => $warehouseLocation->id,
                    'branch' => $branchLocation->id,
                    default => null,
                };

                if ($primaryLocationId) {
                    Employee::query()->updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'employee_no' => $account['employee_no'],
                            'work_location_id' => $primaryLocationId,
                            'name' => $user->name,
                            'position' => $account['position'] ?? 'Staff',
                            'whatsapp_number' => $user->phone_number,
                            'joined_at' => now()->subMonths(1)->toDateString(),
                            'status' => EmployeeStatus::ACTIVE,
                            'is_active' => true,
                        ],
                    );
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
