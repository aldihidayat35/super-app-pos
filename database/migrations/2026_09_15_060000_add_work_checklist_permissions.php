<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** @var array<string, array{label: string, module: string, action: string, description: string}> */
    private array $definitions = [
        'work_checklists.view_own' => ['label' => 'Lihat Checklist Sendiri', 'module' => 'work_checklists', 'action' => 'view_own', 'description' => 'Melihat checklist kerja milik akun sendiri.'],
        'work_checklists.update_own' => ['label' => 'Isi Checklist Sendiri', 'module' => 'work_checklists', 'action' => 'update_own', 'description' => 'Mengisi, menyelesaikan, dan mengoreksi checklist kerja milik akun sendiri.'],
        'work_checklists.view_team' => ['label' => 'Lihat Rekap Tim', 'module' => 'work_checklists', 'action' => 'view_team', 'description' => 'Melihat rekap checklist bawahan pada lokasi penugasan.'],
        'work_checklists.view_all' => ['label' => 'Lihat Seluruh Rekap Checklist', 'module' => 'work_checklists', 'action' => 'view_all', 'description' => 'Melihat rekap checklist seluruh akun dan lokasi.'],
        'work_checklists.manage_templates' => ['label' => 'Kelola Template Checklist', 'module' => 'work_checklists', 'action' => 'manage', 'description' => 'Membuat versi dan menonaktifkan template checklist.'],
        'work_checklists.export' => ['label' => 'Export Rekap Checklist', 'module' => 'work_checklists', 'action' => 'export', 'description' => 'Mengunduh rekap checklist sesuai lingkup akses.'],
    ];

    /** @var array<string, list<string>> */
    private array $grants = [
        'owner_viewer' => ['work_checklists.view_own', 'work_checklists.update_own', 'work_checklists.view_all', 'work_checklists.export'],
        'owner_approver' => ['work_checklists.view_own', 'work_checklists.update_own', 'work_checklists.view_all', 'work_checklists.export'],
        'super_admin' => ['work_checklists.view_own', 'work_checklists.update_own', 'work_checklists.view_team', 'work_checklists.view_all', 'work_checklists.manage_templates', 'work_checklists.export'],
        'admin_user' => ['work_checklists.view_own', 'work_checklists.update_own'],
        'admin_config' => ['work_checklists.view_own', 'work_checklists.update_own', 'work_checklists.manage_templates'],
        'kepala_gudang' => ['work_checklists.view_own', 'work_checklists.update_own', 'work_checklists.view_team', 'work_checklists.export'],
        'staff_gudang' => ['work_checklists.view_own', 'work_checklists.update_own'],
        'picker_packer' => ['work_checklists.view_own', 'work_checklists.update_own'],
        'purchasing' => ['work_checklists.view_own', 'work_checklists.update_own'],
        'kepala_toko' => ['work_checklists.view_own', 'work_checklists.update_own', 'work_checklists.view_team', 'work_checklists.export'],
        'staf_toko' => ['work_checklists.view_own', 'work_checklists.update_own'],
        'kasir' => ['work_checklists.view_own', 'work_checklists.update_own'],
        'supervisor_shift' => ['work_checklists.view_own', 'work_checklists.update_own', 'work_checklists.view_team', 'work_checklists.export'],
        'sales' => ['work_checklists.view_own', 'work_checklists.update_own'],
    ];

    public function up(): void
    {
        foreach ($this->definitions as $name => $metadata) {
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
