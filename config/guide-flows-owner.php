<?php

return [
    'owner-responsibilities' => [
        'title' => 'Tanggung jawab dan batas role Owner',
        'summary' => 'Owner Viewer memantau dan mengekspor, sedangkan Owner Approver juga memutuskan tindakan sensitif melalui workflow sumbernya.',
        'verified_by' => ['config/rbac.php', 'config/role-guides.php'],
        'lanes' => [
            ['label' => 'Owner Viewer', 'tone' => 'primary', 'steps' => [
                ['label' => 'Baca dashboard dan laporan', 'detail' => 'Termasuk margin sensitif dan laporan lintas modul.'],
                ['label' => 'Telusuri audit', 'detail' => 'Lihat log, keamanan, anomali, dan export.'],
                ['label' => 'Tidak mengambil keputusan approval', 'detail' => 'Role ini bersifat read-only.', 'kind' => 'result'],
            ]],
            ['label' => 'Owner Approver', 'tone' => 'success', 'steps' => [
                ['label' => 'Lakukan seluruh review owner', 'detail' => 'Memiliki akses lihat yang sama.'],
                ['label' => 'Baca alasan dan dampak', 'detail' => 'Periksa risiko stok, kas, harga, atau piutang.'],
                ['label' => 'Approve atau reject', 'detail' => 'Keputusan dilakukan pada inbox atau modul sumber.', 'kind' => 'result'],
            ]],
        ],
    ],

    'owner-overview' => [
        'title' => 'Peta kontrol Owner',
        'summary' => 'Dashboard memberi sinyal awal; laporan, approval, audit, invoice, dan piutang menyediakan bukti untuk keputusan.',
        'verified_by' => ['config/navigation.php', 'routes/web.php'],
        'lanes' => [
            ['label' => 'Pantau', 'tone' => 'primary', 'steps' => [
                ['label' => 'Dashboard Owner', 'detail' => 'Omzet, margin, stok, piutang, anomali, dan approval.'],
                ['label' => 'Laporan per domain', 'detail' => 'Harian, gudang, toko, B2B, pricing, supplier, dan piutang.'],
                ['label' => 'Buka detail sumber', 'detail' => 'Gunakan invoice, stok, audit, atau dokumen transaksi.'],
            ]],
            ['label' => 'Putuskan', 'tone' => 'warning', 'steps' => [
                ['label' => 'Nilai risiko dan bukti', 'detail' => 'Bandingkan data sebelum/sesudah serta dampaknya.'],
                ['label' => 'Pilih workflow keputusan', 'detail' => 'Inbox terpusat atau halaman modul terkait.'],
                ['label' => 'Pantau tindak lanjut', 'detail' => 'Keputusan dan koreksi harus dapat diaudit.', 'kind' => 'result'],
            ]],
        ],
    ],

    'owner-dashboard' => [
        'title' => 'Membaca Dashboard Owner',
        'summary' => 'KPI menggabungkan data penjualan POS, B2B, stok, piutang, shift, kehadiran, approval, retur, dan anomali sesuai filter.',
        'verified_by' => ['app/Http/Controllers/Reports/OwnerDashboardController.php', 'app/Services/Reports/ReportMetricService.php'],
        'lanes' => [[
            'label' => 'Review dashboard', 'tone' => 'primary', 'steps' => [
                ['label' => 'Pilih periode dan lokasi', 'detail' => 'Lokasi yang tersedia mengikuti scope pengguna.'],
                ['label' => 'Baca KPI utama', 'detail' => 'Revenue, margin, nilai stok, transaksi, piutang, selisih kas, dan retur.'],
                ['label' => 'Periksa indikator risiko', 'detail' => 'Stok kritis, piutang overdue, telat, anomali, dan approval pending.'],
                ['label' => 'Bandingkan grafik', 'detail' => 'Tren revenue, channel mix, margin cabang, produk, dan aging.'],
                ['label' => 'Buka laporan detail', 'detail' => 'Gunakan sinyal dashboard untuk investigasi, bukan sebagai bukti tunggal.', 'kind' => 'result'],
            ],
        ]],
    ],

    'owner-report-review' => [
        'title' => 'Menelusuri laporan Owner',
        'summary' => 'Semua laporan memakai filter tervalidasi dan data lokasi yang tersedia bagi pengguna.',
        'verified_by' => ['app/Http/Controllers/Reports/ReportController.php', 'app/Services/Reports/ReportMetricService.php'],
        'lanes' => [[
            'label' => 'Dari ringkasan ke bukti', 'tone' => 'primary', 'steps' => [
                ['label' => 'Pilih jenis laporan', 'detail' => 'Harian, gudang, retail, B2B, pricing, atau piutang.'],
                ['label' => 'Atur periode dan lokasi', 'detail' => 'Filter diterapkan pada seluruh metrik laporan.'],
                ['label' => 'Bandingkan ringkasan dan tren', 'detail' => 'Cari penyimpangan terhadap periode atau lokasi lain.'],
                ['label' => 'Buka dokumen sumber', 'detail' => 'Transaksi, mutasi, invoice, shift, atau receipt.'],
                ['label' => 'Catat keputusan', 'detail' => 'Tindak lanjut diberikan kepada pemilik proses terkait.', 'kind' => 'result'],
            ],
        ]],
    ],

    'owner-report-export' => [
        'title' => 'Meminta dan mengunduh export laporan',
        'summary' => 'Export dibuat melalui antrean, memiliki filter dan masa berlaku, lalu diunduh dari pusat export setelah selesai.',
        'verified_by' => ['app/Http/Controllers/Reports/ReportExportController.php', 'app/Services/Reports/ReportExportService.php'],
        'lanes' => [[
            'label' => 'Export', 'tone' => 'warning', 'steps' => [
                ['label' => 'Pilih laporan dan format', 'detail' => 'Filter periode, lokasi, channel, serta entitas disimpan.'],
                ['label' => 'Masuk antrean', 'detail' => 'Status awal export adalah Queued.'],
                ['label' => 'Worker membuat file', 'detail' => 'GenerateReportExportJob memproses data di belakang layar.'],
                ['label' => 'File tersedia?', 'detail' => 'Download ditolak bila file belum ada atau sudah kedaluwarsa.', 'kind' => 'decision'],
                ['label' => 'Unduh dari pusat export', 'detail' => 'Akses mengikuti permission dan kepemilikan request.', 'kind' => 'result'],
            ],
        ]],
    ],

    'owner-approval-inbox' => [
        'title' => 'Memutuskan approval terpusat',
        'summary' => 'Inbox terpusat memproses permintaan harga dan pembelian darurat yang terdaftar; approval modul lain tetap diputuskan pada modul sumber.',
        'verified_by' => ['app/Http/Controllers/Control/ApprovalInboxController.php', 'app/Services/Control/ApprovalWorkflowService.php'],
        'lanes' => [[
            'label' => 'Keputusan approval', 'tone' => 'warning', 'steps' => [
                ['label' => 'Filter Pending', 'detail' => 'Gunakan modul, tingkat risiko, atau pemohon.'],
                ['label' => 'Baca alasan dan snapshot', 'detail' => 'Payload sensitif sudah disaring saat request dibuat.'],
                ['label' => 'Periksa syarat approver', 'detail' => 'Permission, role, lokasi tertentu, kedaluwarsa, dan pemisahan tugas.'],
                ['label' => 'Approve atau reject', 'detail' => 'Keputusan hanya dapat dilakukan saat masih Pending.', 'kind' => 'decision'],
                ['label' => 'Jalankan handler dan audit', 'detail' => 'Harga atau pembelian darurat diperbarui dalam transaksi yang sama.', 'kind' => 'result'],
            ],
        ]],
    ],

    'owner-stock-review' => [
        'title' => 'Meninjau kesehatan stok dan gudang',
        'summary' => 'Owner membaca saldo dan nilai stok, lalu memakai kartu stok untuk menelusuri setiap penyimpangan ke dokumen sumber.',
        'verified_by' => ['app/Http/Controllers/Warehouse/StockController.php', 'app/Http/Controllers/Warehouse/StockCardController.php', 'app/Services/Inventory/InventoryService.php'],
        'lanes' => [[
            'label' => 'Review stok', 'tone' => 'primary', 'steps' => [
                ['label' => 'Filter lokasi dan kondisi', 'detail' => 'Fokus pada kosong, kritis, reserved, damaged, dan nilai besar.'],
                ['label' => 'Hitung available', 'detail' => 'On hand dikurangi reserved dan damaged.'],
                ['label' => 'Ada penyimpangan?', 'detail' => 'Bandingkan dengan kebutuhan, minimum, dan aktivitas terbaru.', 'kind' => 'decision'],
                ['label' => 'Buka kartu stok', 'detail' => 'Telusuri before, change, after, actor, dan dokumen sumber.'],
                ['label' => 'Minta koreksi resmi', 'detail' => 'Gunakan opname, adjustment, retur, loss, atau reversal.', 'kind' => 'result'],
            ],
        ]],
    ],

    'owner-pricing-review' => [
        'title' => 'Meninjau harga, HPP, dan margin',
        'summary' => 'Owner membandingkan harga yang berlaku dengan HPP, batas harga, aturan margin, dan snapshot transaksi.',
        'verified_by' => ['app/Services/Pricing/PriceManagementService.php', 'app/Services/Pricing/PriceResolverService.php', 'app/Http/Controllers/Pricing/HppHistoryController.php'],
        'lanes' => [[
            'label' => 'Review pricing', 'tone' => 'warning', 'steps' => [
                ['label' => 'Periksa histori HPP', 'detail' => 'Cari receipt atau stok awal yang mengubah biaya.'],
                ['label' => 'Periksa aturan harga', 'detail' => 'Channel, cabang, price ring, periode, dan minimum margin.'],
                ['label' => 'Bandingkan harga yang diminta', 'detail' => 'Resolver menghitung minimum, maksimum, margin, dan diskon.'],
                ['label' => 'Perlu approval?', 'detail' => 'Harga sensitif tetap Draft atau Pending sampai diputuskan.', 'kind' => 'decision'],
                ['label' => 'Aktif atau ditolak', 'detail' => 'Keputusan serta snapshot biaya disimpan untuk audit.', 'kind' => 'result'],
            ],
        ]],
    ],

    'owner-receivable-review' => [
        'title' => 'Meninjau piutang dan limit kredit',
        'summary' => 'Dashboard piutang menyusun saldo ledger menjadi aging, arus invoice-pembayaran, pelanggan risiko, dan tindak lanjut.',
        'verified_by' => ['app/Http/Controllers/Receivables/ReceivableController.php', 'app/Services/Receivables/ReceivableService.php'],
        'lanes' => [[
            'label' => 'Review piutang', 'tone' => 'danger', 'steps' => [
                ['label' => 'Refresh aging', 'detail' => 'Status dan bucket dihitung ulang dari tanggal jatuh tempo.'],
                ['label' => 'Baca outstanding dan overdue', 'detail' => 'Pisahkan channel gudang/B2B dan retail bila perlu.'],
                ['label' => 'Prioritaskan risiko', 'detail' => 'Pelanggan overdue, melewati limit, atau follow-up jatuh tempo.'],
                ['label' => 'Telusuri ledger', 'detail' => 'Invoice, pembayaran, dan adjustment mengubah saldo secara append-only.'],
                ['label' => 'Butuh keputusan?', 'detail' => 'Owner Approver dapat memutuskan credit note atau mengelola limit sesuai izin.', 'kind' => 'decision'],
                ['label' => 'Pantau tindak lanjut', 'detail' => 'Saldo tidak boleh negatif atau diubah manual.', 'kind' => 'result'],
            ],
        ]],
    ],

    'owner-audit-review' => [
        'title' => 'Meninjau audit, anomali, dan keamanan',
        'summary' => 'Audit menghubungkan actor dan perubahan; anomali memberi status review tanpa mengubah transaksi sumber.',
        'verified_by' => ['app/Http/Controllers/Control/AuditLogController.php', 'app/Http/Controllers/Control/AnomalyController.php', 'app/Http/Controllers/Control/SecurityAuditController.php'],
        'lanes' => [
            ['label' => 'Anomali', 'tone' => 'danger', 'steps' => [
                ['label' => 'Filter Open dan severity', 'detail' => 'Prioritaskan risiko tinggi.'],
                ['label' => 'Periksa subject dan evidence', 'detail' => 'Buka log serta dokumen transaksi asal.'],
                ['label' => 'Valid?', 'detail' => 'Tandai Reviewed, Resolved, atau False Positive dengan catatan.', 'kind' => 'decision'],
                ['label' => 'Minta koreksi sumber', 'detail' => 'Resolve alert tidak mengubah transaksi asal.', 'kind' => 'result'],
            ]],
            ['label' => 'Keamanan', 'tone' => 'warning', 'steps' => [
                ['label' => 'Pantau login dan perubahan akun', 'detail' => 'Login berhasil/gagal, rate limit, reset, sesi, role, dan user.'],
                ['label' => 'Periksa pola berulang', 'detail' => 'Dashboard menandai login gagal dan IP yang ramai.'],
                ['label' => 'Eskalasi ke Super Admin', 'detail' => 'Tindakan akun dan sistem dilakukan oleh role pengelola.', 'kind' => 'result'],
            ]],
        ],
    ],

    'owner-sales-review' => [
        'title' => 'Meninjau performa tim Sales',
        'summary' => 'Halaman performa membandingkan seluruh Sales pada bulan dan tahun yang dipilih tanpa menjadikan owner sebagai Sales operasional.',
        'verified_by' => ['app/Http/Controllers/Sales/AdminController.php', 'app/Services/Sales/SalesPerformanceService.php'],
        'lanes' => [[
            'label' => 'Review Sales', 'tone' => 'primary', 'steps' => [
                ['label' => 'Buka performa Sales', 'detail' => 'Gunakan /sales/performance, bukan dashboard pribadi Sales.'],
                ['label' => 'Pilih bulan dan tahun', 'detail' => 'Service menghitung hasil semua anggota Sales.'],
                ['label' => 'Bandingkan target dan realisasi', 'detail' => 'Tinjau order, omzet, pencapaian, dan bonus sesuai data.'],
                ['label' => 'Ada penyimpangan?', 'detail' => 'Telusuri pelanggan dan order melalui role operasional yang berwenang.', 'kind' => 'decision'],
                ['label' => 'Tetapkan tindak lanjut', 'detail' => 'Owner memberi keputusan tanpa mengambil alih order Sales.', 'kind' => 'result'],
            ],
        ]],
    ],

    'owner-tax-review' => [
        'title' => 'Meninjau dan menutup masa pajak',
        'summary' => 'Owner Approver mengelola register dan menjalankan status masa pajak secara berurutan; Owner Viewer tidak memiliki tax.access.',
        'verified_by' => ['app/Http/Controllers/Tax/TaxComplianceController.php', 'app/Services/Tax/TaxComplianceService.php', 'app/Enums/TaxPeriodStatus.php'],
        'lanes' => [
            ['label' => 'Rekonsiliasi', 'tone' => 'warning', 'steps' => [
                ['label' => 'Pilih masa pajak', 'detail' => 'Periode baru dimulai dengan status Open.'],
                ['label' => 'Sinkronkan transaksi', 'detail' => 'Invoice, POS completed/returned, dan retur POS completed.'],
                ['label' => 'Periksa masalah', 'detail' => 'Unmatched, identitas pajak kosong, dan dokumen draft.'],
                ['label' => 'Reconcile atau reverse', 'detail' => 'Koreksi memakai status dan alasan tanpa menghapus histori.'],
            ]],
            ['label' => 'Status masa', 'tone' => 'success', 'steps' => [
                ['label' => 'Open ke Reviewed', 'detail' => 'Nilai pajak dihitung ulang.'],
                ['label' => 'Approved', 'detail' => 'Hanya setelah Reviewed.'],
                ['label' => 'Reported lalu Paid', 'detail' => 'Simpan referensi pelaporan dan pembayaran.'],
                ['label' => 'Locked', 'detail' => 'Periode tidak dapat diubah sampai dibuka kembali dengan alasan.', 'kind' => 'result'],
            ]],
        ],
    ],

    'owner-po-status' => [
        'title' => 'Membaca status Purchase Order',
        'summary' => 'Status PO menunjukkan apakah dokumen masih disiapkan, menunggu keputusan, sudah dikirim, atau telah diterima.',
        'verified_by' => ['app/Enums/PurchaseOrderStatus.php', 'app/Services/Purchasing/PurchaseOrderService.php'],
        'lanes' => [[
            'label' => 'Status PO', 'tone' => 'primary', 'steps' => [
                ['label' => 'Draft', 'detail' => 'Masih disiapkan.'],
                ['label' => 'Submitted', 'detail' => 'Menunggu approval.'],
                ['label' => 'Approved', 'detail' => 'Siap dikirim ke supplier.'],
                ['label' => 'Sent to Supplier', 'detail' => 'Menunggu penerimaan.'],
                ['label' => 'Partially Received?', 'detail' => 'Masih ada outstanding per item.', 'kind' => 'decision'],
                ['label' => 'Completed', 'detail' => 'Seluruh qty telah diperhitungkan.', 'kind' => 'result'],
            ],
        ]],
    ],

    'owner-receipt-status' => [
        'title' => 'Membaca status Goods Receipt',
        'summary' => 'Receipt hanya memiliki Draft, Posted, dan Cancelled; koreksi stok dilakukan melalui dokumen koreksi lain.',
        'verified_by' => ['app/Enums/GoodsReceiptStatus.php', 'app/Services/Warehouse/GoodsReceiptService.php'],
        'lanes' => [[
            'label' => 'Status receipt', 'tone' => 'success', 'steps' => [
                ['label' => 'Draft', 'detail' => 'Belum memengaruhi stok, HPP, PO, atau skor supplier.'],
                ['label' => 'Posting?', 'detail' => 'QC dan lokasi harus diperiksa sebelum tindakan final.', 'kind' => 'decision'],
                ['label' => 'Posted', 'detail' => 'Stok, HPP, penerimaan PO, dan skor supplier sudah diperbarui.'],
                ['label' => 'Cancelled', 'detail' => 'Status pembatalan tersedia pada enum; transaksi posted tidak diedit langsung.', 'kind' => 'result'],
            ],
        ]],
    ],

    'owner-transfer-status' => [
        'title' => 'Membaca status transfer stok',
        'summary' => 'Reservasi terjadi saat approved, stok sumber keluar saat shipped, dan stok tujuan masuk saat receive.',
        'verified_by' => ['app/Enums/StockTransferStatus.php', 'app/Services/Warehouse/StockTransferService.php'],
        'lanes' => [[
            'label' => 'Status transfer', 'tone' => 'warning', 'steps' => [
                ['label' => 'Draft atau Pending', 'detail' => 'Stok belum berubah.'],
                ['label' => 'Approved', 'detail' => 'Stok sumber di-reserve.'],
                ['label' => 'Packing', 'detail' => 'Qty picked dan short pick dicatat.'],
                ['label' => 'Shipped', 'detail' => 'Reservasi dilepas dan on hand sumber berkurang.'],
                ['label' => 'Partially/Fully Received', 'detail' => 'Tujuan mencatat baik, rusak, dan discrepancy.'],
                ['label' => 'Completed', 'detail' => 'Seluruh penerimaan dan selisih selesai.', 'kind' => 'result'],
            ],
        ]],
    ],

    'owner-shift-status' => [
        'title' => 'Membaca status shift kasir',
        'summary' => 'Status shift menunjukkan apakah kasir dapat bertransaksi, sedang menunggu verifikasi, perlu koreksi, atau sudah ditutup.',
        'verified_by' => ['app/Enums/CashShiftStatus.php', 'app/Services/Retail/CashShiftService.php'],
        'lanes' => [[
            'label' => 'Status shift', 'tone' => 'primary', 'steps' => [
                ['label' => 'Open', 'detail' => 'Kasir dapat menjalankan POS dan mencatat kas.'],
                ['label' => 'Closing Submitted', 'detail' => 'Kasir mengirim hitungan fisik untuk verifikasi.'],
                ['label' => 'Selisih dapat diterima?', 'detail' => 'Approver membaca expected, actual, difference, dan alasan.', 'kind' => 'decision'],
                ['label' => 'Rejected', 'detail' => 'Shift dikembalikan untuk koreksi.'],
                ['label' => 'Approved atau Closed', 'detail' => 'Closing terkunci dan tidak dapat diubah bebas.', 'kind' => 'result'],
            ],
        ]],
    ],

    'owner-guardrails' => [
        'title' => 'Pagar pengaman keputusan Owner',
        'summary' => 'Owner menjaga pemisahan tugas, bukti, histori, dan workflow koreksi tanpa mengambil alih input operasional.',
        'verified_by' => ['config/rbac.php', 'app/Services/Control/ApprovalWorkflowService.php', 'app/Services/Control/AuditLogService.php'],
        'lanes' => [[
            'label' => 'Sebelum memutuskan', 'tone' => 'danger', 'steps' => [
                ['label' => 'Gunakan akun owner sendiri', 'detail' => 'Jangan memakai Super Admin atau akun petugas.'],
                ['label' => 'Baca dokumen dan dampak', 'detail' => 'Nilai stok, kas, harga, piutang, serta risiko.'],
                ['label' => 'Periksa pemisahan tugas', 'detail' => 'Requester tidak boleh memutuskan request sendiri bila aturan aktif.'],
                ['label' => 'Gunakan workflow sumber', 'detail' => 'Inbox, PO, opname, retur, shift, atau piutang.'],
                ['label' => 'Simpan alasan keputusan', 'detail' => 'Histori dan audit menjadi bukti.'],
                ['label' => 'Pantau koreksi resmi', 'detail' => 'Tidak ada edit database atau penghapusan transaksi final.', 'kind' => 'result'],
            ],
        ]],
    ],

    'owner-daily' => [
        'title' => 'Siklus harian Owner',
        'summary' => 'Review harian bergerak dari sinyal dashboard ke keputusan dan tindak lanjut risiko yang paling mendesak.',
        'verified_by' => ['app/Http/Controllers/Reports/OwnerDashboardController.php', 'app/Http/Controllers/Control/ApprovalInboxController.php', 'app/Http/Controllers/Control/AnomalyController.php'],
        'lanes' => [[
            'label' => 'Setiap hari', 'tone' => 'primary', 'steps' => [
                ['label' => 'Periksa Dashboard Owner', 'detail' => 'Pilih periode dan lokasi yang relevan.'],
                ['label' => 'Putuskan approval tertunda', 'detail' => 'Gunakan inbox dan modul sumber.'],
                ['label' => 'Tinjau stok dan margin', 'detail' => 'Prioritaskan stok kritis serta margin tidak sehat.'],
                ['label' => 'Tinjau piutang overdue', 'detail' => 'Pantau pelanggan berisiko dan follow-up.'],
                ['label' => 'Tinjau anomali tinggi', 'detail' => 'Validasi bukti dan tentukan status.'],
                ['label' => 'Pastikan tindak lanjut', 'detail' => 'Setiap risiko memiliki pemilik dan tindakan.', 'kind' => 'result'],
            ],
        ]],
    ],

    'owner-weekly' => [
        'title' => 'Siklus mingguan Owner',
        'summary' => 'Review mingguan membandingkan performa supplier, cabang, produk, risiko transaksi, kredit, dan kesiapan sistem.',
        'verified_by' => ['app/Services/Reports/ReportMetricService.php', 'app/Http/Controllers/Reports/SupplierPerformanceController.php', 'app/Http/Controllers/Receivables/ReceivableController.php'],
        'lanes' => [[
            'label' => 'Setiap minggu', 'tone' => 'success', 'steps' => [
                ['label' => 'Bandingkan supplier dan cabang', 'detail' => 'Skor supplier, revenue, margin, serta selisih kas.'],
                ['label' => 'Tinjau pergerakan produk', 'detail' => 'Fast moving, slow moving, kritis, dan nilai stok.'],
                ['label' => 'Tinjau loss, retur, dan koreksi', 'detail' => 'Telusuri dokumen serta pola berulang.'],
                ['label' => 'Tinjau limit kredit', 'detail' => 'Bandingkan saldo, overdue, dan pelanggan over limit.'],
                ['label' => 'Review backup dan health', 'detail' => 'Koordinasikan bukti teknis dengan Super Admin.'],
                ['label' => 'Tetapkan tindakan minggu berikutnya', 'detail' => 'Catat pemilik, tenggat, dan ukuran keberhasilan.', 'kind' => 'result'],
            ],
        ]],
    ],
];
