<?php

return [
    'purchasing-responsibilities' => [
        'title' => 'Tanggung jawab Purchasing',
        'summary' => 'Purchasing menjaga kesinambungan kebutuhan, supplier, PO, penerimaan, biaya, dan tindak lanjut dalam dokumen yang dapat diaudit.',
        'verified_by' => ['config/rbac.php', 'app/Policies/PurchaseOrderPolicy.php', 'app/Policies/SupplierPolicy.php'],
        'lanes' => [[
            'label' => 'Rantai tanggung jawab', 'tone' => 'warning', 'steps' => [
                ['label' => 'Validasi kebutuhan', 'detail' => 'Periksa stok, request, dan PO outstanding.'],
                ['label' => 'Kelola supplier', 'detail' => 'Pastikan data, kontak, dan termin benar.'],
                ['label' => 'Susun dan pantau PO', 'detail' => 'Harga, jumlah, pajak, biaya, serta ETA tercatat.'],
                ['label' => 'Cocokkan penerimaan', 'detail' => 'Tinjau hasil QC dan outstanding bersama gudang.'],
                ['label' => 'Evaluasi hasil', 'detail' => 'Periksa HPP, skor supplier, selisih, dan retur.', 'kind' => 'result'],
            ],
        ]],
    ],

    'purchasing-overview' => [
        'title' => 'Peta fitur Purchasing dan Supplier',
        'summary' => 'Setiap menu menyediakan bukti untuk satu tahap pembelian, dari kebutuhan hingga evaluasi supplier.',
        'verified_by' => ['config/navigation.php', 'routes/web.php'],
        'lanes' => [
            ['label' => 'Persiapan', 'tone' => 'primary', 'steps' => [
                ['label' => 'Produk dan saldo stok', 'detail' => 'Lihat SKU, unit, stok minimum, dan kondisi kritis.'],
                ['label' => 'Master supplier', 'detail' => 'Kelola identitas, kontak, termin, dan status.'],
                ['label' => 'Permintaan pembelian', 'detail' => 'Catat kebutuhan dan keputusan approval.'],
            ]],
            ['label' => 'Pelaksanaan', 'tone' => 'success', 'steps' => [
                ['label' => 'Purchase Order', 'detail' => 'Susun, submit, pantau approval, dan tandai terkirim.'],
                ['label' => 'Goods Receipt', 'detail' => 'Pantau hasil penerimaan dan QC dari gudang.'],
                ['label' => 'HPP dan performa', 'detail' => 'Tinjau biaya aktual dan skor supplier.', 'kind' => 'result'],
            ]],
        ],
    ],

    'purchasing-end-to-end' => [
        'title' => 'Alur pembelian dari kebutuhan sampai evaluasi',
        'summary' => 'PO menjadi penghubung resmi antara kebutuhan internal, supplier, penerimaan gudang, HPP, dan evaluasi.',
        'verified_by' => ['app/Http/Controllers/Purchasing/PurchaseRequestController.php', 'app/Services/Purchasing/PurchaseOrderService.php', 'app/Services/Warehouse/GoodsReceiptService.php'],
        'lanes' => [[
            'label' => 'Alur normal', 'tone' => 'warning', 'steps' => [
                ['label' => 'Periksa kebutuhan', 'detail' => 'Stok kritis, request baru, dan PO outstanding.'],
                ['label' => 'Validasi produk dan supplier', 'detail' => 'Data aktif, unit, termin, harga, dan ETA.'],
                ['label' => 'Request disetujui', 'detail' => 'Kebutuhan dapat dikonversi menjadi PO draft.'],
                ['label' => 'PO disetujui dan dikirim', 'detail' => 'Hanya PO approved yang ditandai terkirim.'],
                ['label' => 'Gudang menerima dan QC', 'detail' => 'Receipt posted memperbarui stok, PO, dan HPP.'],
                ['label' => 'Evaluasi dan tindak lanjut', 'detail' => 'Pantau skor supplier, shortage, damaged, dan retur.', 'kind' => 'result'],
            ],
        ]],
    ],

    'supplier-create' => [
        'title' => 'Menambahkan supplier',
        'summary' => 'Supplier dibuat dengan kode unik, data kontak, termin, pajak, rekening, catatan, dan status aktif.',
        'verified_by' => ['app/Http/Controllers/Admin/SupplierController.php', 'app/Http/Requests/Admin/StoreSupplierRequest.php'],
        'lanes' => [[
            'label' => 'Master supplier baru', 'tone' => 'primary', 'steps' => [
                ['label' => 'Periksa izin tambah', 'detail' => 'Memerlukan suppliers.create.'],
                ['label' => 'Isi kode dan nama', 'detail' => 'Kode wajib unik dan berbentuk alpha-dash.'],
                ['label' => 'Isi kontak dan termin', 'detail' => 'Email, WhatsApp, alamat, serta termin divalidasi.'],
                ['label' => 'Isi data pendukung', 'detail' => 'Pajak, rekening, dan catatan bersifat opsional.'],
                ['label' => 'Simpan dalam transaksi', 'detail' => 'Supplier dibuat dan aktivitas supplier.created dicatat.', 'kind' => 'result'],
            ],
        ]],
    ],

    'supplier-update' => [
        'title' => 'Memperbarui data supplier',
        'summary' => 'Perubahan memakai validasi yang sama, menjaga keunikan kode, dan mencatat aktivitas pembaruan.',
        'verified_by' => ['app/Http/Controllers/Admin/SupplierController.php', 'app/Http/Requests/Admin/UpdateSupplierRequest.php'],
        'lanes' => [[
            'label' => 'Pembaruan', 'tone' => 'warning', 'steps' => [
                ['label' => 'Cari dan buka supplier', 'detail' => 'Detail juga menampilkan PO dan receipt terbaru.'],
                ['label' => 'Periksa izin ubah', 'detail' => 'Memerlukan suppliers.update.'],
                ['label' => 'Ubah data terkonfirmasi', 'detail' => 'Kode tetap harus unik; termin maksimal 365 hari.'],
                ['label' => 'Simpan', 'detail' => 'Perubahan dibungkus transaksi database.'],
                ['label' => 'Catat audit', 'detail' => 'Aktivitas supplier.updated disimpan.', 'kind' => 'result'],
            ],
        ]],
    ],

    'supplier-deactivate' => [
        'title' => 'Menonaktifkan supplier',
        'summary' => 'Penonaktifan mempertahankan histori, tetapi supplier tidak lagi tersedia pada pilihan PO baru.',
        'verified_by' => ['app/Http/Controllers/Admin/SupplierController.php', 'app/Http/Controllers/Purchasing/PurchaseOrderController.php'],
        'lanes' => [[
            'label' => 'Penonaktifan', 'tone' => 'danger', 'steps' => [
                ['label' => 'Periksa kewajiban operasional', 'detail' => 'Tinjau PO aktif, pembayaran, klaim, dan retur.'],
                ['label' => 'Periksa izin ubah', 'detail' => 'Memerlukan suppliers.update.'],
                ['label' => 'Set is_active false', 'detail' => 'Data supplier tidak dihapus.'],
                ['label' => 'Hapus dari pilihan PO baru', 'detail' => 'Form PO hanya memuat supplier aktif.'],
                ['label' => 'Pertahankan histori', 'detail' => 'PO dan receipt lama tetap dapat ditelusuri.', 'kind' => 'result'],
            ],
        ]],
    ],

    'supplier-import' => [
        'title' => 'Mengimpor supplier',
        'summary' => 'Import memakai preview wajib; commit hanya berjalan ketika seluruh baris bebas dari error.',
        'verified_by' => ['app/Http/Controllers/Admin/PartyImportController.php', 'app/Services/Party/PartyImportService.php'],
        'lanes' => [[
            'label' => 'Import data', 'tone' => 'primary', 'steps' => [
                ['label' => 'Unduh template supplier', 'detail' => 'Header resmi tidak boleh diubah.'],
                ['label' => 'Upload CSV atau spreadsheet', 'detail' => 'File dibaca menjadi baris data.'],
                ['label' => 'Preview dan validasi', 'detail' => 'Kode, nama, email, WhatsApp, dan termin diperiksa.'],
                ['label' => 'Ada error?', 'detail' => 'Commit ditolak sampai semua error diperbaiki.', 'kind' => 'decision'],
                ['label' => 'Commit dalam transaksi', 'detail' => 'Kode baru dibuat; kode yang sama diperbarui dan diaktifkan.', 'kind' => 'result'],
            ],
        ]],
    ],

    'purchasing-stock-needs' => [
        'title' => 'Menilai kebutuhan dari stok kritis',
        'summary' => 'Keputusan pembelian harus mempertimbangkan stok tersedia, minimum produk, reservasi, dan PO outstanding.',
        'verified_by' => ['app/Http/Controllers/Warehouse/StockController.php', 'app/Http/Controllers/Purchasing/PurchaseOrderController.php'],
        'lanes' => [[
            'label' => 'Analisis kebutuhan', 'tone' => 'warning', 'steps' => [
                ['label' => 'Filter lokasi dan kondisi', 'detail' => 'Pilih stok kritis atau kosong dalam penugasan.'],
                ['label' => 'Baca on hand dan available', 'detail' => 'Reserved serta damaged sudah mengurangi available.'],
                ['label' => 'Bandingkan minimum dan safety', 'detail' => 'Tentukan besarnya kekurangan operasional.'],
                ['label' => 'Periksa PO outstanding', 'detail' => 'Hindari membeli barang yang masih dalam perjalanan.'],
                ['label' => 'Ajukan kebutuhan realistis', 'detail' => 'Gunakan permintaan pembelian dengan produk, unit, qty, dan alasan.', 'kind' => 'result'],
            ],
        ]],
    ],

    'purchase-request-review' => [
        'title' => 'Meninjau Permintaan Pembelian',
        'summary' => 'Permintaan yang diajukan hanya dapat disetujui atau ditolak oleh approver; purchasing mengonversi permintaan approved menjadi PO.',
        'verified_by' => ['app/Http/Controllers/Purchasing/PurchaseRequestController.php', 'app/Policies/PurchaseRequestPolicy.php'],
        'lanes' => [[
            'label' => 'Review request', 'tone' => 'primary', 'steps' => [
                ['label' => 'Buka request Submitted', 'detail' => 'Periksa pemohon, gudang, prioritas, produk, unit, qty, dan alasan.'],
                ['label' => 'Bandingkan stok dan PO', 'detail' => 'Transfer atau PO aktif mungkin lebih tepat.'],
                ['label' => 'Approver memutuskan', 'detail' => 'Approve atau reject dengan alasan.', 'kind' => 'decision'],
                ['label' => 'Pilih supplier aktif', 'detail' => 'Hanya request Approved yang dapat dikonversi.'],
                ['label' => 'Buat PO Draft', 'detail' => 'Item dan qty request disalin; harga masih nol untuk dilengkapi.', 'kind' => 'result'],
            ],
        ]],
    ],

    'purchase-order-preparation' => [
        'title' => 'Menyiapkan Purchase Order',
        'summary' => 'Persiapan memastikan supplier, produk, harga, lead time, dan lokasi penerima sudah valid sebelum input PO.',
        'verified_by' => ['app/Http/Controllers/Purchasing/PurchaseOrderController.php', 'app/Http/Requests/Purchasing/StorePurchaseOrderRequest.php'],
        'lanes' => [[
            'label' => 'Persiapan', 'tone' => 'warning', 'steps' => [
                ['label' => 'Pilih kebutuhan', 'detail' => 'Gunakan request approved atau kebutuhan terverifikasi.'],
                ['label' => 'Pilih supplier aktif', 'detail' => 'Konfirmasi produk, harga, minimum order, dan lead time.'],
                ['label' => 'Pilih lokasi penerima', 'detail' => 'Gudang atau cabang aktif dalam penugasan.'],
                ['label' => 'Konfirmasi unit pembelian', 'detail' => 'Faktor konversi akan disimpan sebagai snapshot.'],
                ['label' => 'Siap input PO', 'detail' => 'Tanggal, biaya, termin, dan item dapat dimasukkan.', 'kind' => 'result'],
            ],
        ]],
    ],

    'purchase-order-review' => [
        'title' => 'Memeriksa draft Purchase Order',
        'summary' => 'Draft diperiksa dari identitas dokumen sampai grand total sebelum dijadikan permintaan resmi.',
        'verified_by' => ['app/Services/Purchasing/PurchaseOrderService.php', 'app/Http/Controllers/Purchasing/PurchaseOrderController.php'],
        'lanes' => [[
            'label' => 'Kontrol draft', 'tone' => 'primary', 'steps' => [
                ['label' => 'Periksa supplier dan tujuan', 'detail' => 'Pastikan dokumen ditujukan ke pihak dan lokasi yang benar.'],
                ['label' => 'Periksa item dan konversi', 'detail' => 'SKU, unit, faktor, dan qty harus konsisten.'],
                ['label' => 'Periksa harga dan biaya', 'detail' => 'Harga, diskon, pajak, ongkir, dan biaya tambahan.'],
                ['label' => 'Periksa tanggal dan termin', 'detail' => 'ETA tidak boleh sebelum tanggal order.'],
                ['label' => 'Total sudah benar?', 'detail' => 'Jika belum, edit selama status Draft atau Submitted.', 'kind' => 'decision'],
                ['label' => 'Siap diajukan', 'detail' => 'PO harus memiliki minimal satu item.', 'kind' => 'result'],
            ],
        ]],
    ],

    'purchase-order-approval' => [
        'title' => 'Submit dan persetujuan Purchase Order',
        'summary' => 'Purchasing mengajukan PO; pengguna dengan purchase_orders.approve memutuskan PO Submitted.',
        'verified_by' => ['app/Services/Purchasing/PurchaseOrderService.php', 'app/Policies/PurchaseOrderPolicy.php'],
        'lanes' => [[
            'label' => 'Persetujuan', 'tone' => 'warning', 'steps' => [
                ['label' => 'PO Draft lengkap', 'detail' => 'Minimal satu item dan total telah diperiksa.'],
                ['label' => 'Submit', 'detail' => 'Status menjadi Submitted dan waktu serta pengaju dicatat.'],
                ['label' => 'Approver memeriksa', 'detail' => 'Hanya pengguna berizin dan berlokasi sesuai.'],
                ['label' => 'Setujui?', 'detail' => 'Backend tidak menyediakan status rejected; bila tidak dilanjutkan gunakan pembatalan resmi.', 'kind' => 'decision'],
                ['label' => 'Status Approved', 'detail' => 'Approval dan histori status dicatat.', 'kind' => 'result'],
            ],
        ]],
    ],

    'purchase-order-send' => [
        'title' => 'Mengirim PO kepada supplier',
        'summary' => 'Dokumen dicetak atau diekspor lalu status ditandai terkirim hanya setelah komunikasi resmi dilakukan.',
        'verified_by' => ['app/Http/Controllers/Purchasing/PurchaseOrderController.php', 'app/Http/Controllers/Purchasing/PurchaseOrderPrintController.php'],
        'lanes' => [[
            'label' => 'Pengiriman PO', 'tone' => 'success', 'steps' => [
                ['label' => 'Pastikan Approved', 'detail' => 'Status lain ditolak oleh policy.'],
                ['label' => 'Cetak atau ekspor', 'detail' => 'Periksa ulang dokumen yang akan dikirim.'],
                ['label' => 'Kirim melalui kanal resmi', 'detail' => 'Catat konfirmasi supplier di catatan operasional.'],
                ['label' => 'Tandai sudah dikirim', 'detail' => 'Status menjadi Sent to Supplier.'],
                ['label' => 'Pantau ETA', 'detail' => 'PO siap menjadi dasar Goods Receipt.', 'kind' => 'result'],
            ],
        ]],
    ],

    'purchase-order-monitoring' => [
        'title' => 'Memantau PO outstanding',
        'summary' => 'Filter status, supplier, dan periode membantu purchasing memprioritaskan PO yang belum selesai.',
        'verified_by' => ['app/Http/Controllers/Purchasing/PurchaseOrderController.php', 'app/Enums/PurchaseOrderStatus.php'],
        'lanes' => [[
            'label' => 'Monitoring', 'tone' => 'primary', 'steps' => [
                ['label' => 'Filter PO aktif', 'detail' => 'Submitted, Approved, Sent, atau Partially Received.'],
                ['label' => 'Bandingkan ETA', 'detail' => 'Prioritaskan kedatangan terdekat dan terlambat.'],
                ['label' => 'Bandingkan ordered dan received', 'detail' => 'Outstanding berasal dari sisa per item.'],
                ['label' => 'Masih akan dikirim?', 'detail' => 'Koordinasikan susulan; PO yang sudah menerima barang tidak dapat dibatalkan.', 'kind' => 'decision'],
                ['label' => 'Catat tindak lanjut', 'detail' => 'Informasikan gudang dan hindari PO pengganti ganda.', 'kind' => 'result'],
            ],
        ]],
    ],

    'purchasing-before-receipt' => [
        'title' => 'Persiapan sebelum barang datang',
        'summary' => 'Purchasing memastikan PO dan jadwal siap, sedangkan gudang menyiapkan proses penerimaan.',
        'verified_by' => ['app/Http/Controllers/Warehouse/GoodsReceiptController.php', 'app/Services/Warehouse/GoodsReceiptService.php'],
        'lanes' => [[
            'label' => 'Koordinasi kedatangan', 'tone' => 'warning', 'steps' => [
                ['label' => 'PO Approved atau Sent', 'detail' => 'PO dengan status ini dapat dipilih untuk receipt.'],
                ['label' => 'Konfirmasi ETA supplier', 'detail' => 'Sampaikan jadwal dan nomor PO ke gudang.'],
                ['label' => 'Siapkan kebutuhan QC', 'detail' => 'Batch atau kedaluwarsa hanya bila produk memerlukannya.'],
                ['label' => 'Barang datang', 'detail' => 'Gudang membuat Goods Receipt draft.', 'kind' => 'result'],
            ],
        ]],
    ],

    'purchasing-receipt-qc' => [
        'title' => 'Pembagian tugas saat penerimaan dan QC',
        'summary' => 'Gudang mencatat kondisi fisik aktual; purchasing memakai hasil tersebut untuk klaim dan rekonsiliasi supplier.',
        'verified_by' => ['app/Services/Warehouse/GoodsReceiptService.php', 'app/Http/Requests/Warehouse/StoreGoodsReceiptRequest.php'],
        'lanes' => [
            ['label' => 'Gudang', 'tone' => 'primary', 'steps' => [
                ['label' => 'Hitung qty datang', 'detail' => 'Tidak boleh melampaui outstanding PO.'],
                ['label' => 'Pisahkan hasil QC', 'detail' => 'Accepted, damaged, dan rejected harus seimbang dengan qty datang.'],
                ['label' => 'Catat lokasi dan bukti', 'detail' => 'Bin, surat jalan, batch, dan catatan sesuai kondisi.'],
                ['label' => 'Posting receipt', 'detail' => 'Hanya pengguna dengan goods_receipts.create.', 'kind' => 'result'],
            ]],
            ['label' => 'Purchasing', 'tone' => 'warning', 'steps' => [
                ['label' => 'Pantau hasil QC', 'detail' => 'Role purchasing memiliki akses lihat receipt.'],
                ['label' => 'Ada selisih?', 'detail' => 'Gunakan data aktual sebagai dasar klaim supplier.', 'kind' => 'decision'],
            ]],
        ],
    ],

    'purchasing-after-receipt' => [
        'title' => 'Tindak lanjut setelah receipt diposting',
        'summary' => 'Receipt posted memperbarui penerimaan PO, stok, HPP, dan skor supplier secara bersamaan.',
        'verified_by' => ['app/Services/Warehouse/GoodsReceiptService.php', 'app/Services/Purchasing/PurchaseOrderService.php'],
        'lanes' => [[
            'label' => 'Rekonsiliasi', 'tone' => 'success', 'steps' => [
                ['label' => 'Bandingkan ordered dan received', 'detail' => 'Periksa receipt saat ini dan total sebelumnya.'],
                ['label' => 'Periksa QC', 'detail' => 'Accepted menambah stok; damaged diblok; rejected tidak masuk stok.'],
                ['label' => 'Periksa status PO', 'detail' => 'Sisa menghasilkan Partially Received; lengkap menjadi Completed.'],
                ['label' => 'Periksa HPP dan skor', 'detail' => 'Biaya masuk dan hasil supplier direkam.'],
                ['label' => 'Tutup atau klaim selisih', 'detail' => 'Tindak lanjut memakai dokumen resmi.', 'kind' => 'result'],
            ],
        ]],
    ],

    'purchasing-correction' => [
        'title' => 'Menangani kekurangan, kerusakan, retur, dan koreksi',
        'summary' => 'Dokumen final dipertahankan; tindak lanjut memakai receipt aktual, pembatalan yang sah, retur, atau dokumen pengganti.',
        'verified_by' => ['app/Services/Warehouse/GoodsReceiptService.php', 'app/Services/Purchasing/PurchaseOrderService.php', 'app/Services/Returns/ReturnService.php'],
        'lanes' => [
            ['label' => 'Selisih jumlah/kondisi', 'tone' => 'danger', 'steps' => [
                ['label' => 'Catat qty dan QC aktual', 'detail' => 'Jangan menyesuaikan hasil fisik agar sama dengan invoice.'],
                ['label' => 'Sisa akan dikirim?', 'detail' => 'Biarkan PO partial jika masih ada pengiriman susulan.', 'kind' => 'decision'],
                ['label' => 'Buat bukti dan klaim', 'detail' => 'Gunakan retur supplier bila barang perlu dikembalikan.'],
            ]],
            ['label' => 'Kesalahan PO', 'tone' => 'warning', 'steps' => [
                ['label' => 'Hentikan proses lanjutan', 'detail' => 'Selama aman dan barang belum diterima.'],
                ['label' => 'Bisa dibatalkan?', 'detail' => 'Hanya Draft, Submitted, atau Approved tanpa qty received.', 'kind' => 'decision'],
                ['label' => 'Buat dokumen koreksi', 'detail' => 'Pertahankan audit dan referensi silang.', 'kind' => 'result'],
            ]],
        ],
    ],

    'purchasing-guardrails' => [
        'title' => 'Pagar pengaman Purchasing',
        'summary' => 'Permission, lokasi, supplier aktif, state machine, nilai decimal, dan histori dokumen menjaga integritas pembelian.',
        'verified_by' => ['routes/web.php', 'config/rbac.php', 'app/Services/Purchasing/PurchaseOrderService.php'],
        'lanes' => [[
            'label' => 'Validasi wajib', 'tone' => 'danger', 'steps' => [
                ['label' => 'Periksa permission dan lokasi', 'detail' => 'Data di luar penugasan ditolak.'],
                ['label' => 'Periksa master aktif', 'detail' => 'Supplier, produk, unit, dan tujuan harus valid.'],
                ['label' => 'Periksa status dokumen', 'detail' => 'Aksi hanya berjalan pada transisi yang tersedia.'],
                ['label' => 'Periksa jumlah dan biaya', 'detail' => 'Qty positif, biaya nonnegatif, dan perhitungan decimal.'],
                ['label' => 'Simpan histori', 'detail' => 'Status, actor, approval, dan aktivitas dapat diaudit.'],
                ['label' => 'Koreksi dengan dokumen', 'detail' => 'Dokumen final tidak dihapus atau diubah langsung.', 'kind' => 'result'],
            ],
        ]],
    ],

    'purchasing-daily' => [
        'title' => 'Siklus harian Purchasing',
        'summary' => 'Pemeriksaan harian memprioritaskan kebutuhan baru, PO tertunda, kedatangan, receipt, dan klaim.',
        'verified_by' => ['app/Http/Controllers/Purchasing/PurchaseRequestController.php', 'app/Http/Controllers/Purchasing/PurchaseOrderController.php', 'app/Http/Controllers/Warehouse/GoodsReceiptController.php'],
        'lanes' => [[
            'label' => 'Setiap hari', 'tone' => 'warning', 'steps' => [
                ['label' => 'Periksa stok dan request', 'detail' => 'Identifikasi kebutuhan baru dan prioritas.'],
                ['label' => 'Periksa antrean PO', 'detail' => 'Submitted, Approved belum dikirim, dan overdue.'],
                ['label' => 'Koordinasikan kedatangan', 'detail' => 'Supplier dan gudang menerima jadwal yang sama.'],
                ['label' => 'Tinjau receipt baru', 'detail' => 'Cocokkan jumlah, QC, status PO, dan HPP.'],
                ['label' => 'Tindak lanjuti pengecualian', 'detail' => 'Shortage, damaged, rejected, dan perubahan ETA.'],
                ['label' => 'Catat komunikasi', 'detail' => 'Keputusan penting tersimpan pada dokumen atau catatan resmi.', 'kind' => 'result'],
            ],
        ]],
    ],

    'purchasing-weekly' => [
        'title' => 'Siklus mingguan Purchasing',
        'summary' => 'Review mingguan mengubah data PO, receipt, HPP, dan skor supplier menjadi tindakan perbaikan.',
        'verified_by' => ['app/Http/Controllers/Reports/SupplierPerformanceController.php', 'app/Http/Controllers/Pricing/HppHistoryController.php', 'app/Http/Controllers/Purchasing/PurchaseOrderController.php'],
        'lanes' => [[
            'label' => 'Setiap minggu', 'tone' => 'success', 'steps' => [
                ['label' => 'Review performa supplier', 'detail' => 'Skor, acceptance rate, ranking, dan tren.'],
                ['label' => 'Review perubahan HPP', 'detail' => 'Cari perubahan biaya terbesar dan receipt sumber.'],
                ['label' => 'Review PO partial lama', 'detail' => 'Putuskan susulan dan eskalasi supplier.'],
                ['label' => 'Review master supplier', 'detail' => 'Perbarui kontak dan status yang sudah tidak valid.'],
                ['label' => 'Rekonsiliasi dokumen', 'detail' => 'Cocokkan PO, receipt, retur, dan tagihan bersama tim terkait.'],
                ['label' => 'Susun tindakan lanjut', 'detail' => 'Pemilik, tenggat, dan bukti keputusan dicatat.', 'kind' => 'result'],
            ],
        ]],
    ],
];
