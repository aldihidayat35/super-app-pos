<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder akun administrasi untuk local/testing.
 *
 * Nama class dipertahankan untuk kompatibilitas perintah dan dokumentasi lama.
 * Tidak membuat gudang, cabang, produk, stok, supplier, pelanggan, ataupun
 * transaksi demo. Semua akun di bawah dapat login dengan password `password`.
 */
class DemoFullApplicationSeeder extends Seeder
{
    private const PASSWORD = 'password';

    /** @var array<string, array{name: string, username: string, email: string, roles: list<string>}> */
    private array $accounts = [
        'owner' => ['name' => 'Owner', 'username' => 'owner', 'email' => 'owner@gudangtoko.test', 'roles' => ['owner_approver']],
        'super_admin' => ['name' => 'Super Admin', 'username' => 'superadmin', 'email' => 'superadmin@gudangtoko.test', 'roles' => ['super_admin']],
        'manajemen_gudang' => ['name' => 'Manajemen Gudang', 'username' => 'manajemen-gudang', 'email' => 'manajemen-gudang@gudangtoko.test', 'roles' => ['kepala_gudang', 'purchasing']],
        'staff_gudang' => ['name' => 'Staff Gudang', 'username' => 'staff-gudang', 'email' => 'staff-gudang@gudangtoko.test', 'roles' => ['staff_gudang']],
        'toko_internal' => ['name' => 'Toko Internal', 'username' => 'toko-internal', 'email' => 'toko@gudangtoko.test', 'roles' => ['kepala_toko']],
        'staf_toko' => ['name' => 'Staf Toko', 'username' => 'staf-toko', 'email' => 'staf-toko@gudangtoko.test', 'roles' => ['staf_toko']],
        'kasir_kepala_toko' => ['name' => 'Kasir / Kepala Toko', 'username' => 'kasir', 'email' => 'kasir@gudangtoko.test', 'roles' => ['kasir', 'kepala_toko']],
        'langganan_b2b' => ['name' => 'Langganan / B2B', 'username' => 'langganan-b2b', 'email' => 'langganan-b2b@gudangtoko.test', 'roles' => ['langganan_owner']],
        'akun_pelanggan' => ['name' => 'Akun Pelanggan', 'username' => 'pelanggan', 'email' => 'pelanggan@gudangtoko.test', 'roles' => ['langganan_staff']],
    ];

    public function run(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 403, 'Seeder akun demo hanya boleh dijalankan pada environment local/testing. Untuk production gunakan InitialAdministrationSeeder.');

        $this->call(RolePermissionSeeder::class);

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
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
