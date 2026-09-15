<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $definitions = [
        'whatsapp_connection.view' => ['label' => 'Lihat Koneksi WhatsApp', 'module' => 'whatsapp_connection', 'action' => 'view', 'description' => 'Melihat status koneksi WhatsApp perusahaan.'],
        'whatsapp_connection.manage' => ['label' => 'Kelola Koneksi WhatsApp', 'module' => 'whatsapp_connection', 'action' => 'manage', 'description' => 'Menghubungkan, menyambungkan ulang, dan memutus WhatsApp perusahaan.'],
    ];

    public function up(): void
    {
        foreach ($this->definitions as $name => $metadata) {
            Permission::findOrCreate($name)->forceFill([...$metadata, 'is_system' => true])->save();
        }
        foreach (['super_admin', 'admin_config'] as $role) {
            Role::findOrCreate($role)->givePermissionTo(array_keys($this->definitions));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (['super_admin', 'admin_config'] as $role) {
            Role::query()->where('name', $role)->first()?->revokePermissionTo(array_keys($this->definitions));
        }
        Permission::query()->whereIn('name', array_keys($this->definitions))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
