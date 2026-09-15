<?php

return [
    'warehouse-overview' => [
        'title' => 'Siklus utama manajemen gudang',
        'summary' => 'Dashboard mengarahkan pekerjaan dari kebutuhan pembelian sampai barang diterima, disimpan, dan dikeluarkan dengan dokumen yang dapat ditelusuri.',
        'verified_by' => ['app/Http/Controllers/Warehouse/WarehouseDashboardController.php', 'app/Services/Reports/ReportMetricService.php'],
        'lanes' => [
            ['label' => 'Arus barang', 'tone' => 'primary', 'steps' => [
                ['label' => 'Pantau kebutuhan', 'detail' => 'Dashboard dan saldo stok menunjukkan pekerjaan dan stok kritis.'],
                ['label' => 'Beli atau terima transfer', 'detail' => 'Barang berasal dari PO atau perpindahan yang sah.'],
                ['label' => 'Terima dan periksa', 'detail' => 'QC memisahkan barang diterima, rusak, dan ditolak.'],
                ['label' => 'Simpan per lokasi', 'detail' => 'Saldo dicatat pada lokasi kerja dan bin yang sesuai.'],
                ['label' => 'Keluarkan dengan dokumen', 'detail' => 'Transfer, shipment, retur, atau loss membuat mutasi stok.', 'kind' => 'result'],
            ]],
            ['label' => 'Kontrol', 'tone' => 'success', 'steps' => [
                ['label' => 'Kartu stok', 'detail' => 'Setiap perubahan dapat ditelusuri ke dokumen sumber.'],
                ['label' => 'Opname', 'detail' => 'Saldo sistem dibandingkan dengan hitungan fisik.'],
                ['label' => 'Laporan', 'detail' => 'HPP, supplier, dan loss menjadi bahan keputusan.', 'kind' => 'result'],
            ]],
        ],
    ],

    'warehouse-stock-principles' => [
        'title' => 'Cara sistem menghitung stok siap pakai',
        'summary' => 'Stok siap dipakai selalu dihitung dari saldo fisik setelah dikurangi stok yang dicadangkan dan stok rusak.',
        'verified_by' => ['app/Models/Stock.php', 'app/Services/Inventory/InventoryService.php'],
        'lanes' => [
            ['label' => 'Perhitungan', 'tone' => 'primary', 'steps' => [
                ['label' => 'Stok fisik tercatat', 'detail' => 'quantity_on_hand.'],
                ['label' => 'Kurangi stok dicadangkan', 'detail' => 'quantity_reserved belum keluar secara fisik.'],
                ['label' => 'Kurangi stok rusak', 'detail' => 'quantity_damaged tidak boleh dijual.'],
                ['label' => 'Stok tersedia', 'detail' => 'available = on hand - reserved - damaged.', 'kind' => 'result'],
            ]],
            ['label' => 'Setiap perubahan', 'tone' => 'warning', 'steps' => [
                ['label' => 'Validasi stok dan lokasi', 'detail' => 'Jumlah negatif dan lokasi tidak sah ditolak.', 'kind' => 'decision'],
                ['label' => 'Kunci transaksi', 'detail' => 'Saldo dikunci selama perubahan diproses.'],
                ['label' => 'Tulis mutasi', 'detail' => 'Nilai sebelum, perubahan, sesudah, dan referensi disimpan.', 'kind' => 'result'],
            ]],
        ],
    ],

    'warehouse-location-setup' => [
        'title' => 'Membuat zona, rak, dan bin',
        'summary' => 'Struktur lokasi disimpan di bawah gudang yang berada dalam penugasan pengguna.',
        'verified_by' => ['app/Http/Controllers/Warehouse/WarehouseLocationController.php', 'app/Policies/WarehouseLocationPolicy.php'],
        'lanes' => [[
            'label' => 'Pengaturan lokasi', 'tone' => 'primary', 'steps' => [
                ['label' => 'Pilih gudang', 'detail' => 'Gudang harus aktif dan dapat diakses pengguna.'],
                ['label' => 'Pilih tipe dan induk', 'detail' => 'Zona, rak, atau bin mengikuti struktur fisik.'],
                ['label' => 'Isi kode dan kapasitas', 'detail' => 'Sistem membentuk kode lengkap lokasi.'],
                ['label' => 'Aktif?', 'detail' => 'Lokasi nonaktif tidak tersedia untuk transaksi baru.', 'kind' => 'decision'],
                ['label' => 'Lokasi siap dipakai', 'detail' => 'Receipt, transfer, kartu stok, dan opname dapat merujuk lokasi.', 'kind' => 'result'],
            ],
        ]],
    ],

    'warehouse-location-transfer' => [
        'title' => 'Transfer internal antar lokasi',
        'summary' => 'Perpindahan internal langsung mencatat mutasi keluar dan masuk dalam satu transaksi idempoten.',
        'verified_by' => ['app/Http/Controllers/Warehouse/LocationTransferController.php', 'app/Services/Inventory/InventoryService.php'],
        'lanes' => [[
            'label' => 'Pindah bin/lokasi', 'tone' => 'primary', 'steps' => [
                ['label' => 'Pilih asal dan tujuan', 'detail' => 'Keduanya harus aktif dan berada dalam akses pengguna.'],
                ['label' => 'Pilih produk', 'detail' => 'Produk harus memiliki stok tersedia di sumber.'],
                ['label' => 'Isi jumlah dan alasan', 'detail' => 'Jumlah divalidasi dalam satuan dasar.'],
                ['label' => 'Stok cukup?', 'detail' => 'Jika tidak cukup, seluruh transaksi ditolak.', 'kind' => 'decision'],
                ['label' => 'Mutasi keluar dan masuk', 'detail' => 'Dua mutasi memakai satu kunci operasi agar tidak ganda.', 'kind' => 'result'],
            ],
        ]],
    ],

    'warehouse-stock-balance' => [
        'title' => 'Membaca saldo stok',
        'summary' => 'Daftar saldo hanya menampilkan lokasi yang diizinkan dan menghitung stok tersedia serta nilai persediaan.',
        'verified_by' => ['app/Http/Controllers/Warehouse/StockController.php', 'app/Models/Stock.php'],
        'lanes' => [[
            'label' => 'Pemeriksaan saldo', 'tone' => 'primary', 'steps' => [
                ['label' => 'Pilih filter', 'detail' => 'Produk, lokasi kerja, bin, dan kondisi stok.'],
                ['label' => 'Batasi penugasan', 'detail' => 'Data lokasi lain tidak ikut ditampilkan.'],
                ['label' => 'Hitung tersedia', 'detail' => 'On hand dikurangi reserved dan damaged.'],
                ['label' => 'Tentukan kondisi', 'detail' => 'Kritis atau kosong dibandingkan minimum stok.', 'kind' => 'decision'],
                ['label' => 'Buka kartu stok', 'detail' => 'Telusuri penyebab saldo dari setiap mutasi.', 'kind' => 'result'],
            ],
        ]],
    ],

    'warehouse-stock-card' => [
        'title' => 'Menelusuri kartu stok dan detail mutasi',
        'summary' => 'Kartu stok menyusun mutasi secara kronologis dan detail mutasi menunjukkan dokumen sumber yang tidak boleh diedit.',
        'verified_by' => ['app/Http/Controllers/Warehouse/StockCardController.php', 'app/Http/Controllers/Warehouse/StockMutationController.php'],
        'lanes' => [[
            'label' => 'Penelusuran', 'tone' => 'primary', 'steps' => [
                ['label' => 'Pilih produk dan lokasi', 'detail' => 'Filter tanggal, jenis mutasi, referensi, atau pengguna.'],
                ['label' => 'Baca urutan mutasi', 'detail' => 'Periksa saldo sebelum, perubahan, dan saldo sesudah.'],
                ['label' => 'Buka dokumen sumber', 'detail' => 'Transfer, receipt, opname, retur, loss, atau shipment.'],
                ['label' => 'Saldo tidak cocok?', 'detail' => 'Lakukan investigasi; jangan mengubah mutasi.', 'kind' => 'decision'],
                ['label' => 'Buat koreksi resmi', 'detail' => 'Gunakan opname, adjustment, retur, loss, atau reversal.', 'kind' => 'result'],
            ],
        ]],
    ],

    'warehouse-batch-lot' => [
        'title' => 'Melihat batch atau lot stok',
        'summary' => 'Halaman batch bersifat pemantauan dan hanya berguna untuk item yang memang memiliki data batch dari penerimaan.',
        'verified_by' => ['app/Http/Controllers/Warehouse/StockBatchController.php', 'app/Models/StockBatch.php'],
        'lanes' => [[
            'label' => 'Pemantauan opsional', 'tone' => 'warning', 'steps' => [
                ['label' => 'Receipt memiliki batch?', 'detail' => 'Jika tidak, operasional tetap memakai saldo stok biasa.', 'kind' => 'decision'],
                ['label' => 'Filter produk dan lokasi', 'detail' => 'Data dibatasi ke lokasi kerja pengguna.'],
                ['label' => 'Periksa lot dan tanggal', 'detail' => 'Nomor lot, supplier, tanggal masuk, dan biaya ditampilkan.'],
                ['label' => 'Gunakan untuk penelusuran', 'detail' => 'Batch tidak menggantikan kartu stok.', 'kind' => 'result'],
            ],
        ]],
    ],

    'purchase-request' => [
        'title' => 'Permintaan pembelian',
        'summary' => 'Permintaan manual langsung diajukan untuk disetujui atau ditolak, lalu dapat dikonversi menjadi PO draft.',
        'verified_by' => ['app/Http/Controllers/Purchasing/PurchaseRequestController.php', 'app/Enums/PurchaseRequestStatus.php'],
        'lanes' => [[
            'label' => 'Kebutuhan pembelian', 'tone' => 'primary', 'steps' => [
                ['label' => 'Lihat rekomendasi', 'detail' => 'Stok tersedia dibandingkan dengan minimum produk.'],
                ['label' => 'Isi permintaan', 'detail' => 'Gudang, produk, jumlah, prioritas, dan alasan.'],
                ['label' => 'Status Diajukan', 'detail' => 'Permintaan belum mengubah stok.'],
                ['label' => 'Setujui atau tolak', 'detail' => 'Approver memutuskan kebutuhan.', 'kind' => 'decision'],
                ['label' => 'Konversi ke PO', 'detail' => 'Permintaan disetujui menjadi PO draft untuk supplier terpilih.', 'kind' => 'result'],
            ],
        ]],
    ],

    'purchase-order-create' => [
        'title' => 'Membuat Purchase Order',
        'summary' => 'PO menyimpan snapshot unit, faktor konversi, harga, diskon, pajak, dan biaya sebelum diajukan.',
        'verified_by' => ['app/Services/Purchasing/PurchaseOrderService.php', 'app/Http/Controllers/Purchasing/PurchaseOrderController.php'],
        'lanes' => [[
            'label' => 'Penyusunan PO', 'tone' => 'primary', 'steps' => [
                ['label' => 'Pilih gudang dan supplier', 'detail' => 'Keduanya harus aktif dan dapat diakses.'],
                ['label' => 'Isi jadwal dan termin', 'detail' => 'Tanggal order, ETA, termin, dan catatan.'],
                ['label' => 'Isi item', 'detail' => 'Unit, konversi, jumlah, harga, diskon, dan pajak.'],
                ['label' => 'Hitung total', 'detail' => 'Subtotal serta biaya header dihitung dengan decimal.'],
                ['label' => 'Simpan Draft', 'detail' => 'Belum menambah atau mencadangkan stok.', 'kind' => 'result'],
            ],
        ]],
    ],

    'purchase-order-lifecycle' => [
        'title' => 'Persetujuan dan status Purchase Order',
        'summary' => 'Perubahan status PO dibatasi oleh state machine; penerimaan yang diposting menentukan status sebagian atau selesai.',
        'verified_by' => ['app/Services/Purchasing/PurchaseOrderService.php', 'app/Enums/PurchaseOrderStatus.php'],
        'lanes' => [[
            'label' => 'Status PO', 'tone' => 'success', 'steps' => [
                ['label' => 'Draft', 'detail' => 'Masih dapat diperbarui.'],
                ['label' => 'Diajukan', 'detail' => 'Menunggu persetujuan.'],
                ['label' => 'Disetujui', 'detail' => 'Tidak boleh diedit bebas.'],
                ['label' => 'Dikirim ke supplier', 'detail' => 'PO siap dijadikan dasar receipt.'],
                ['label' => 'Diterima sebagian?', 'detail' => 'Outstanding tersisa menghasilkan partially received.', 'kind' => 'decision'],
                ['label' => 'Selesai', 'detail' => 'Seluruh jumlah telah diperhitungkan.', 'kind' => 'result'],
            ],
        ]],
    ],

    'goods-receipt-create' => [
        'title' => 'Membuat penerimaan barang dari PO',
        'summary' => 'Receipt draft mencatat jumlah datang dan hasil QC tanpa memengaruhi saldo stok.',
        'verified_by' => ['app/Http/Controllers/Warehouse/GoodsReceiptController.php', 'app/Services/Warehouse/GoodsReceiptService.php'],
        'lanes' => [[
            'label' => 'Draft penerimaan', 'tone' => 'primary', 'steps' => [
                ['label' => 'Pilih PO', 'detail' => 'Hanya PO approved, sent, atau diterima sebagian.'],
                ['label' => 'Isi dokumen kedatangan', 'detail' => 'Surat jalan, tanggal, dan bukti.'],
                ['label' => 'Periksa outstanding', 'detail' => 'Jumlah receipt tidak boleh melampaui sisa PO.'],
                ['label' => 'Catat hasil QC', 'detail' => 'Accepted, damaged, rejected, serta batch jika digunakan.'],
                ['label' => 'Pilih lokasi simpan', 'detail' => 'Gudang memakai bin aktif yang sesuai.'],
                ['label' => 'Simpan Draft', 'detail' => 'Stok belum berubah.', 'kind' => 'result'],
            ],
        ]],
    ],

    'goods-receipt-post' => [
        'title' => 'Posting penerimaan barang',
        'summary' => 'Posting dilakukan sekali dan mengikat mutasi stok, pembaruan PO, HPP, serta skor supplier dalam satu transaksi.',
        'verified_by' => ['app/Services/Warehouse/GoodsReceiptService.php', 'app/Services/Inventory/InventoryService.php'],
        'lanes' => [
            ['label' => 'Mutasi stok', 'tone' => 'primary', 'steps' => [
                ['label' => 'Kunci receipt draft', 'detail' => 'Posting ulang mengembalikan hasil yang sama.'],
                ['label' => 'Accepted', 'detail' => 'Menambah stok tersedia.'],
                ['label' => 'Damaged', 'detail' => 'Masuk on hand lalu dipindah ke damaged.'],
                ['label' => 'Rejected', 'detail' => 'Dicatat sebagai hasil QC dan tidak menambah stok tersedia.'],
            ]],
            ['label' => 'Dokumen lanjutan', 'tone' => 'success', 'steps' => [
                ['label' => 'Perbarui qty PO', 'detail' => 'Status menjadi diterima sebagian atau selesai.'],
                ['label' => 'Hitung HPP rata-rata', 'detail' => 'Biaya masuk dan landed cost disimpan sebagai histori.'],
                ['label' => 'Nilai supplier', 'detail' => 'Kualitas dan ketepatan penerimaan menghasilkan skor.', 'kind' => 'result'],
            ]],
        ],
    ],

    'warehouse-restock-review' => [
        'title' => 'Meninjau permintaan restock toko',
        'summary' => 'Gudang memutuskan jumlah yang disetujui sebelum permintaan dikonversi menjadi transfer stok.',
        'verified_by' => ['app/Services/Warehouse/RestockRequestService.php', 'app/Http/Controllers/Retail/RestockRequestController.php'],
        'lanes' => [[
            'label' => 'Keputusan restock', 'tone' => 'primary', 'steps' => [
                ['label' => 'Permintaan toko diajukan', 'detail' => 'Produk, jumlah, prioritas, dan alasan tercatat.'],
                ['label' => 'Bandingkan stok gudang', 'detail' => 'Periksa jumlah tersedia pada sumber.'],
                ['label' => 'Setujui jumlah atau tolak', 'detail' => 'Jumlah disetujui tidak boleh melebihi permintaan.', 'kind' => 'decision'],
                ['label' => 'Konversi ke transfer', 'detail' => 'Item dan lokasi tujuan dibawa ke dokumen transfer.', 'kind' => 'result'],
            ],
        ]],
    ],

    'stock-transfer-create' => [
        'title' => 'Membuat transfer stok ke toko',
        'summary' => 'Transfer draft belum memengaruhi stok; stok sumber baru dicadangkan saat transfer disetujui.',
        'verified_by' => ['app/Services/Warehouse/StockTransferService.php', 'app/Http/Controllers/Warehouse/StockTransferController.php'],
        'lanes' => [[
            'label' => 'Persiapan transfer', 'tone' => 'primary', 'steps' => [
                ['label' => 'Pilih sumber dan tujuan', 'detail' => 'Lokasi berbeda dan berada dalam akses pengguna.'],
                ['label' => 'Isi item dan jumlah', 'detail' => 'Dapat berasal dari restock yang disetujui.'],
                ['label' => 'Submit transfer', 'detail' => 'Status menunggu persetujuan.'],
                ['label' => 'Stok tersedia cukup?', 'detail' => 'Persetujuan gagal jika stok sumber tidak cukup.', 'kind' => 'decision'],
                ['label' => 'Reserve stok sumber', 'detail' => 'Available berkurang, on hand belum berubah.', 'kind' => 'result'],
            ],
        ]],
    ],

    'stock-transfer-packing' => [
        'title' => 'Picking dan packing transfer',
        'summary' => 'Jumlah yang dipilih dicatat per item; kekurangan fisik menjadi short pick yang terlihat pada dokumen.',
        'verified_by' => ['app/Services/Warehouse/StockTransferService.php', 'app/Http/Controllers/Warehouse/StockTransferController.php'],
        'lanes' => [[
            'label' => 'Picking', 'tone' => 'warning', 'steps' => [
                ['label' => 'Buka transfer approved', 'detail' => 'Hanya transfer yang sudah memiliki reservasi.'],
                ['label' => 'Cek produk dan bin', 'detail' => 'Petugas mengambil dari sumber yang ditentukan.'],
                ['label' => 'Isi qty picked', 'detail' => 'Jumlah aktual dibandingkan jumlah rencana.'],
                ['label' => 'Ada kekurangan?', 'detail' => 'Catat short pick dan alasannya.', 'kind' => 'decision'],
                ['label' => 'Simpan paket', 'detail' => 'Nomor paket, checker, dan bukti dapat dicatat.', 'kind' => 'result'],
            ],
        ]],
    ],

    'stock-transfer-shipping' => [
        'title' => 'Mengirim transfer stok',
        'summary' => 'Saat dikirim, reservasi sumber dilepas lalu stok fisik sumber dikeluarkan sesuai jumlah kirim.',
        'verified_by' => ['app/Services/Warehouse/StockTransferService.php', 'app/Services/Inventory/InventoryService.php'],
        'lanes' => [[
            'label' => 'Pengiriman', 'tone' => 'primary', 'steps' => [
                ['label' => 'Transfer selesai dipacking', 'detail' => 'Jumlah kirim berasal dari hasil picking.'],
                ['label' => 'Isi data pengiriman', 'detail' => 'Kurir, kendaraan/resi, biaya, tanggal, dan bukti.'],
                ['label' => 'Lepas reservasi', 'detail' => 'Reserved sumber dikurangi.'],
                ['label' => 'Keluarkan stok sumber', 'detail' => 'Mutasi transfer out mengurangi on hand.'],
                ['label' => 'Status Dikirim', 'detail' => 'Barang menunggu penerimaan toko.', 'kind' => 'result'],
            ],
        ]],
    ],

    'warehouse-b2b-review' => [
        'title' => 'Meninjau order B2B di gudang',
        'summary' => 'Review menggabungkan status pelanggan, limit kredit, item order, stok tersedia, reservasi, invoice, dan shipment.',
        'verified_by' => ['app/Http/Controllers/Warehouse/B2bOrderController.php', 'app/Services/B2B/B2bOrderWorkflowService.php'],
        'lanes' => [[
            'label' => 'Validasi order', 'tone' => 'primary', 'steps' => [
                ['label' => 'Buka order pending', 'detail' => 'Data hanya untuk lokasi gudang yang diizinkan.'],
                ['label' => 'Periksa pelanggan', 'detail' => 'Akun dan verifikasi pelanggan harus aktif.'],
                ['label' => 'Periksa kredit dan harga', 'detail' => 'Limit, termin, total, dan kebutuhan pembayaran.'],
                ['label' => 'Periksa stok', 'detail' => 'Available dibandingkan jumlah tiap item.'],
                ['label' => 'Layak diproses?', 'detail' => 'Reserve atau tolak dengan alasan.', 'kind' => 'decision'],
            ],
        ]],
    ],

    'warehouse-b2b-reserve' => [
        'title' => 'Mencadangkan stok untuk order B2B',
        'summary' => 'Reservasi mengurangi stok tersedia tanpa mengurangi saldo fisik dan memiliki waktu kedaluwarsa.',
        'verified_by' => ['app/Services/B2B/B2bOrderWorkflowService.php', 'app/Http/Controllers/Warehouse/StockReservationController.php'],
        'lanes' => [[
            'label' => 'Reservasi', 'tone' => 'warning', 'steps' => [
                ['label' => 'Order validasi gudang', 'detail' => 'Status order harus mengizinkan reserve.'],
                ['label' => 'Kunci stok per lokasi', 'detail' => 'Sistem mengalokasikan stok tersedia secara deterministik.'],
                ['label' => 'Cukup?', 'detail' => 'Partial hanya berjalan bila opsi partial diizinkan.', 'kind' => 'decision'],
                ['label' => 'Tambah reserved', 'detail' => 'On hand tetap, available berkurang.'],
                ['label' => 'Kirim atau lepaskan', 'detail' => 'Issue mengonversi reservasi; batal/kedaluwarsa mengembalikannya.', 'kind' => 'result'],
            ],
        ]],
    ],

    'warehouse-b2b-fulfillment' => [
        'title' => 'Packing, shipment, dan pengeluaran stok B2B',
        'summary' => 'Shipment dibuat dari order yang siap dipacking; posting shipment mengeluarkan stok dari reservasi dan dapat dilakukan sebagian.',
        'verified_by' => ['app/Services/B2B/B2bFulfillmentService.php', 'app/Services/B2B/B2bOrderWorkflowService.php'],
        'lanes' => [[
            'label' => 'Fulfillment', 'tone' => 'success', 'steps' => [
                ['label' => 'Order sudah reserved/kredit siap', 'detail' => 'Order menunggu pembayaran belum boleh dikirim.'],
                ['label' => 'Masuk packing', 'detail' => 'Shipment menyimpan item dan jumlah rencana.'],
                ['label' => 'Posting shipment', 'detail' => 'Reserved dilepas lalu stock issue dibuat per item.'],
                ['label' => 'Masih ada reservasi?', 'detail' => 'Order tetap packing jika pengiriman baru sebagian.', 'kind' => 'decision'],
                ['label' => 'Shipped', 'detail' => 'Bukti terima mengubah shipment menjadi delivered dan order menjadi received.', 'kind' => 'result'],
            ],
        ]],
    ],

    'stock-opname-create' => [
        'title' => 'Membuat dan memulai stok opname',
        'summary' => 'Saat opname dimulai, sistem menyimpan snapshot saldo untuk lingkup lokasi dan kategori yang dipilih.',
        'verified_by' => ['app/Services/Warehouse/StockOpnameService.php', 'app/Http/Controllers/Warehouse/StockOpnameController.php'],
        'lanes' => [[
            'label' => 'Persiapan opname', 'tone' => 'primary', 'steps' => [
                ['label' => 'Pilih lingkup', 'detail' => 'Lokasi kerja, bin, kategori, PIC, dan jadwal.'],
                ['label' => 'Atur metode', 'detail' => 'Manual/import, blind count, freeze, dan ambang.'],
                ['label' => 'Simpan Draft', 'detail' => 'Belum ada snapshot hitungan.'],
                ['label' => 'Start opname', 'detail' => 'Stok dalam lingkup dikunci dan disalin sebagai acuan.'],
                ['label' => 'Status Counting', 'detail' => 'Daftar item siap dihitung.', 'kind' => 'result'],
            ],
        ]],
    ],

    'stock-opname-count' => [
        'title' => 'Mengisi hitungan fisik',
        'summary' => 'Setiap item menyimpan jumlah fisik, selisih dari snapshot, alasan, petugas, dan peringatan bila ada mutasi setelah opname dimulai.',
        'verified_by' => ['app/Services/Warehouse/StockOpnameService.php', 'app/Http/Controllers/Warehouse/StockOpnameController.php'],
        'lanes' => [[
            'label' => 'Counting', 'tone' => 'warning', 'steps' => [
                ['label' => 'Buka item count', 'detail' => 'Item hanya dapat diubah saat status Counting.'],
                ['label' => 'Kunci item sementara', 'detail' => 'Mencegah dua petugas mengisi item yang sama bersamaan.'],
                ['label' => 'Isi qty fisik dan alasan', 'detail' => 'Selisih dan nilai estimasi dihitung.'],
                ['label' => 'Ada mutasi setelah start?', 'detail' => 'Sistem memberi peringatan transaksi berjalan.', 'kind' => 'decision'],
                ['label' => 'Semua item terhitung', 'detail' => 'Baru setelah itu opname dapat diajukan.', 'kind' => 'result'],
            ],
        ]],
    ],

    'stock-opname-approval' => [
        'title' => 'Persetujuan dan penyelesaian opname',
        'summary' => 'Semua hasil counting menunggu persetujuan; ambang menentukan tingkat approver sebelum adjustment dibuat.',
        'verified_by' => ['app/Services/Warehouse/StockOpnameService.php', 'app/Enums/StockOpnameStatus.php'],
        'lanes' => [[
            'label' => 'Keputusan selisih', 'tone' => 'danger', 'steps' => [
                ['label' => 'Submit counting', 'detail' => 'Semua item wajib memiliki jumlah fisik.'],
                ['label' => 'Status Menunggu Approval', 'detail' => 'Belum ada adjustment stok.'],
                ['label' => 'Melebihi ambang?', 'detail' => 'Qty atau nilai besar mewajibkan owner/super admin.', 'kind' => 'decision'],
                ['label' => 'Approve atau reject', 'detail' => 'Selisih normal dapat disetujui kepala gudang.'],
                ['label' => 'Complete opname', 'detail' => 'Hanya status approved yang membuat mutasi adjustment.', 'kind' => 'result'],
            ],
        ]],
    ],

    'warehouse-return' => [
        'title' => 'Retur, QC, persetujuan, dan penyelesaian',
        'summary' => 'QC membagi barang baik, rusak, dan ditolak; nilai kerugian menentukan kebutuhan persetujuan sebelum settlement.',
        'verified_by' => ['app/Services/Returns/ReturnService.php', 'app/Http/Controllers/Returns/ReturnController.php'],
        'lanes' => [[
            'label' => 'Alur retur', 'tone' => 'primary', 'steps' => [
                ['label' => 'Buat retur dari dokumen sumber', 'detail' => 'Jumlah tidak boleh melebihi jumlah yang masih dapat diretur.'],
                ['label' => 'Status Submitted', 'detail' => 'Menunggu pemeriksaan fisik.'],
                ['label' => 'QC barang', 'detail' => 'Barang baik masuk stok; barang rusak masuk lalu ditandai damaged.'],
                ['label' => 'Kerugian melewati ambang?', 'detail' => 'Jika ya, status menunggu approval.', 'kind' => 'decision'],
                ['label' => 'Settlement', 'detail' => 'Selesaikan refund, penggantian, kredit, atau retur supplier.'],
                ['label' => 'Status Settled', 'detail' => 'Dokumen dan mutasi dapat ditelusuri.', 'kind' => 'result'],
            ],
        ]],
    ],

    'inventory-loss' => [
        'title' => 'Mencatat barang rusak atau hilang',
        'summary' => 'Nilai loss dihitung dari jumlah dan HPP produk; mutasi terjadi langsung untuk nilai kecil atau setelah persetujuan untuk nilai besar.',
        'verified_by' => ['app/Services/Returns/ReturnService.php', 'app/Http/Controllers/Returns/InventoryLossController.php'],
        'lanes' => [[
            'label' => 'Pencatatan loss', 'tone' => 'danger', 'steps' => [
                ['label' => 'Pilih produk dan lokasi', 'detail' => 'Bin harus sesuai dengan lokasi kerja.'],
                ['label' => 'Isi jumlah, jenis, dan bukti', 'detail' => 'Nilai memakai snapshot HPP dari master produk.'],
                ['label' => 'Nilai melewati ambang?', 'detail' => 'Loss besar menunggu persetujuan.', 'kind' => 'decision'],
                ['label' => 'Periksa stok tersedia', 'detail' => 'Mutasi ditolak jika jumlah tidak cukup.'],
                ['label' => 'Issue atau damage', 'detail' => 'Disposisi menentukan stok dikeluarkan atau dipindah ke damaged.', 'kind' => 'result'],
            ],
        ]],
    ],

    'hpp-history' => [
        'title' => 'Membaca histori HPP',
        'summary' => 'Setiap penerimaan yang memengaruhi biaya menyimpan jumlah, biaya masuk, landed cost, dan HPP sebelum serta sesudah.',
        'verified_by' => ['app/Http/Controllers/Pricing/HppHistoryController.php', 'app/Models/ProductCostHistory.php'],
        'lanes' => [[
            'label' => 'Perubahan biaya', 'tone' => 'primary', 'steps' => [
                ['label' => 'Stok awal atau receipt posted', 'detail' => 'Menjadi sumber histori biaya.'],
                ['label' => 'Ambil qty dan HPP sebelum', 'detail' => 'Saldo sebelum penerimaan menjadi dasar.'],
                ['label' => 'Tambahkan incoming dan landed cost', 'detail' => 'Perhitungan memakai decimal.'],
                ['label' => 'Hitung moving weighted average', 'detail' => 'Menghasilkan HPP baru produk.'],
                ['label' => 'Simpan histori', 'detail' => 'Transaksi lama tetap memakai snapshot biaya asal.', 'kind' => 'result'],
            ],
        ]],
    ],

    'supplier-performance' => [
        'title' => 'Menilai performa supplier',
        'summary' => 'Skor dibentuk dari penerimaan barang dan dapat difilter menurut periode, supplier, serta produk.',
        'verified_by' => ['app/Http/Controllers/Reports/SupplierPerformanceController.php', 'app/Models/SupplierScore.php'],
        'lanes' => [[
            'label' => 'Evaluasi supplier', 'tone' => 'success', 'steps' => [
                ['label' => 'Goods receipt diposting', 'detail' => 'Jumlah datang dan diterima menjadi data penilaian.'],
                ['label' => 'Hitung skor', 'detail' => 'Kualitas, pengiriman, harga, dan total disimpan.'],
                ['label' => 'Filter laporan', 'detail' => 'Pilih periode, supplier, atau produk.'],
                ['label' => 'Bandingkan ranking dan tren', 'detail' => 'Supplier dengan skor di bawah 80 ditandai untuk ditinjau.', 'kind' => 'decision'],
                ['label' => 'Tindak lanjuti pembelian', 'detail' => 'Gunakan bukti skor saat memilih dan mengevaluasi supplier.', 'kind' => 'result'],
            ],
        ]],
    ],

    'warehouse-guardrails' => [
        'title' => 'Pagar pengaman operasional gudang',
        'summary' => 'Route, policy, lokasi kerja, status dokumen, jumlah, transaksi database, dan idempotensi melindungi setiap perubahan stok.',
        'verified_by' => ['routes/web.php', 'app/Services/Inventory/InventoryService.php', 'config/rbac.php'],
        'lanes' => [[
            'label' => 'Sebelum menyimpan', 'tone' => 'danger', 'steps' => [
                ['label' => 'Periksa permission', 'detail' => 'Aksi harus sesuai role.'],
                ['label' => 'Periksa lokasi kerja', 'detail' => 'Dokumen di luar penugasan ditolak.'],
                ['label' => 'Periksa status dokumen', 'detail' => 'Hanya transisi yang diizinkan dapat dijalankan.'],
                ['label' => 'Periksa qty dan stok', 'detail' => 'Over-receive dan saldo negatif ditolak.'],
                ['label' => 'Proses dalam transaksi', 'detail' => 'Kegagalan membatalkan seluruh perubahan.'],
                ['label' => 'Simpan audit dan mutasi', 'detail' => 'Dokumen final tidak diedit atau dihapus.', 'kind' => 'result'],
            ],
        ]],
    ],

    'warehouse-daily' => [
        'title' => 'Siklus harian tim gudang',
        'summary' => 'Prioritas harian bergerak dari pengecualian dan barang masuk ke kebutuhan keluar, lalu rekonsiliasi stok.',
        'verified_by' => ['app/Http/Controllers/Warehouse/WarehouseDashboardController.php', 'app/Services/Warehouse/GoodsReceiptService.php', 'app/Services/Warehouse/StockTransferService.php'],
        'lanes' => [[
            'label' => 'Satu hari operasional', 'tone' => 'primary', 'steps' => [
                ['label' => 'Periksa dashboard', 'detail' => 'Identifikasi stok kritis dan dokumen tertunda.'],
                ['label' => 'Selesaikan receipt fisik', 'detail' => 'QC dan posting barang yang telah datang.'],
                ['label' => 'Proses kebutuhan keluar', 'detail' => 'Restock toko, transfer, dan order B2B.'],
                ['label' => 'Tinjau reservasi', 'detail' => 'Lepaskan reservasi batal atau kedaluwarsa.'],
                ['label' => 'Catat pengecualian', 'detail' => 'Loss, retur, short pick, dan discrepancy.'],
                ['label' => 'Rekonsiliasi mutasi', 'detail' => 'Periksa mutasi besar dan tindak lanjut sebelum tutup hari.', 'kind' => 'result'],
            ],
        ]],
    ],
];
