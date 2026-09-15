<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $definitions = [
        'product_requests.view' => ['label' => 'Lihat Pengajuan Produk Toko', 'module' => 'product_requests', 'action' => 'view', 'description' => 'Melihat usulan produk baru dari toko.'],
        'product_requests.create' => ['label' => 'Ajukan Produk Toko', 'module' => 'product_requests', 'action' => 'create', 'description' => 'Mengusulkan produk baru dari toko untuk diperiksa master data.'],
        'product_requests.approve' => ['label' => 'Periksa Pengajuan Produk Toko', 'module' => 'product_requests', 'action' => 'approve', 'description' => 'Menyetujui atau menolak usulan produk dan membuat master produk.'],
    ];

    private array $grants = [
        'super_admin' => ['product_requests.view', 'product_requests.create', 'product_requests.approve'],
        'admin_config' => ['product_requests.view', 'product_requests.approve'],
        'kepala_gudang' => ['product_requests.view', 'product_requests.approve'],
        'kepala_toko' => ['product_requests.view', 'product_requests.create', 'purchase_orders.view', 'purchase_orders.create', 'goods_receipts.view', 'goods_receipts.create'],
        'staf_toko' => ['product_requests.view', 'product_requests.create'],
    ];

    public function up(): void
    {
        $permissionNames = array_unique(array_merge(...array_values($this->grants)));
        foreach ($permissionNames as $name) {
            $metadata = $this->definitions[$name] ?? config("rbac.permissions.{$name}", [
                'label' => $name, 'module' => 'purchasing', 'action' => 'view', 'description' => $name,
            ]);
            Permission::findOrCreate($name)->forceFill([...$metadata, 'is_system' => true])->save();
        }
        foreach ($this->grants as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName);
            foreach ($permissions as $permission) {
                $role->givePermissionTo($permission);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach ($this->grants as $roleName => $permissions) {
            $role = Role::query()->where('name', $roleName)->first();
            foreach ($permissions as $permission) {
                $role?->revokePermissionTo($permission);
            }
        }
        Permission::query()->whereIn('name', array_keys($this->definitions))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
