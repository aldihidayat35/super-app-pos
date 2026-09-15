<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_notification_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 100)->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->json('recipient_roles');
            $table->boolean('location_scoped')->default(false);
            $table->unsignedInteger('cooldown_minutes')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        DB::table('business_notification_settings')->insert(collect([
            ['critical_stock', 'Stok Kritis atau Habis', 'Produk mencapai batas minimum atau habis.', ['kepala_toko', 'kepala_gudang', 'purchasing'], true, 360],
            ['restock_submitted', 'Permintaan Restok Diajukan', 'Permintaan restok baru perlu ditinjau.', ['kepala_gudang'], true, 0],
            ['restock_decided', 'Keputusan Permintaan Restok', 'Restok disetujui atau ditolak.', ['kepala_toko', 'staf_toko'], true, 0],
            ['emergency_purchase', 'Pembelian Darurat', 'Perubahan penting pembelian darurat toko.', ['kepala_toko', 'owner_approver'], true, 0],
            ['approval_pending', 'Approval Menunggu', 'Transaksi baru menunggu keputusan approver.', ['owner_approver'], false, 0],
            ['approval_decided', 'Hasil Approval', 'Approval disetujui atau ditolak.', [], false, 0],
            ['cash_closing_difference', 'Selisih Penutupan Kas', 'Selisih kas shift memerlukan pemeriksaan.', ['kepala_toko', 'owner_approver'], true, 0],
            ['receivable_due', 'Piutang Jatuh Tempo', 'Piutang telah atau segera jatuh tempo.', ['owner_approver', 'sales'], false, 1440],
            ['b2b_pending_order', 'Order B2B Tertunda', 'Order B2B belum diproses lebih dari 24 jam.', ['kepala_gudang', 'sales'], true, 720],
            ['b2b_status', 'Perubahan Status B2B', 'Order B2B dikirim atau diselesaikan.', ['sales'], false, 0],
            ['owner_report_nightly', 'Laporan Owner Pukul 21.00', 'Ringkasan operasional dan tautan dashboard dikirim setiap malam.', ['owner_approver', 'owner_viewer'], false, 0],
            ['owner_report_head_checklist', 'Laporan Owner Setelah Checklist Kepala Lokasi', 'Tautan dashboard lokasi dikirim setelah kepala toko atau gudang menutup checklist harian.', ['owner_approver', 'owner_viewer'], false, 0],
        ])->map(fn (array $row): array => [
            'event_key' => $row[0], 'name' => $row[1], 'description' => $row[2],
            'recipient_roles' => json_encode($row[3]), 'location_scoped' => $row[4],
            'cooldown_minutes' => $row[5], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ])->all());

        $permission = Permission::query()->where('name', 'notifications.update')->first();
        $role = Role::query()->where('name', 'owner_approver')->first();
        if ($permission !== null && $role !== null) {
            $role->givePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permission = Permission::query()->where('name', 'notifications.update')->first();
        $role = Role::query()->where('name', 'owner_approver')->first();
        if ($permission !== null && $role !== null) {
            $role->revokePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Schema::dropIfExists('business_notification_settings');
    }
};
