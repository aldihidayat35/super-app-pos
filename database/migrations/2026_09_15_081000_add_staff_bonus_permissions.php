<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $definitions = [
        'staff_bonuses.view_own' => ['label' => 'Lihat Target dan Bonus Sendiri', 'module' => 'staff_bonuses', 'action' => 'view_own', 'description' => 'Melihat target dan bonus milik akun sendiri.'],
        'staff_bonuses.view_team' => ['label' => 'Lihat Bonus Tim', 'module' => 'staff_bonuses', 'action' => 'view_team', 'description' => 'Melihat bonus tim sesuai lokasi penugasan.'],
        'staff_bonuses.view_all' => ['label' => 'Lihat Seluruh Bonus', 'module' => 'staff_bonuses', 'action' => 'view_all', 'description' => 'Melihat bonus seluruh organisasi.'],
        'staff_bonuses.manage' => ['label' => 'Kelola Program Bonus', 'module' => 'staff_bonuses', 'action' => 'manage', 'description' => 'Membuat dan mengaktifkan program bonus.'],
        'staff_bonuses.approve' => ['label' => 'Setujui Bonus', 'module' => 'staff_bonuses', 'action' => 'approve', 'description' => 'Menyetujui atau menolak hasil bonus.'],
        'staff_bonuses.pay' => ['label' => 'Catat Pembayaran Bonus', 'module' => 'staff_bonuses', 'action' => 'pay', 'description' => 'Mencatat pembayaran bonus.'],
        'staff_bonuses.export' => ['label' => 'Ekspor Rekap Bonus', 'module' => 'staff_bonuses', 'action' => 'export', 'description' => 'Mengunduh rekap bonus.'],
    ];

    private array $grants = [
        'owner_viewer' => ['staff_bonuses.view_all', 'staff_bonuses.export'],
        'owner_approver' => ['staff_bonuses.view_all', 'staff_bonuses.manage', 'staff_bonuses.approve', 'staff_bonuses.pay', 'staff_bonuses.export'],
        'super_admin' => ['staff_bonuses.view_own', 'staff_bonuses.view_team', 'staff_bonuses.view_all', 'staff_bonuses.manage', 'staff_bonuses.approve', 'staff_bonuses.pay', 'staff_bonuses.export'],
        'kepala_gudang' => ['staff_bonuses.view_own', 'staff_bonuses.view_team'],
        'kepala_toko' => ['staff_bonuses.view_own', 'staff_bonuses.view_team'],
        'supervisor_shift' => ['staff_bonuses.view_own', 'staff_bonuses.view_team'],
        'staff_gudang' => ['staff_bonuses.view_own'], 'picker_packer' => ['staff_bonuses.view_own'],
        'staf_toko' => ['staff_bonuses.view_own'], 'kasir' => ['staff_bonuses.view_own'], 'sales' => ['staff_bonuses.view_own'],
    ];

    public function up(): void
    {
        foreach ($this->definitions as $name => $metadata) {
            Permission::findOrCreate($name)->forceFill([...$metadata, 'is_system' => true])->save();
        }
        foreach ($this->grants as $roleName => $permissions) {
            Role::findOrCreate($roleName)->givePermissionTo($permissions);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach ($this->grants as $roleName => $permissions) {
            Role::query()->where('name', $roleName)->first()?->revokePermissionTo($permissions);
        }
        Permission::query()->whereIn('name', array_keys($this->definitions))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
