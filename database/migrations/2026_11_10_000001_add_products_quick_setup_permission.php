<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * @var array<string, array{label: string, module: string, action: string, description: string}>
     */
    private array $definitions = [
        'products.quick_setup' => [
            'label' => 'Setup Cepat Produk',
            'module' => 'products',
            'action' => 'update',
            'description' => 'Melakukan setup cepat operasional produk (HPP, Stok Awal, dan Harga Jual Minimum).',
        ],
    ];

    public function up(): void
    {
        foreach ($this->definitions as $name => $metadata) {
            Permission::findOrCreate($name)->forceFill([...$metadata, 'is_system' => true])->save();
        }

        foreach (['super_admin', 'admin_config', 'kepala_gudang', 'kepala_toko'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo(array_keys($this->definitions));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (['super_admin', 'admin_config', 'kepala_gudang', 'kepala_toko'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->first();
            if ($role) {
                $role->revokePermissionTo(array_keys($this->definitions));
            }
        }

        Permission::query()->whereIn('name', array_keys($this->definitions))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
