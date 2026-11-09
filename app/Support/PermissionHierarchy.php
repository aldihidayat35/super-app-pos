<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

class PermissionHierarchy
{
    /**
     * Definisi 12 Grand Modules utama dalam sistem GudangToko.
     *
     * @return array<string, array{title: string, icon: string, description: string, sub_modules: list<string>}>
     */
    public static function grandModules(): array
    {
        return [
            'retail' => [
                'title' => 'Toko Internal & Kasir',
                'icon' => 'ki-outline ki-shop',
                'description' => 'Akses transaksi kasir POS, shift kasir, etalase produk toko, pembelian darurat, dan retur retail.',
                'sub_modules' => [
                    'pos',
                    'cash_shifts',
                    'retail.catalog',
                    'product_requests',
                    'emergency_purchases',
                    'emergency_reports',
                    'returns',
                ],
            ],
            'warehouse' => [
                'title' => 'Gudang & Persediaan Stok',
                'icon' => 'ki-outline ki-delivery-3',
                'description' => 'Manajemen fisik persediaan, kartu stok, mutasi, transfer antar cabang, opname, dan pengiriman.',
                'sub_modules' => [
                    'stock',
                    'opening_stocks',
                    'stock_adjustments',
                    'stock_direct_adjustments',
                    'goods_receipts',
                    'stock_transfers',
                    'b2b_orders',
                    'shipments',
                    'losses',
                ],
            ],
            'master_data' => [
                'title' => 'Master Produk & Relasi Bisnis',
                'icon' => 'ki-outline ki-parcel',
                'description' => 'Katalog master barang, barcode, kategori, satuan, serta master supplier dan pelanggan.',
                'sub_modules' => [
                    'products',
                    'suppliers',
                    'customers',
                ],
            ],
            'purchasing' => [
                'title' => 'Pembelian & Pengadaan (PO)',
                'icon' => 'ki-outline ki-purchase',
                'description' => 'Pengajuan belanja internal dan penerbitan pesanan pembelian ke pemasok (Purchase Order).',
                'sub_modules' => [
                    'purchase_orders',
                ],
            ],
            'pricing' => [
                'title' => 'Penetapan Harga & Margin',
                'icon' => 'ki-outline ki-price-tag',
                'description' => 'Aturan penetapan harga jual minimum anti-rugi, ring harga, dan akses informasi margin/HPP.',
                'sub_modules' => [
                    'prices',
                    'margins',
                ],
            ],
            'sales' => [
                'title' => 'Penjualan & Tim Sales',
                'icon' => 'ki-outline ki-chart-line-up-2',
                'description' => 'Aktivitas penugasan pelanggan, order sales lapangan, target, komisi, dan evaluasi kinerja sales.',
                'sub_modules' => [
                    'sales.dashboard',
                    'sales.customers',
                    'sales.orders',
                    'sales.stock',
                    'sales.targets',
                    'sales.performance',
                ],
            ],
            'finance' => [
                'title' => 'Keuangan, Piutang & Pajak',
                'icon' => 'ki-outline ki-bill',
                'description' => 'Pengelolaan piutang usaha, limit kredit, reminder penagihan, invoice faktur, dan pajak tahunan.',
                'sub_modules' => [
                    'receivables',
                    'invoices',
                    'payments',
                    'tax',
                    'finance_annual',
                ],
            ],
            'hr_attendance' => [
                'title' => 'SDM, Presensi & Bonus Staf',
                'icon' => 'ki-outline ki-calendar-tick',
                'description' => 'Presensi harian karyawan, pengaturan jadwal shift kerja, checklist SOP, dan bonus produktivitas staf.',
                'sub_modules' => [
                    'attendance',
                    'work_checklists',
                    'staff_bonuses',
                ],
            ],
            'audit_governance' => [
                'title' => 'Approval, Audit & Notifikasi',
                'icon' => 'ki-outline ki-shield-tick',
                'description' => 'Pusat persetujuan dokumen sensitif, rekam jejak audit log, mitigasi anomali, dan gateway WhatsApp.',
                'sub_modules' => [
                    'approvals',
                    'audit',
                    'notifications',
                    'whatsapp_connection',
                ],
            ],
            'b2b_portal' => [
                'title' => 'Portal Pelanggan Langganan (B2B)',
                'icon' => 'ki-outline ki-basket',
                'description' => 'Hak akses mandiri akun pelanggan langganan B2B untuk pesanan mandiri dan komplain layanan.',
                'sub_modules' => [
                    'complaints',
                ],
            ],
            'reports' => [
                'title' => 'Pusat Laporan & Analitik',
                'icon' => 'ki-outline ki-chart-pie',
                'description' => 'Akses laporan terpadu untuk Kepala Gudang, Kepala Toko, Owner eksekutif, dan Super Admin.',
                'sub_modules' => [
                    'reports',
                    'reports.warehouse',
                    'reports.retail',
                    'reports.owner',
                    'reports.system',
                    'reports.users',
                ],
            ],
            'system_admin' => [
                'title' => 'Administrasi Sistem & Konfigurasi',
                'icon' => 'ki-outline ki-setting-2',
                'description' => 'Pengaturan master organisasi, manajemen pengguna & role, kesehatan server, dan maintenance.',
                'sub_modules' => [
                    'dashboard',
                    'admin.users',
                    'admin.roles',
                    'admin.permissions',
                    'admin.warehouses',
                    'admin.branches',
                    'admin.settings',
                    'system.health',
                    'system.operations',
                ],
            ],
        ];
    }

