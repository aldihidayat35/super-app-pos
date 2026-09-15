<?php

$item = static fn (string $key, string $label, ?string $guidance = null): array => compact('key', 'label', 'guidance');

return [
    'timezone' => 'Asia/Jakarta',
    'internal_roles' => [
        'owner_viewer', 'owner_approver', 'super_admin', 'admin_user', 'admin_config',
        'kepala_gudang', 'staff_gudang', 'picker_packer', 'purchasing',
        'kepala_toko', 'staf_toko', 'kasir', 'supervisor_shift', 'sales',
    ],
    'team_roles' => [
        'kepala_gudang' => ['staff_gudang', 'picker_packer'],
        'kepala_toko' => ['staf_toko', 'kasir'],
        'supervisor_shift' => ['staf_toko', 'kasir'],
    ],
    'common_items' => [
        $item('common.transaction_status', 'Pastikan transaksi penting sudah berstatus benar.'),
        $item('common.outstanding_drafts', 'Pastikan tidak ada draft yang seharusnya disubmit.'),
        $item('common.failed_notifications', 'Periksa notifikasi gagal yang berkaitan dengan pekerjaan Anda.'),
        $item('common.shared_device_logout', 'Logout dari perangkat bersama setelah pekerjaan berakhir.'),
    ],
    'templates' => [
        [
            'key' => 'owner_daily', 'name' => 'Checklist Harian Owner', 'frequency' => 'daily', 'scope' => 'global', 'location_type' => null,
            'roles' => ['owner_viewer', 'owner_approver'],
            'items' => [
                $item('owner.dashboard', 'Dashboard owner sudah diperiksa.'),
                $item('owner.approvals', 'Approval pending diputuskan atau didelegasikan.'),
                $item('owner.critical_stock', 'Stok kritis atau kosong ditindaklanjuti.'),
                $item('owner.margin', 'Margin rendah dan harga tidak wajar ditinjau.'),
                $item('owner.overdue_receivables', 'Piutang jatuh tempo dipantau.'),
                $item('owner.high_anomaly', 'Anomali prioritas tinggi ditinjau.'),
                $item('owner.exports', 'Laporan penting diunduh bila diperlukan.'),
            ],
        ],
        [
            'key' => 'owner_weekly', 'name' => 'Checklist Mingguan Owner', 'frequency' => 'weekly', 'scope' => 'global', 'location_type' => null,
            'roles' => ['owner_viewer', 'owner_approver'],
            'items' => [
                $item('owner_weekly.suppliers', 'Evaluasi performa supplier.'),
                $item('owner_weekly.branches', 'Evaluasi performa cabang.'),
                $item('owner_weekly.product_movement', 'Evaluasi produk lambat dan cepat bergerak.'),
                $item('owner_weekly.loss_returns', 'Evaluasi loss, retur, void, dan koreksi stok.'),
                $item('owner_weekly.credit_limits', 'Evaluasi limit kredit pelanggan B2B.'),
                $item('owner_weekly.system_health', 'Review backup dan kesehatan sistem bersama Super Admin.'),
            ],
        ],
        [
            'key' => 'super_admin_daily', 'name' => 'Checklist Harian Super Admin', 'frequency' => 'daily', 'scope' => 'global', 'location_type' => null,
            'roles' => ['super_admin'],
            'items' => [
                $item('sys.health', 'Periksa kesehatan aplikasi dan koneksi layanan utama.'),
                $item('sys.scheduler', 'Periksa heartbeat scheduler dan pekerjaan terjadwal.'),
                $item('sys.queue', 'Periksa antrean dan pekerjaan yang gagal.'),
                $item('sys.backup', 'Pastikan backup terakhir berhasil.'),
                $item('sys.security', 'Tinjau error dan kejadian keamanan prioritas tinggi.'),
            ],
        ],
        [
            'key' => 'super_admin_weekly', 'name' => 'Checklist Mingguan Super Admin', 'frequency' => 'weekly', 'scope' => 'global', 'location_type' => null,
            'roles' => ['super_admin'],
            'items' => [
                $item('sys_weekly.restore', 'Periksa hasil backup dan jadwal uji pemulihan.'),
                $item('sys_weekly.access', 'Audit akun, role, permission, dan penugasan lokasi.'),
                $item('sys_weekly.anomalies', 'Review anomali keamanan serta tindak lanjutnya.'),
                $item('sys_weekly.capacity', 'Review kapasitas penyimpanan, log, dan antrean.'),
            ],
        ],
        [
            'key' => 'admin_user_daily', 'name' => 'Checklist Harian Admin User', 'frequency' => 'daily', 'scope' => 'global', 'location_type' => null,
            'roles' => ['admin_user'],
            'items' => [
                $item('admin_user.requests', 'Periksa permintaan akun atau perubahan akses.'),
                $item('admin_user.inactive', 'Tindak lanjuti akun yang perlu dinonaktifkan.'),
                $item('admin_user.assignment', 'Pastikan role dan lokasi akun baru sudah tepat.'),
            ],
        ],
        [
            'key' => 'admin_user_weekly', 'name' => 'Checklist Mingguan Admin User', 'frequency' => 'weekly', 'scope' => 'global', 'location_type' => null,
            'roles' => ['admin_user'],
            'items' => [
                $item('admin_user_weekly.accounts', 'Review akun tidak aktif dan akun tanpa penugasan.'),
                $item('admin_user_weekly.roles', 'Review ketepatan role dan lokasi kerja pengguna.'),
            ],
        ],
        [
            'key' => 'admin_config_daily', 'name' => 'Checklist Harian Admin Config', 'frequency' => 'daily', 'scope' => 'global', 'location_type' => null,
            'roles' => ['admin_config'],
            'items' => [
                $item('admin_config.master_requests', 'Periksa perubahan master produk, supplier, pelanggan, dan lokasi.'),
                $item('admin_config.product_requests', 'Periksa pengajuan produk baru dari toko.'),
                $item('admin_config.settings', 'Tindak lanjuti konfigurasi yang menghambat operasional.'),
            ],
        ],
        [
            'key' => 'admin_config_weekly', 'name' => 'Checklist Mingguan Admin Config', 'frequency' => 'weekly', 'scope' => 'global', 'location_type' => null,
            'roles' => ['admin_config'],
            'items' => [
                $item('admin_config_weekly.master', 'Review kelengkapan dan duplikasi master data.'),
                $item('admin_config_weekly.numbers', 'Review nomor dokumen dan pengaturan umum.'),
                $item('admin_config_weekly.prices', 'Review perubahan harga dan konfigurasi sensitif.'),
            ],
        ],
        [
            'key' => 'warehouse_daily', 'name' => 'Checklist Harian Gudang', 'frequency' => 'daily', 'scope' => 'location', 'location_type' => 'warehouse',
            'roles' => ['kepala_gudang', 'staff_gudang'],
            'items' => [
                $item('warehouse.dashboard', 'Dashboard gudang diperiksa.'),
                $item('warehouse.receipts', 'Penerimaan fisik yang masih draft diposting.'),
                $item('warehouse.transfers', 'Transfer pending diproses.'),
                $item('warehouse.b2b_orders', 'Order B2B pending direview.'),
                $item('warehouse.reservations', 'Reserved stock yang tidak wajar dicek.'),
                $item('warehouse.critical_stock', 'Stok kritis dilaporkan ke Purchasing.'),
                $item('warehouse.loss', 'Barang loss atau rusak dicatat.'),
                $item('warehouse.mutations', 'Mutasi besar direview.'),
            ],
        ],
        [
            'key' => 'warehouse_manager_weekly', 'name' => 'Checklist Mingguan Kepala Gudang', 'frequency' => 'weekly', 'scope' => 'location', 'location_type' => 'warehouse',
            'roles' => ['kepala_gudang'],
            'items' => [
                $item('warehouse_weekly.stock_accuracy', 'Review akurasi stok, selisih, dan hasil opname.'),
                $item('warehouse_weekly.throughput', 'Review penerimaan, picking, packing, dan transfer tertunda.'),
                $item('warehouse_weekly.loss', 'Review loss, barang rusak, dan tindak lanjut koreksi.'),
                $item('warehouse_weekly.team', 'Review kendala kerja tim gudang.'),
            ],
        ],
        [
            'key' => 'picker_daily', 'name' => 'Checklist Harian Picker Packer', 'frequency' => 'daily', 'scope' => 'location', 'location_type' => 'warehouse',
            'roles' => ['picker_packer'],
            'items' => [
                $item('picker.queue', 'Periksa antrean picking dan packing.'),
                $item('picker.quantity', 'Pastikan produk dan kuantitas sesuai dokumen.'),
                $item('picker.condition', 'Catat kekurangan atau kerusakan yang ditemukan.'),
                $item('picker.handover', 'Pastikan barang dan dokumen sudah diserahterimakan.'),
            ],
        ],
        [
            'key' => 'purchasing_daily', 'name' => 'Checklist Harian Purchasing', 'frequency' => 'daily', 'scope' => 'global', 'location_type' => null,
            'roles' => ['purchasing'],
            'items' => [
                $item('purchasing.critical_stock', 'Periksa stok kritis dan permintaan baru.'),
                $item('purchasing.pending_approval', 'Periksa PO yang menunggu persetujuan.'),
                $item('purchasing.not_shipped', 'Periksa PO disetujui yang belum dikirim.'),
                $item('purchasing.arrivals', 'Periksa jadwal kedatangan hari ini dan besok.'),
                $item('purchasing.coordinate', 'Koordinasikan kedatangan dengan gudang atau toko tujuan.'),
                $item('purchasing.receipts', 'Periksa penerimaan yang baru diposting.'),
                $item('purchasing.issues', 'Tindak lanjuti kekurangan, penolakan, atau kerusakan.'),
                $item('purchasing.overdue', 'Periksa PO yang melewati jadwal.'),
                $item('purchasing.communication', 'Catat komunikasi penting dengan supplier.'),
            ],
        ],
        [
            'key' => 'purchasing_weekly', 'name' => 'Checklist Mingguan Purchasing', 'frequency' => 'weekly', 'scope' => 'global', 'location_type' => null,
            'roles' => ['purchasing'],
            'items' => [
                $item('purchasing_weekly.suppliers', 'Review performa supplier.'),
                $item('purchasing_weekly.cost', 'Review perubahan harga dan HPP terbesar.'),
                $item('purchasing_weekly.partial', 'Review PO parsial yang terlalu lama.'),
                $item('purchasing_weekly.contacts', 'Review supplier tidak aktif atau kontak kedaluwarsa.'),
                $item('purchasing_weekly.demand', 'Review kebutuhan pembelian berdasarkan tren stok.'),
                $item('purchasing_weekly.reconcile', 'Rekonsiliasi PO, penerimaan, dan tagihan.'),
                $item('purchasing_weekly.actions', 'Dokumentasikan isu supplier dan rencana tindak lanjut.'),
            ],
        ],
        [
            'key' => 'store_manager_daily', 'name' => 'Checklist Harian Kepala Toko dan Supervisor', 'frequency' => 'daily', 'scope' => 'location', 'location_type' => 'branch',
            'roles' => ['kepala_toko', 'supervisor_shift'],
            'items' => [
                $item('store_manager.dashboard', 'Dashboard cabang diperiksa.'),
                $item('store_manager.shifts', 'Shift aktif dan closing diperiksa.'),
                $item('store_manager.cash_difference', 'Selisih kas ditindaklanjuti.'),
                $item('store_manager.restock', 'Stok kritis dibuatkan permintaan restok.'),
                $item('store_manager.transfer', 'Transfer masuk diterima sesuai kondisi fisik.'),
                $item('store_manager.returns', 'Retur dan void direview.'),
                $item('store_manager.receivables', 'Piutang toko dan pengingat diperiksa.'),
                $item('store_manager.attendance', 'Kehadiran karyawan diperiksa.'),
            ],
        ],
        [
            'key' => 'store_manager_weekly', 'name' => 'Checklist Mingguan Kepala Toko dan Supervisor', 'frequency' => 'weekly', 'scope' => 'location', 'location_type' => 'branch',
            'roles' => ['kepala_toko', 'supervisor_shift'],
            'items' => [
                $item('store_manager_weekly.sales', 'Review penjualan, margin, dan produk utama cabang.'),
                $item('store_manager_weekly.stock', 'Review stok kritis, selisih, dan barang lambat bergerak.'),
                $item('store_manager_weekly.cash', 'Review selisih kas, retur, dan void.'),
                $item('store_manager_weekly.team', 'Review kehadiran dan kendala tim toko.'),
            ],
        ],
        [
            'key' => 'store_staff_daily', 'name' => 'Checklist Harian Staf Toko', 'frequency' => 'daily', 'scope' => 'location', 'location_type' => 'branch',
            'roles' => ['staf_toko'],
            'items' => [
                $item('store_staff.catalog', 'Periksa produk, harga, dan stok pada etalase.'),
                $item('store_staff.placement', 'Pastikan lokasi pajang produk sesuai kondisi toko.'),
                $item('store_staff.service', 'Tindak lanjuti kebutuhan pelanggan yang belum selesai.'),
                $item('store_staff.emergency', 'Catat kekurangan dan pembelian darurat bila diperlukan.'),
                $item('store_staff.product_request', 'Ajukan produk baru yang diminta pelanggan bila diperlukan.'),
                $item('store_staff.handover', 'Sampaikan kendala dan pekerjaan terbuka saat serah terima.'),
            ],
        ],
        [
            'key' => 'cashier_daily', 'name' => 'Checklist Harian Kasir', 'frequency' => 'daily', 'scope' => 'location', 'location_type' => 'branch',
            'roles' => ['kasir'],
            'items' => [
                $item('cashier.check_in', 'Check-in sudah dilakukan.'),
                $item('cashier.open_shift', 'Shift dibuka dengan modal awal yang benar.'),
                $item('cashier.transactions', 'Semua transaksi POS sudah dicatat.'),
                $item('cashier.holds', 'Transaksi hold lama sudah diperiksa.'),
                $item('cashier.returns', 'Retur atau void sudah disertai alasan.'),
                $item('cashier.cash_count', 'Kas fisik sudah dihitung.'),
                $item('cashier.close_shift', 'Closing shift sudah disubmit.'),
                $item('cashier.check_out', 'Check-out sudah dilakukan.'),
            ],
        ],
        [
            'key' => 'sales_daily', 'name' => 'Checklist Harian Sales', 'frequency' => 'daily', 'scope' => 'global', 'location_type' => null,
            'roles' => ['sales'],
            'items' => [
                $item('sales.customers', 'Periksa pelanggan yang menjadi tanggung jawab Anda.'),
                $item('sales.follow_up', 'Tindak lanjuti pelanggan dan order yang masih terbuka.'),
                $item('sales.stock_price', 'Pastikan stok serta harga sebelum membuat order.'),
                $item('sales.order_notes', 'Catat kebutuhan dan kendala pelanggan pada order.'),
                $item('sales.target', 'Periksa perkembangan target penjualan.'),
            ],
        ],
        [
            'key' => 'sales_weekly', 'name' => 'Checklist Mingguan Sales', 'frequency' => 'weekly', 'scope' => 'global', 'location_type' => null,
            'roles' => ['sales'],
            'items' => [
                $item('sales_weekly.target', 'Review pencapaian target dan selisih terhadap rencana.'),
                $item('sales_weekly.pipeline', 'Review order terbuka dan peluang tindak lanjut.'),
                $item('sales_weekly.inactive_customers', 'Review pelanggan yang belum melakukan pemesanan.'),
                $item('sales_weekly.issues', 'Ringkas kendala pelanggan untuk tindak lanjut manajemen.'),
            ],
        ],
    ],
];
