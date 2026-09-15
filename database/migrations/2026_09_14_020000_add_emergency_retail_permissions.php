<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** @var array<string, array{label: string, module: string, action: string, description: string}> */
    private array $definitions = [
        'emergency_purchases.view' => ['label' => 'Lihat Pembelian Darurat', 'module' => 'emergency_purchases', 'action' => 'view', 'description' => 'Melihat pembelian darurat toko penugasan.'],
        'emergency_purchases.create' => ['label' => 'Ajukan Pembelian Darurat', 'module' => 'emergency_purchases', 'action' => 'create', 'description' => 'Mengonfirmasi stockout dan mengajukan pembelian darurat.'],
        'emergency_purchases.purchase' => ['label' => 'Catat Pembelian Darurat', 'module' => 'emergency_purchases', 'action' => 'update', 'description' => 'Mencatat barang, biaya, sumber dana, dan nota.'],
        'emergency_purchases.approve' => ['label' => 'Setujui Pembelian Darurat', 'module' => 'emergency_purchases', 'action' => 'approve', 'description' => 'Memutuskan permintaan pembelian darurat.'],
        'emergency_purchases.manage' => ['label' => 'Kelola Pembelian Darurat', 'module' => 'emergency_purchases', 'action' => 'update', 'description' => 'Mengelola pembatalan, retur pemasok, reimbursement, dan aturan.'],
        'emergency_reports.view' => ['label' => 'Lihat Laporan Darurat Toko', 'module' => 'emergency_reports', 'action' => 'view', 'description' => 'Melihat biaya, lost margin, dan stockout toko.'],
    ];

    /** @var array<string, list<string>> */
    private array $grants = [
        'super_admin' => ['emergency_purchases.view', 'emergency_purchases.create', 'emergency_purchases.purchase', 'emergency_purchases.approve', 'emergency_purchases.manage', 'emergency_reports.view'],
        'owner_viewer' => ['emergency_purchases.view', 'emergency_reports.view'],
        'owner_approver' => ['emergency_purchases.view', 'emergency_purchases.approve', 'emergency_reports.view'],
        'kepala_toko' => ['emergency_purchases.view', 'emergency_purchases.create', 'emergency_purchases.purchase', 'emergency_purchases.approve', 'emergency_purchases.manage', 'emergency_reports.view'],
        'staf_toko' => ['emergency_purchases.view', 'emergency_purchases.create'],
        'kasir' => ['emergency_purchases.view', 'emergency_purchases.create', 'emergency_purchases.purchase'],
    ];

    public function up(): void
    {
        foreach (array_unique(array_merge(...array_values($this->grants))) as $name) {
            $metadata = $this->definitions[$name];
            Permission::findOrCreate($name)->forceFill([
                'label' => $metadata['label'],
                'module' => $metadata['module'],
                'action' => $metadata['action'],
                'description' => $metadata['description'],
                'is_system' => true,
            ])->save();
        }

        foreach ($this->grants as $name => $permissions) {
            $role = Role::findOrCreate($name);
            foreach ($permissions as $permission) {
                $role->givePermissionTo($permission);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissions = array_unique(array_merge(...array_values($this->grants)));
        foreach ($this->grants as $name => $grants) {
            $role = Role::query()->where('name', $name)->first();
            if ($role) {
                foreach ($grants as $permission) {
                    $role->revokePermissionTo($permission);
                }
            }
        }
        Permission::query()->whereIn('name', $permissions)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