    /**
     * Label bahasa Indonesia yang ramah pengguna untuk setiap sub-modul.
     *
     * @return array<string, string>
     */
    public static function subModuleLabels(): array
    {
        return [
            'pos' => 'Kasir & Transaksi POS',
            'cash_shifts' => 'Shift & Saldo Kasir',
            'retail.catalog' => 'Etalase & Display Produk Toko',
            'product_requests' => 'Pengajuan Produk Baru Toko',
            'emergency_purchases' => 'Pembelian Darurat Toko',
            'emergency_reports' => 'Laporan Darurat Toko',
            'returns' => 'Retur Penjualan & Barang',
            'stock' => 'Saldo & Kartu Stok',
            'opening_stocks' => 'Stok Awal & Import Stok',
            'stock_adjustments' => 'Koreksi Stok & Stok Opname',
            'stock_direct_adjustments' => 'Koreksi Stok Langsung',
            'goods_receipts' => 'Penerimaan Barang (GR)',
            'stock_transfers' => 'Transfer Stok Antar Cabang',
            'b2b_orders' => 'Order B2B Pelanggan',
            'shipments' => 'Pengiriman & Ekspedisi B2B',
            'losses' => 'Barang Rusak & Kerugian (Loss)',
            'products' => 'Katalog Produk & Barcode',
            'suppliers' => 'Supplier / Pemasok',
            'customers' => 'Pelanggan & Mitra B2B',
            'purchase_orders' => 'Pesanan Pembelian (PO)',
            'prices' => 'Daftar & Aturan Harga',
            'margins' => 'Informasi Margin & HPP Sensitif',
            'sales.dashboard' => 'Dashboard Sales',
            'sales.customers' => 'Customer & Penugasan Sales',
            'sales.orders' => 'Order Sales Lapangan',
            'sales.stock' => 'Cek Saldo Stok Sales',
            'sales.targets' => 'Target & Komisi Sales',
            'sales.performance' => 'Evaluasi Kinerja Sales',
            'receivables' => 'Piutang & Limit Kredit',
            'invoices' => 'Faktur & Tagihan (Invoice)',
            'payments' => 'Pembayaran & Verifikasi Kas',
            'tax' => 'Perpajakan Usaha & Coretax',
            'finance_annual' => 'Laporan Keuangan Tahunan',
            'attendance' => 'Presensi, Shift & Cuti Karyawan',
            'work_checklists' => 'Checklist Harian Kerja (SOP)',
            'staff_bonuses' => 'Target & Bonus Staf',
            'approvals' => 'Pusat Approval Dokumen',
            'audit' => 'Audit Log & Anomali Sistem',
            'notifications' => 'Pengaturan & Log Notifikasi',
            'whatsapp_connection' => 'Koneksi Gateway WhatsApp',
            'complaints' => 'Komplain Pelanggan B2B',
            'reports' => 'Laporan Operasional Umum',
            'reports.warehouse' => 'Laporan Kepala Gudang',
            'reports.retail' => 'Laporan Kepala Toko',
            'reports.owner' => 'Laporan Eksekutif Owner',
            'reports.system' => 'Laporan Sistem & Audit',
            'reports.users' => 'Laporan Akun & Pengguna',
            'dashboard' => 'Akses Dashboard Utama',
            'admin.users' => 'Manajemen Pengguna',
            'admin.roles' => 'Manajemen Peran (Role)',
            'admin.permissions' => 'Daftar Hak Akses (Permissions)',
            'admin.warehouses' => 'Master Gudang Fisik',
            'admin.branches' => 'Master Cabang / Toko',
            'admin.settings' => 'Pengaturan Sistem & Penomoran',
            'system.health' => 'Kesehatan Server & Layanan',
            'system.operations' => 'Operasional, Backup & Log',
        ];
    }

