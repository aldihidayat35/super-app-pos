<?php

$storeFlows = [
    'store-catalog' => [
        'title' => 'Etalase produk dan lokasi pajang',
        'summary' => 'Produk, harga, dan stok selalu dibaca dari toko yang termasuk penugasan pengguna.',
        'verified_by' => ['app/Http/Controllers/Retail/StorefrontController.php', 'app/Services/Retail/EmergencyStockService.php'],
        'lanes' => [
            ['label' => 'Lihat etalase', 'tone' => 'primary', 'steps' => [
                ['label' => 'Pilih toko penugasan', 'detail' => 'Lokasi di luar penugasan ditolak.'],
                ['label' => 'Ambil produk aktif', 'detail' => 'Pencarian, kategori, dan area pajang diterapkan.'],
                ['label' => 'Hitung stok siap jual', 'detail' => 'Stok reguler ditambah pool darurat toko.'],
                ['label' => 'Tentukan harga POS', 'detail' => 'Harga diselesaikan untuk cabang dan kanal POS.'],
                ['label' => 'Tampilkan kartu produk', 'detail' => 'Harga, stok, area, rak, dan tingkat rak.'],
            ]],
            ['label' => 'Atur pajangan', 'tone' => 'success', 'steps' => [
                ['label' => 'Izin kelola etalase', 'detail' => 'Hanya pengguna dengan retail.catalog.manage.', 'kind' => 'decision'],
                ['label' => 'Produk aktif dan tersedia', 'detail' => 'Harus memiliki stok reguler atau darurat.'],
                ['label' => 'Isi lokasi pajang', 'detail' => 'Area wajib; rak dan tingkat rak opsional.'],
                ['label' => 'Simpan penempatan', 'detail' => 'Penempatan produk diperbarui untuk toko tersebut.', 'kind' => 'result'],
            ]],
        ],
    ],

    'attendance-check' => [
        'title' => 'Absen masuk dan pulang',
        'summary' => 'Karyawan cukup login dan menekan satu tombol; lokasi, jadwal, dan waktu Asia/Jakarta ditentukan sistem.',
        'verified_by' => ['app/Services/Attendance/AttendanceService.php', 'app/Http/Controllers/Attendance/CheckController.php', 'app/Http/Controllers/WorkChecklist/WorkChecklistController.php'],
        'lanes' => [
            ['label' => 'Mulai kerja', 'tone' => 'primary', 'steps' => [
                ['label' => 'Login dan buka Kehadiran', 'detail' => 'Sistem mencari karyawan aktif, penugasan lokasi, dan jadwal.'],
                ['label' => 'Klik Absen Masuk', 'detail' => 'Pengguna tidak memilih lokasi, metode, atau shift.'],
                ['label' => 'Catat waktu server', 'detail' => 'Jam masuk disimpan dan status hadir atau terlambat dihitung.'],
                ['label' => 'Operasional aktif', 'detail' => 'Kasir dapat membuka shift POS tanpa menunggu verifikasi.', 'kind' => 'result'],
            ]],
            ['label' => 'Selesai kerja', 'tone' => 'success', 'steps' => [
                ['label' => 'Selesaikan pekerjaan', 'detail' => 'Kasir harus menutup shift kas yang masih aktif.'],
                ['label' => 'Checklist harian selesai?', 'detail' => 'Semua poin harus sudah direspons.', 'kind' => 'decision'],
                ['label' => 'Klik Absen Pulang', 'detail' => 'Dapat digabung dengan penyelesaian checklist dalam satu transaksi.'],
                ['label' => 'Menunggu verifikasi', 'detail' => 'Jam pulang, durasi, dan deviasi tersimpan tanpa berubah.', 'kind' => 'result'],
            ]],
        ],
    ],

    'attendance-verification' => [
        'title' => 'Verifikasi absensi oleh kepala lokasi',
        'summary' => 'Verifikasi dilakukan sekali setelah jam pulang dan tidak mengubah waktu asli ketika staf menekan tombol.',
        'verified_by' => ['app/Services/Attendance/AttendanceService.php', 'app/Http/Controllers/Attendance/AttendanceRecordController.php'],
        'lanes' => [
            ['label' => 'Pemeriksaan', 'tone' => 'warning', 'steps' => [
                ['label' => 'Jam pulang tercatat', 'detail' => 'Status verifikasi menjadi Menunggu Verifikasi.'],
                ['label' => 'Periksa lokasi dan role', 'detail' => 'Kepala toko hanya memeriksa tim toko; kepala gudang hanya tim gudang.', 'kind' => 'decision'],
                ['label' => 'Periksa jam dan checklist', 'detail' => 'Durasi, keterlambatan, dan hasil checklist ditampilkan.'],
                ['label' => 'Setujui', 'detail' => 'Record ditandai Disetujui tanpa mengubah jam masuk atau pulang.', 'kind' => 'result'],
                ['label' => 'Tolak dengan alasan', 'detail' => 'Staf mengajukan koreksi lalu hasilnya kembali menunggu verifikasi.', 'kind' => 'result'],
            ]],
            ['label' => 'Gangguan operasional', 'tone' => 'danger', 'steps' => [
                ['label' => 'Staf belum dapat pulang', 'detail' => 'Checklist atau sistem mengalami gangguan.'],
                ['label' => 'Kepala lokasi isi alasan', 'detail' => 'Alasan pulang darurat wajib diisi.'],
                ['label' => 'Catat pulang darurat', 'detail' => 'Waktu server, pelaku, dan alasan masuk audit serta laporan.', 'kind' => 'result'],
            ]],
        ],
    ],

    'attendance-schedule-pattern' => [
        'title' => 'Pola jadwal mingguan',
        'summary' => 'Kepala lokasi menetapkan shift atau libur Senin sampai Minggu untuk membentuk jadwal 28 hari.',
        'verified_by' => ['app/Services/Attendance/AttendanceService.php', 'app/Console/Commands/GenerateAttendanceSchedulesCommand.php', 'routes/console.php'],
        'lanes' => [
            ['label' => 'Penyusunan', 'tone' => 'primary', 'steps' => [
                ['label' => 'Pilih karyawan dan lokasi', 'detail' => 'Keduanya harus termasuk penugasan kepala lokasi.'],
                ['label' => 'Atur Senin sampai Minggu', 'detail' => 'Setiap hari dipilih shift atau Libur.'],
                ['label' => 'Mulai Senin berikutnya', 'detail' => 'Pola lama berakhir sehari sebelum pola baru.'],
                ['label' => 'Bentuk 28 hari', 'detail' => 'Scheduler membuat jadwal berulang secara idempoten.', 'kind' => 'result'],
                ['label' => 'Ada pengecualian tanggal?', 'detail' => 'Jadwal manual pada tanggal tertentu tidak ditimpa generator.', 'kind' => 'decision'],
            ]],
        ],
    ],

    'cash-shift-open' => [
        'title' => 'Membuka shift kasir',
        'summary' => 'Shift POS hanya dibuka pada cabang yang diizinkan dan setelah kehadiran aktif tersedia.',
        'verified_by' => ['app/Services/Retail/CashShiftService.php', 'app/Services/Attendance/AttendanceService.php'],
        'lanes' => [
            ['label' => 'Alur normal', 'tone' => 'primary', 'steps' => [
                ['label' => 'Pilih cabang', 'detail' => 'Cabang harus aktif dan termasuk penugasan.'],
                ['label' => 'Periksa kehadiran', 'detail' => 'Jadwal aktif, sudah check-in, belum check-out.', 'kind' => 'decision'],
                ['label' => 'Periksa shift aktif', 'detail' => 'Kasir tidak boleh memiliki shift terbuka lain di cabang.'],
                ['label' => 'Isi modal dan terminal', 'detail' => 'Modal menjadi kas harapan awal.'],
                ['label' => 'Shift terbuka', 'detail' => 'POS dapat digunakan oleh kasir.', 'kind' => 'result'],
            ]],
            ['label' => 'Pengecualian kehadiran', 'tone' => 'warning', 'steps' => [
                ['label' => 'Kehadiran tidak siap', 'detail' => 'Jadwal atau check-in tidak ditemukan.', 'kind' => 'decision'],
                ['label' => 'Periksa izin override', 'detail' => 'Memerlukan izin approval shift atau kehadiran.'],
                ['label' => 'Wajib isi alasan', 'detail' => 'Pemberi override dan alasan disimpan.'],
                ['label' => 'Lanjut buka shift', 'detail' => 'Jika syarat override terpenuhi.', 'kind' => 'result'],
            ]],
        ],
    ],

    'pos-checkout' => [
        'title' => 'Penjualan dan checkout POS',
        'summary' => 'Harga, pajak, stok, pembayaran, margin, dan piutang dipastikan dalam satu transaksi database.',
        'verified_by' => ['app/Services/Retail/PosService.php', 'app/Services/Retail/PosCatalogService.php'],
        'lanes' => [
            ['label' => 'Siapkan transaksi', 'tone' => 'primary', 'steps' => [
                ['label' => 'Shift aktif', 'detail' => 'Kasir dan cabang dikunci untuk transaksi.'],
                ['label' => 'Cari atau scan produk', 'detail' => 'Hanya produk aktif dan unit jual valid.'],
                ['label' => 'Hitung harga dan pajak', 'detail' => 'Aturan harga, diskon, minimum harga, dan pajak dihitung ulang.'],
                ['label' => 'Periksa kebutuhan approval', 'detail' => 'Harga yang masih perlu approval menolak checkout.', 'kind' => 'decision'],
            ]],
            ['label' => 'Alokasi stok', 'tone' => 'warning', 'steps' => [
                ['label' => 'Stok reguler', 'detail' => 'Dipakai terlebih dahulu.'],
                ['label' => 'Darurat terikat', 'detail' => 'Permintaan pelanggan aktif dipakai berikutnya.'],
                ['label' => 'Pool darurat', 'detail' => 'Lot bersama dipakai FIFO dalam toko yang sama.'],
                ['label' => 'Cukup?', 'detail' => 'Kekurangan setelah semua sumber menolak checkout.', 'kind' => 'decision'],
            ]],
            ['label' => 'Selesaikan', 'tone' => 'success', 'steps' => [
                ['label' => 'Validasi pembayaran', 'detail' => 'Minimal satu metode; kredit wajib pelanggan dan limit cukup.'],
                ['label' => 'Simpan penjualan', 'detail' => 'Item, HPP, biaya aktual, pajak, margin, dan alokasi disnapshot.'],
                ['label' => 'Mutasi stok', 'detail' => 'Hanya alokasi reguler mengurangi tabel stok reguler.'],
                ['label' => 'Perbarui kas atau piutang', 'detail' => 'Shift dan piutang diperbarui sesuai metode pembayaran.'],
                ['label' => 'Transaksi selesai', 'detail' => 'Seluruh perubahan berhasil bersama.', 'kind' => 'result'],
            ]],
        ],
    ],

    'emergency-purchase' => [
        'title' => 'Pembelian dan restok darurat toko',
        'summary' => 'Permintaan pelanggan dan restok proaktif memakai approval, lot biaya, serta tujuan saldo yang berbeda.',
        'verified_by' => ['app/Services/Retail/EmergencyPurchaseService.php', 'app/Services/Retail/EmergencyStockService.php', 'app/Services/Retail/PosService.php'],
        'lanes' => [
            ['label' => 'Kebutuhan pelanggan', 'tone' => 'warning', 'steps' => [
                ['label' => 'Pilih pelanggan dan barang', 'detail' => 'Qty serta estimasi biaya diisi.'],
                ['label' => 'Hitung kekurangan nyata', 'detail' => 'Permintaan dikurangi stok reguler dan pool darurat.', 'kind' => 'decision'],
                ['label' => 'Catat stockout', 'detail' => 'Hanya dibuat jika masih ada kekurangan.'],
                ['label' => 'Periksa aturan approval', 'detail' => 'Tanpa aturan langsung disetujui; dengan aturan dapat menunggu persetujuan.'],
            ]],
            ['label' => 'Restok proaktif', 'tone' => 'primary', 'steps' => [
                ['label' => 'Pilih restok toko', 'detail' => 'Tidak memakai pelanggan.'],
                ['label' => 'Isi batas pembelian', 'detail' => 'Boleh dibuat walaupun stok reguler masih ada.'],
                ['label' => 'Periksa aturan approval', 'detail' => 'Tidak membuat kejadian stockout.', 'kind' => 'decision'],
                ['label' => 'Siap dibeli', 'detail' => 'Status approved atau pending approval.', 'kind' => 'result'],
            ]],
            ['label' => 'Catat pembelian', 'tone' => 'success', 'steps' => [
                ['label' => 'Status disetujui', 'detail' => 'Hanya permintaan approved yang dapat dibeli.'],
                ['label' => 'Qty dan biaya aktual', 'detail' => 'Tidak boleh melebihi batas yang disetujui.'],
                ['label' => 'Unggah nota', 'detail' => 'Pemasok dan bukti wajib dicatat.'],
                ['label' => 'Pilih sumber dana', 'detail' => 'Kas toko memerlukan shift aktif; pribadi menjadi reimbursement pending.', 'kind' => 'decision'],
                ['label' => 'Bentuk lot darurat', 'detail' => 'Permintaan pelanggan terikat; restok proaktif masuk pool.', 'kind' => 'result'],
            ]],
            ['label' => 'Pemakaian dan sisa', 'tone' => 'info', 'steps' => [
                ['label' => 'Checkout POS', 'detail' => 'Reguler lalu lot terikat lalu pool FIFO.'],
                ['label' => 'Sisa menjadi pool', 'detail' => 'Saldo yang tidak terjual dilepas sebagai unallocated.'],
                ['label' => 'Kelola saldo bebas', 'detail' => 'Konversi reguler, retur pemasok, alokasi ulang, atau teruskan gudang.', 'kind' => 'decision'],
                ['label' => 'Riwayat dan biaya tetap', 'detail' => 'Setiap disposisi mengurangi saldo lot dan tercatat.', 'kind' => 'result'],
            ]],
        ],
    ],

    'pos-hold' => [
        'title' => 'Menahan dan melanjutkan transaksi',
        'summary' => 'Hold menyimpan snapshot keranjang tanpa mengurangi stok dan tetap terikat pada kasir serta shift asal.',
        'verified_by' => ['app/Services/Retail/PosService.php', 'app/Http/Controllers/Retail/PosController.php'],
        'lanes' => [
            ['label' => 'Tahan', 'tone' => 'primary', 'steps' => [
                ['label' => 'Shift aktif', 'detail' => 'Cabang dan kasir divalidasi.'],
                ['label' => 'Validasi keranjang', 'detail' => 'Produk, unit, harga, pelanggan, dan total dihitung.'],
                ['label' => 'Simpan snapshot', 'detail' => 'Status held; stok belum berubah.', 'kind' => 'result'],
            ]],
            ['label' => 'Lanjutkan', 'tone' => 'success', 'steps' => [
                ['label' => 'Kasir yang sama', 'detail' => 'Pengguna lain tidak dapat mengambil hold.', 'kind' => 'decision'],
                ['label' => 'Shift asal masih terbuka', 'detail' => 'Hold lintas shift tidak dapat dilanjutkan.'],
                ['label' => 'Hitung ulang keranjang', 'detail' => 'Data produk, pelanggan, harga, dan stok diperiksa kembali.'],
                ['label' => 'Status resumed', 'detail' => 'Keranjang kembali ke POS untuk checkout.', 'kind' => 'result'],
            ]],
            ['label' => 'Batalkan', 'tone' => 'danger', 'steps' => [
                ['label' => 'Hold masih aktif', 'detail' => 'Hanya kasir pemilik dapat membatalkan.'],
                ['label' => 'Isi alasan', 'detail' => 'Alasan pembatalan wajib.'],
                ['label' => 'Status cancelled', 'detail' => 'Tidak ada mutasi stok.', 'kind' => 'result'],
            ]],
        ],
    ],

    'pos-return' => [
        'title' => 'Retur penjualan POS',
        'summary' => 'Retur menjaga transaksi asal, mengembalikan sumber stok yang benar, dan membalik margin berdasarkan alokasi asli.',
        'verified_by' => ['app/Services/Retail/PosService.php', 'app/Http/Controllers/Retail/PosSaleController.php'],
        'lanes' => [
            ['label' => 'Validasi', 'tone' => 'primary', 'steps' => [
                ['label' => 'Pilih penjualan', 'detail' => 'Status completed atau returned dan shift belum dikunci.'],
                ['label' => 'Pilih item dan kondisi', 'detail' => 'Qty tidak boleh melebihi sisa yang dapat diretur.'],
                ['label' => 'Pisahkan sumber', 'detail' => 'Retur campuran wajib mengisi qty reguler dan darurat.', 'kind' => 'decision'],
            ]],
            ['label' => 'Pemulihan stok', 'tone' => 'warning', 'steps' => [
                ['label' => 'Bagian reguler', 'detail' => 'Masuk kembali ke stok toko.'],
                ['label' => 'Kondisi rusak?', 'detail' => 'Jika rusak, qty reguler langsung ditandai rusak.', 'kind' => 'decision'],
                ['label' => 'Bagian darurat', 'detail' => 'Alokasi dilepas kembali ke lot darurat asal.'],
            ]],
            ['label' => 'Hasil', 'tone' => 'success', 'steps' => [
                ['label' => 'Hitung refund', 'detail' => 'Nominal mengikuti nilai item yang dikembalikan.'],
                ['label' => 'Balik HPP dan margin', 'detail' => 'Biaya alokasi asli digunakan.'],
                ['label' => 'Simpan retur selesai', 'detail' => 'Penjualan menjadi returned tanpa menghapus transaksi awal.', 'kind' => 'result'],
            ]],
        ],
    ],

    'pos-void' => [
        'title' => 'Void penjualan POS',
        'summary' => 'Pengguna dengan izin void menjalankan pembalikan langsung selama shift belum dikunci.',
        'verified_by' => ['app/Services/Retail/PosService.php', 'app/Policies/PosSalePolicy.php'],
        'lanes' => [
            ['label' => 'Void', 'tone' => 'danger', 'steps' => [
                ['label' => 'Periksa izin dan lokasi', 'detail' => 'Memerlukan pos.void pada cabang yang dapat diakses.'],
                ['label' => 'Periksa status penjualan', 'detail' => 'Penjualan harus masih dapat di-void.', 'kind' => 'decision'],
                ['label' => 'Periksa status shift', 'detail' => 'Shift closing atau closed menolak void.'],
                ['label' => 'Isi alasan', 'detail' => 'Alasan pembatalan wajib disimpan.'],
                ['label' => 'Pulihkan alokasi', 'detail' => 'Reguler kembali ke stok; darurat kembali ke lot.'],
                ['label' => 'Status void approved', 'detail' => 'Aktor menjadi pemohon dan penyetuju; audit tetap tercatat.', 'kind' => 'result'],
            ]],
        ],
    ],

    'shift-expense' => [
        'title' => 'Mencatat pengeluaran shift',
        'summary' => 'Pengeluaran hanya dapat dicatat pada shift terbuka di cabang yang dapat diakses.',
        'verified_by' => ['app/Services/Retail/CashShiftService.php'],
        'lanes' => [
            ['label' => 'Pengeluaran', 'tone' => 'warning', 'steps' => [
                ['label' => 'Pilih shift terbuka', 'detail' => 'Shift lain atau terkunci ditolak.'],
                ['label' => 'Isi kategori dan nominal', 'detail' => 'Metode, catatan, waktu, dan bukti dapat dilengkapi.'],
                ['label' => 'Nominal di atas Rp1 juta?', 'detail' => 'Kasir biasa ditolak; memerlukan izin approval shift.', 'kind' => 'decision'],
                ['label' => 'Simpan pengeluaran', 'detail' => 'Masuk ke perhitungan kas harapan closing.', 'kind' => 'result'],
            ]],
        ],
    ],

    'shift-closing' => [
        'title' => 'Mengajukan closing shift',
        'summary' => 'Kasir pemilik menghitung uang fisik dan mengirim snapshot rekonsiliasi untuk diperiksa kepala toko.',
        'verified_by' => ['app/Services/Retail/CashShiftService.php'],
        'lanes' => [
            ['label' => 'Closing', 'tone' => 'primary', 'steps' => [
                ['label' => 'Shift open atau rejected', 'detail' => 'Hanya kasir pemilik yang dapat mengajukan.'],
                ['label' => 'Hitung kas fisik', 'detail' => 'Nominal pecahan atau total aktual dicatat.'],
                ['label' => 'Hitung kas harapan', 'detail' => 'Modal + tunai + refund pemasok - pengeluaran - refund penjualan.'],
                ['label' => 'Hitung selisih', 'detail' => 'Kas aktual dikurangi kas harapan.'],
                ['label' => 'Simpan catatan serah terima', 'detail' => 'Alasan selisih dapat dicatat.'],
                ['label' => 'Closing submitted', 'detail' => 'Menunggu keputusan pengguna berizin approval.', 'kind' => 'result'],
            ]],
        ],
    ],

    'shift-approval' => [
        'title' => 'Persetujuan closing shift',
        'summary' => 'Hanya closing submitted pada lokasi yang diizinkan yang dapat disetujui atau ditolak.',
        'verified_by' => ['app/Services/Retail/CashShiftService.php', 'app/Policies/CashShiftPolicy.php'],
        'lanes' => [
            ['label' => 'Pemeriksaan', 'tone' => 'primary', 'steps' => [
                ['label' => 'Buka closing submitted', 'detail' => 'Izin approval dan akses lokasi diperiksa.'],
                ['label' => 'Cocokkan transaksi dan kas', 'detail' => 'Penjualan, refund, pengeluaran, dan selisih ditinjau.'],
                ['label' => 'Putuskan', 'detail' => 'Setujui atau tolak dengan catatan.', 'kind' => 'decision'],
            ]],
            ['label' => 'Disetujui', 'tone' => 'success', 'steps' => [
                ['label' => 'Approve', 'detail' => 'Penyetuju dan waktu dicatat.'],
                ['label' => 'Status closed', 'detail' => 'Shift dikunci.', 'kind' => 'result'],
            ]],
            ['label' => 'Ditolak', 'tone' => 'danger', 'steps' => [
                ['label' => 'Reject', 'detail' => 'Catatan penolakan wajib.'],
                ['label' => 'Status rejected', 'detail' => 'Kasir dapat memperbaiki lalu submit ulang.', 'kind' => 'result'],
            ]],
        ],
    ],

    'restock-request' => [
        'title' => 'Permintaan restock dari gudang',
        'summary' => 'Permintaan tidak mengubah stok sampai transfer dikirim dan benar-benar diterima toko.',
        'verified_by' => ['app/Services/Warehouse/RestockRequestService.php', 'app/Services/Warehouse/StockTransferService.php'],
        'lanes' => [
            ['label' => 'Pengajuan toko', 'tone' => 'primary', 'steps' => [
                ['label' => 'Pilih cabang dan gudang sumber', 'detail' => 'Cabang harus termasuk penugasan.'],
                ['label' => 'Isi produk dan qty', 'detail' => 'Produk unik dan qty lebih dari nol.'],
                ['label' => 'Simpan draft atau ajukan', 'detail' => 'Submit mengubah status menjadi pending approval.', 'kind' => 'decision'],
            ]],
            ['label' => 'Keputusan gudang', 'tone' => 'warning', 'steps' => [
                ['label' => 'Tinjau permintaan', 'detail' => 'Qty disetujui tidak boleh melebihi permintaan.'],
                ['label' => 'Approve atau reject', 'detail' => 'Penolakan berhenti; approval dapat dikonversi.', 'kind' => 'decision'],
                ['label' => 'Buat transfer', 'detail' => 'Transfer mengacu ke permintaan restock.'],
                ['label' => 'Reserve, kemas, kirim', 'detail' => 'Stok sumber keluar saat pengiriman.'],
                ['label' => 'Terima di toko', 'detail' => 'Stok tujuan bertambah sesuai qty fisik.', 'kind' => 'result'],
            ]],
        ],
    ],

    'transfer-receive' => [
        'title' => 'Penerimaan transfer di toko',
        'summary' => 'Setiap jumlah kiriman harus dipilah menjadi diterima baik, rusak, atau selisih tanpa melebihi qty dikirim.',
        'verified_by' => ['app/Services/Warehouse/StockTransferService.php'],
        'lanes' => [
            ['label' => 'Penerimaan', 'tone' => 'primary', 'steps' => [
                ['label' => 'Transfer shipped', 'detail' => 'Status harus mengizinkan penerimaan.'],
                ['label' => 'Hitung fisik', 'detail' => 'Isi qty baik, rusak, dan selisih.'],
                ['label' => 'Validasi total', 'detail' => 'Akumulasi tidak boleh melebihi qty dikirim.', 'kind' => 'decision'],
                ['label' => 'Simpan bukti penerimaan', 'detail' => 'Bukti dan catatan dikaitkan ke receipt.'],
            ]],
            ['label' => 'Dampak stok', 'tone' => 'success', 'steps' => [
                ['label' => 'Qty baik', 'detail' => 'Masuk sebagai stok tersedia toko.'],
                ['label' => 'Qty rusak', 'detail' => 'Masuk lalu langsung ditandai sebagai stok rusak.'],
                ['label' => 'Ada selisih?', 'detail' => 'Transfer tetap partially received sampai diselesaikan.', 'kind' => 'decision'],
                ['label' => 'Seluruh qty terjelaskan', 'detail' => 'Status fully received lalu dapat diselesaikan.', 'kind' => 'result'],
            ]],
        ],
    ],

    'direct-store-purchase' => [
        'title' => 'Pembelian pemasok langsung ke toko',
        'summary' => 'PO menggunakan toko sebagai lokasi tujuan; stok bertambah hanya ketika penerimaan diposting.',
        'verified_by' => ['app/Services/Purchasing/PurchaseOrderService.php', 'app/Services/Warehouse/GoodsReceiptService.php'],
        'lanes' => [
            ['label' => 'Purchase Order', 'tone' => 'primary', 'steps' => [
                ['label' => 'Buat PO draft', 'detail' => 'Pilih pemasok, toko tujuan, item, biaya, dan jadwal.'],
                ['label' => 'Ajukan PO', 'detail' => 'PO tanpa item tidak dapat diajukan.'],
                ['label' => 'Persetujuan pusat', 'detail' => 'Approver memutuskan PO submitted.', 'kind' => 'decision'],
                ['label' => 'Approved atau sent', 'detail' => 'PO siap menerima barang.'],
            ]],
            ['label' => 'Penerimaan toko', 'tone' => 'success', 'steps' => [
                ['label' => 'Buat receipt draft', 'detail' => 'Tujuan otomatis mengikuti lokasi tujuan PO.'],
                ['label' => 'Catat hasil QC', 'detail' => 'Accepted, rejected, damaged, atau returned to supplier.'],
                ['label' => 'Posting receipt', 'detail' => 'Dokumen terkunci dan mutasi dibuat.'],
                ['label' => 'Accepted masuk stok reguler', 'detail' => 'Stok toko dan HPP rata-rata bergerak diperbarui.'],
                ['label' => 'PO partial atau completed', 'detail' => 'Berdasarkan total qty yang telah diterima.', 'kind' => 'result'],
            ]],
        ],
    ],

    'product-request' => [
        'title' => 'Pengajuan produk baru dari toko',
        'summary' => 'Produk baru diperiksa terhadap duplikasi sebelum master produk dan harga toko dibuat.',
        'verified_by' => ['app/Services/Retail/ProductRequestService.php', 'app/Http/Controllers/Retail/ProductRequestController.php'],
        'lanes' => [
            ['label' => 'Pengajuan', 'tone' => 'primary', 'steps' => [
                ['label' => 'Isi data produk', 'detail' => 'Nama, kategori, satuan, biaya, harga, dan toko.'],
                ['label' => 'Periksa duplikasi', 'detail' => 'Nama, SKU, dan barcode dibandingkan dengan master.', 'kind' => 'decision'],
                ['label' => 'Pending approval', 'detail' => 'Pengajuan menunggu pemeriksa master data.'],
                ['label' => 'Approve atau reject', 'detail' => 'Pengajuan yang sudah diputus tidak dapat diproses ulang.', 'kind' => 'decision'],
            ]],
            ['label' => 'Jika disetujui', 'tone' => 'success', 'steps' => [
                ['label' => 'Buat produk aktif', 'detail' => 'SKU otomatis dibuat jika tidak diajukan.'],
                ['label' => 'Buat unit dan barcode', 'detail' => 'Barcode dibuat bila diisi.'],
                ['label' => 'Buat harga retail toko', 'detail' => 'Harga berlaku pada cabang pengaju.'],
                ['label' => 'Hubungkan pemasok', 'detail' => 'Dilakukan bila supplier diisi.'],
                ['label' => 'Produk siap masuk PO', 'detail' => 'Stok tetap nol sampai barang diterima.', 'kind' => 'result'],
            ]],
        ],
    ],

    'retail-receivable' => [
        'title' => 'Piutang pelanggan toko',
        'summary' => 'Piutang lahir dari pembayaran kredit POS dan berkurang melalui alokasi pembayaran atau credit note yang disetujui.',
        'verified_by' => ['app/Services/Receivables/ReceivableService.php', 'app/Http/Controllers/Receivables/ReceivableController.php'],
        'lanes' => [
            ['label' => 'Membentuk piutang', 'tone' => 'warning', 'steps' => [
                ['label' => 'Pilih pelanggan', 'detail' => 'Wajib untuk metode kredit.'],
                ['label' => 'Periksa limit dan tunggakan', 'detail' => 'Kredit ditolak jika aturan tidak terpenuhi.', 'kind' => 'decision'],
                ['label' => 'Checkout kredit', 'detail' => 'Pembayaran kredit disimpan pada penjualan.'],
                ['label' => 'Buat piutang retail', 'detail' => 'Saldo, jatuh tempo, invoice, dan ledger dicatat.', 'kind' => 'result'],
            ]],
            ['label' => 'Pembayaran', 'tone' => 'success', 'steps' => [
                ['label' => 'Pilih pelanggan dan tagihan', 'detail' => 'Hanya saldo yang masih terbuka.'],
                ['label' => 'Isi alokasi pembayaran', 'detail' => 'Total alokasi tidak boleh melampaui pembayaran atau saldo.'],
                ['label' => 'Simpan pembayaran', 'detail' => 'Ledger dan saldo pelanggan diperbarui.'],
                ['label' => 'Partial atau paid', 'detail' => 'Status mengikuti saldo tersisa.', 'kind' => 'result'],
            ]],
            ['label' => 'Tindak lanjut dan koreksi', 'tone' => 'primary', 'steps' => [
                ['label' => 'Catat reminder', 'detail' => 'Hasil komunikasi dan jadwal follow-up disimpan.'],
                ['label' => 'Perlu koreksi saldo?', 'detail' => 'Buat credit note dengan alasan.', 'kind' => 'decision'],
                ['label' => 'Approval credit note', 'detail' => 'Saldo baru berubah setelah disetujui.', 'kind' => 'result'],
            ]],
        ],
    ],

    'branch-monitoring' => [
        'title' => 'Monitoring operasional cabang',
        'summary' => 'Kepala toko membaca data sesuai lokasi penugasan lalu menindaklanjuti modul sumbernya.',
        'verified_by' => ['app/Http/Controllers/Reports/RetailDashboardController.php', 'app/Http/Controllers/Reports/ReportController.php'],
        'lanes' => [
            ['label' => 'Siklus kontrol', 'tone' => 'primary', 'steps' => [
                ['label' => 'Pilih konteks cabang', 'detail' => 'Cabang di luar penugasan tidak dapat dibaca.'],
                ['label' => 'Baca KPI dashboard', 'detail' => 'Penjualan, transaksi, shift, stok, retur, dan void.'],
                ['label' => 'Temukan pengecualian', 'detail' => 'Shift menggantung, selisih, stok kritis, atau piutang.', 'kind' => 'decision'],
                ['label' => 'Buka modul sumber', 'detail' => 'Shift, restock, stok, laporan, atau kehadiran.'],
                ['label' => 'Tindak lanjut dan pantau', 'detail' => 'Keputusan tetap memakai workflow modul terkait.', 'kind' => 'result'],
            ]],
        ],
    ],

    'attendance-request' => [
        'title' => 'Pengajuan izin, sakit, cuti, atau lembur',
        'summary' => 'Pengajuan tidak boleh bertabrakan dan baru memengaruhi kehadiran setelah disetujui.',
        'verified_by' => ['app/Services/Attendance/AttendanceService.php', 'app/Http/Controllers/Attendance/AttendanceRequestController.php'],
        'lanes' => [
            ['label' => 'Pengajuan', 'tone' => 'primary', 'steps' => [
                ['label' => 'Pilih jenis dan periode', 'detail' => 'Waktu selesai harus setelah waktu mulai.'],
                ['label' => 'Isi alasan dan bukti', 'detail' => 'Pengganti dapat dipilih bila diperlukan.'],
                ['label' => 'Periksa bentrok', 'detail' => 'Overlap dengan pengajuan selain rejected ditolak.', 'kind' => 'decision'],
                ['label' => 'Status pending', 'detail' => 'Menunggu approver lokasi.'],
                ['label' => 'Approve atau reject', 'detail' => 'Keputusan dan catatan disimpan.', 'kind' => 'decision'],
                ['label' => 'Buat status kehadiran', 'detail' => 'Hanya approval yang membuat izin, sakit, cuti, atau lembur.', 'kind' => 'result'],
            ]],
        ],
    ],

    'attendance-correction' => [
        'title' => 'Koreksi absensi',
        'summary' => 'Perubahan waktu kehadiran menggunakan pengajuan, snapshot sebelum/sesudah, dan keputusan approver lokasi.',
        'verified_by' => ['app/Services/Attendance/AttendanceService.php', 'app/Http/Controllers/Attendance/CorrectionController.php'],
        'lanes' => [
            ['label' => 'Koreksi', 'tone' => 'warning', 'steps' => [
                ['label' => 'Pilih absensi', 'detail' => 'Pemohon harus memiliki akses ke lokasi absensi.'],
                ['label' => 'Ajukan waktu baru', 'detail' => 'Alasan dan bukti disimpan bersama snapshot awal.'],
                ['label' => 'Status pending', 'detail' => 'Data kehadiran belum berubah.'],
                ['label' => 'Approve atau reject', 'detail' => 'Hanya approver pada lokasi terkait.', 'kind' => 'decision'],
                ['label' => 'Hitung ulang kehadiran', 'detail' => 'Jika approved, status, terlambat, pulang awal, dan durasi diperbarui.', 'kind' => 'result'],
            ]],
        ],
    ],

    'store-guardrails' => [
        'title' => 'Pagar pengaman transaksi toko',
        'summary' => 'Setiap tindakan sensitif melewati izin, lokasi, status dokumen, dan validasi jumlah sebelum disimpan.',
        'verified_by' => ['routes/web.php', 'app/Services/Retail/PosService.php', 'app/Services/Warehouse/StockTransferService.php'],
        'lanes' => [
            ['label' => 'Validasi umum', 'tone' => 'danger', 'steps' => [
                ['label' => 'Pengguna menjalankan aksi', 'detail' => 'POS, shift, penerimaan, retur, atau void.'],
                ['label' => 'Periksa permission', 'detail' => 'Route dan policy memeriksa kewenangan.'],
                ['label' => 'Periksa lokasi kerja', 'detail' => 'Data di luar penugasan ditolak.'],
                ['label' => 'Periksa status dan qty', 'detail' => 'Transisi salah, over-receive, dan stok kurang ditolak.'],
                ['label' => 'Valid?', 'detail' => 'Jika tidak valid, transaksi dibatalkan tanpa mutasi.', 'kind' => 'decision'],
                ['label' => 'Simpan dengan audit', 'detail' => 'Jika valid, perubahan dan riwayat dicatat.', 'kind' => 'result'],
            ]],
        ],
    ],

    'cashier-daily' => [
        'title' => 'Siklus harian kasir',
        'summary' => 'Urutan ini menjaga hubungan kehadiran, shift, transaksi, kas fisik, dan closing.',
        'verified_by' => ['app/Services/Attendance/AttendanceService.php', 'app/Services/Retail/CashShiftService.php', 'app/Services/Retail/PosService.php'],
        'lanes' => [
            ['label' => 'Satu hari kerja', 'tone' => 'primary', 'steps' => [
                ['label' => 'Check-in', 'detail' => 'Aktifkan kehadiran pada jadwal toko.'],
                ['label' => 'Buka shift', 'detail' => 'Catat modal awal.'],
                ['label' => 'Layani POS', 'detail' => 'Catat transaksi, hold, retur, dan pengeluaran.'],
                ['label' => 'Rekonsiliasi', 'detail' => 'Hitung kas fisik dan selisih.'],
                ['label' => 'Submit closing', 'detail' => 'Tunggu approval kepala toko.'],
                ['label' => 'Check-out', 'detail' => 'Tutup catatan kehadiran.', 'kind' => 'result'],
            ]],
        ],
    ],

    'manager-daily' => [
        'title' => 'Siklus harian kepala toko',
        'summary' => 'Kepala toko meninjau pengecualian dan memprosesnya melalui workflow sumber, bukan mengubah data final.',
        'verified_by' => ['app/Http/Controllers/Reports/RetailDashboardController.php', 'app/Services/Retail/CashShiftService.php', 'app/Services/Warehouse/RestockRequestService.php'],
        'lanes' => [
            ['label' => 'Kontrol harian', 'tone' => 'success', 'steps' => [
                ['label' => 'Periksa dashboard', 'detail' => 'Pilih cabang dalam penugasan.'],
                ['label' => 'Tinjau shift dan selisih', 'detail' => 'Approve atau reject closing.'],
                ['label' => 'Tinjau stok', 'detail' => 'Restock, transfer, pembelian toko, dan stok darurat.'],
                ['label' => 'Tinjau retur, void, piutang', 'detail' => 'Ikuti status dan bukti pada modul terkait.'],
                ['label' => 'Tinjau kehadiran', 'detail' => 'Putuskan pengajuan dan koreksi.'],
                ['label' => 'Pastikan tindak lanjut', 'detail' => 'Semua pengecualian memiliki keputusan atau catatan.', 'kind' => 'result'],
            ]],
        ],
    ],
];

return [
    ...$storeFlows,
    ...require __DIR__.'/guide-flows-warehouse.php',
    ...require __DIR__.'/guide-flows-purchasing.php',
    ...require __DIR__.'/guide-flows-owner.php',
    ...require __DIR__.'/guide-flows-checklists.php',
    ...require __DIR__.'/guide-flows-staff-bonuses.php',
];
