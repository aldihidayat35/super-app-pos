<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder untuk inisialisasi awal production (hosting).
 * Hanya memuat data primer yang dibutuhkan: Role, Permission, Akun Super Admin, dan Satuan Dasar.
 */
class InitialAdministrationSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Install Roles & Permissions
        $this->call(RolePermissionSeeder::class);

        // 2. Buat Akun Super Admin
        $superAdmin = User::query()->updateOrCreate(
            ['email' => 'superadmin@gudangtoko.com'], // Sesuaikan email jika perlu
            [
                'name' => 'Super Administrator',
                'username' => 'superadmin',
                'phone_number' => '08000000000',
                'password' => 'password', // Ganti setelah login di production!
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
        $superAdmin->syncRoles([Role::findOrCreate('super_admin')]);

        // 3. Buat satuan standar. Faktor konversi tetap ditentukan per produk.
        $this->call(UnitSeeder::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