    /**
     * Mengelompokkan koleksi permission ke dalam hierarki 2 tingkat:
     * GrandModule -> SubModule -> List of Permissions.
     *
     * @param  Collection<int, Permission>  $permissions
     * @return array<string, array{
     *     key: string,
     *     title: string,
     *     icon: string,
     *     description: string,
     *     total_permissions: int,
     *     sub_modules: array<string, array{
     *         key: string,
     *         label: string,
     *         permissions: Collection<int, Permission>
     *     }>
     * }>
     */
    public static function group(Collection $permissions): array
    {
        $grandDefinitions = self::grandModules();
        $subLabels = self::subModuleLabels();

        // Index permissions berdasarkan modul
        /** @var Collection<string, Collection<int, Permission>> $byModule */
        $byModule = $permissions->groupBy(function (Permission $permission): string {
            return (string) ($permission->getAttribute('module') ?: explode('.', $permission->name)[0]);
        });

        $processedModules = [];
        $result = [];

        foreach ($grandDefinitions as $grandKey => $grandInfo) {
            $subModules = [];
            $totalCount = 0;

            foreach ($grandInfo['sub_modules'] as $subKey) {
                if ($byModule->has($subKey)) {
                    /** @var Collection<int, Permission> $modulePermissions */
                    $modulePermissions = $byModule->get($subKey);
                    $subModules[$subKey] = [
                        'key' => $subKey,
                        'label' => $subLabels[$subKey] ?? ucwords(str_replace(['.', '_', '-'], ' ', $subKey)),
                        'permissions' => $modulePermissions,
                    ];
                    $totalCount += $modulePermissions->count();
                    $processedModules[] = $subKey;
                }
            }

            if ($totalCount > 0) {
                $result[$grandKey] = [
                    'key' => $grandKey,
                    'title' => $grandInfo['title'],
                    'icon' => $grandInfo['icon'],
                    'description' => $grandInfo['description'],
                    'total_permissions' => $totalCount,
                    'sub_modules' => $subModules,
                ];
            }
        }

        // Tangani permission yang belum terpetakan (fallback)
        $unprocessedSubModules = [];
        $unprocessedTotal = 0;

        foreach ($byModule as $modKey => $perms) {
            if (! in_array($modKey, $processedModules, true)) {
                $unprocessedSubModules[$modKey] = [
                    'key' => $modKey,
                    'label' => $subLabels[$modKey] ?? ucwords(str_replace(['.', '_', '-'], ' ', (string) $modKey)),
                    'permissions' => $perms,
                ];
                $unprocessedTotal += $perms->count();
            }
        }

        if ($unprocessedTotal > 0) {
            $result['other'] = [
                'key' => 'other',
                'title' => 'Modul Lainnya',
                'icon' => 'ki-outline ki-more-2',
                'description' => 'Hak akses modul tambahan atau modul khusus lainnya.',
                'total_permissions' => $unprocessedTotal,
                'sub_modules' => $unprocessedSubModules,
            ];
        }

        return $result;
    }
}
