# Dokumentasi Update Stok, HPP Berlapis, dan Harga Jual Per Lokasi

Tanggal: 31 Oktober 2026  
Status: implementasi aktif dan menjadi baseline pengembangan lanjutan

## 1. Ringkasan

Pembaruan ini mengubah persediaan dari satu HPP global menjadi persediaan berbasis lapisan stok. Setiap penerimaan menyimpan biaya aslinya, sementara harga jual aktif ditentukan per lokasi kerja.

Tujuan bisnisnya:

- HPP pembelian baru tidak menimpa HPP stok lama;
- laba dihitung dari lapisan barang yang benar-benar keluar;
- gudang dan toko dapat mempunyai harga jual berbeda untuk produk yang sama;
- transfer mempertahankan HPP sumber;
- histori stok, biaya, harga, dan pelaksana dapat diaudit;
- alur tetap mendukung toko yang beroperasi tanpa gudang pusat.

## 2. Keputusan domain

### 2.1 Stok agregat

`stocks` tetap menjadi saldo operasional cepat per produk, lokasi kerja, dan rak/bin. Saldo ini dipakai untuk pemeriksaan ketersediaan, tetapi bukan sumber rincian HPP.

```text
Stok tersedia = quantity_on_hand - quantity_reserved - quantity_damaged
```

### 2.2 Lapisan HPP

`stock_batches` menjadi lapisan stok aktual. Satu produk pada satu lokasi dapat mempunyai beberapa lapisan dengan HPP berbeda.

| Lokasi | Batch | Sisa | HPP/base unit | Harga jual lokasi |
| --- | --- | ---: | ---: | ---: |
| Gudang A | BATCH-001 | 12 pcs | Rp10.000 | Rp15.000 |
| Gudang A | BATCH-002 | 20 pcs | Rp12.000 | Rp15.000 |
| Toko A | turunan BATCH-001 | 5 pcs | Rp10.000 | Rp16.000 |

Masuknya `BATCH-002` tidak mengubah HPP `BATCH-001`. `products.cost_price` dipertahankan hanya sebagai fallback kompatibilitas data lama, bukan HPP transaksi baru.

### 2.3 Ledger alokasi

`stock_layer_allocations` adalah ledger append-only untuk mencatat lapisan yang digunakan atau dikembalikan oleh dokumen. Catatannya berisi batch, lokasi, referensi, event, kuantitas, HPP snapshot, harga jual snapshot, actor, dan waktu.

Histori final tidak diedit. Koreksi memakai `void`, `return`, `reverse`, atau dokumen koreksi yang sah.

### 2.4 Harga jual per lokasi

`product_prices.work_location_id` menentukan harga aktif pada gudang atau toko. Harga di Toko A tidak mengubah Gudang A atau Toko B.

Prioritas sumber harga:

1. harga aktif produk pada lokasi kerja;
2. aturan harga cabang/channel yang berlaku;
3. harga global lama sebagai fallback.

Harga jual tetap satu harga aktif per produk/lokasi/channel walaupun stok mempunyai beberapa HPP. Perubahan harga masuk ke riwayat harga.

## 3. Struktur data

### 3.1 `stock_batches`

| Kolom | Fungsi |
| --- | --- |
| `source_batch_id` | Garis keturunan batch hasil transfer/retur. |
| `work_location_id` | Gudang atau toko pemilik lapisan. |
| `warehouse_location_id` | Rak/bin fisik lapisan. |
| `source_type`, `source_id`, `source_item_id` | Dokumen dan item sumber. |
| `unit_id` | Satuan saat barang diterima. |
| `conversion_factor_snapshot` | Faktor menuju satuan dasar saat transaksi. |
| `cost_price` | HPP per satuan dasar lapisan. |
| `entry_sale_price_snapshot` | Harga jual lokasi saat lapisan masuk. |
| `quantity_on_hand` | Kuantitas tersisa. |
| `quantity_reserved` | Kuantitas dicadangkan. |
| `quantity_damaged` | Kuantitas rusak yang tetap membawa HPP asal. |
| `received_at`, `expires_at` | Dasar FIFO dan informasi kedaluwarsa. |

Nomor batch pemasok boleh berada di beberapa lokasi setelah transfer. Identitas lapisan adalah `id` dan lineage, bukan `batch_no` global.

### 3.2 `stock_layer_allocations`

Kolom utama:

- `stock_batch_id`, `product_id`, dan lokasi;
- `reference_type` dan `reference_id`;
- `event_type`, misalnya penjualan, transfer, koreksi, void, atau retur;
- `quantity` dan `unit_cost` snapshot;
- `sale_price_snapshot`;
- `actor_user_id`, `metadata`, dan `occurred_at`.

### 3.3 Snapshot dokumen

- `goods_receipt_items`: `actual_unit_cost` dan `sale_price`;
- `stock_transfer_items`: harga asal, harga tujuan, dan batch terpilih;
- `stock_direct_adjustment_items`: HPP, harga jual, dan batch terpilih;
- `pos_sale_items.hpp_snapshot`: HPP rata-rata alokasi untuk laporan lama;
- rincian HPP aktual tetap tersimpan per allocation.

Semua nominal menggunakan decimal, bukan float. Kuantitas dan faktor konversi juga menggunakan presisi decimal.

## 4. Alur stok masuk eksternal

Barang masuk eksternal membuat lapisan baru dan tidak menggabungkan HPP baru ke stok lama.

### 4.1 Goods Receipt pemasok

Saat penerimaan diposting:

1. validasi lokasi, produk, satuan, kuantitas, HPP aktual, dan harga jual;
2. konversi kuantitas ke base unit memakai snapshot faktor;
3. tambah saldo agregat `stocks`;
4. buat `stock_batches` baru dengan `actual_unit_cost`;
5. simpan allocation/mutation dokumen;
6. jadikan `sale_price` harga aktif produk pada lokasi penerimaan;
7. catat actor dan dokumen sumber.

Goods Receipt boleh langsung menuju toko; gudang pusat bukan syarat.

### 4.2 Stok awal

Stok awal positif wajib mempunyai HPP dan dapat mengisi harga lokasi. Setelah disetujui, item positif membuat lapisan baru. Selisih negatif mengambil lapisan tersedia.

### 4.3 Pembelian darurat

Regularisasi pembelian darurat membuat lapisan berdasarkan HPP aktual dokumen. Proses ini tidak menimpa `products.cost_price` dan tidak memakai moving average.

### 4.4 Koreksi stok

- koreksi positif wajib mempunyai HPP dan membuat lapisan baru;
- harga jual snapshot dapat memperbarui harga aktif lokasi;
- koreksi negatif mengambil lapisan terpilih;
- jika tidak dipilih manual, allocator memakai FIFO;
- opname tidak mengubah HPP lapisan yang telah ada.

## 5. Transfer antar lokasi

Transfer mendukung gudang ke toko, toko ke gudang, antargudang, dan antartoko sesuai akses lokasi dan permission.

1. Pengguna memilih produk dan kuantitas.
2. Lapisan sumber dipilih manual atau diselesaikan FIFO.
3. HPP sumber bersifat read-only.
4. Harga tujuan default mengikuti harga aktif lokasi sumber.
5. Hanya pengguna dengan `prices.update` yang dapat mengganti harga tujuan.
6. Saat diterima, sistem membuat lapisan turunan pada tujuan.
7. `source_batch_id` mempertahankan lineage dan HPP sumber.
8. Harga tujuan menjadi harga aktif lokasi tujuan bila diisi.

Jika transfer mengambil dua HPP, tujuan menerima dua lapisan turunan. Nilai persediaan total sebelum dan sesudah transfer harus sama. Perpindahan antarrak pada lokasi yang sama hanya memindahkan posisi fisik, bukan menciptakan HPP baru.

## 6. Penjualan POS

### 6.1 Pemilihan batch

Keranjang menampilkan panel **Batch Pengambilan** dengan:

- nomor batch;
- tanggal masuk dan kedaluwarsa;
- stok tersedia;
- tanda **TERLAMA** pada kandidat FIFO pertama;
- status **FIFO otomatis** dan jumlah batch dipilih.

HPP tidak boleh dikirim ke HTML atau JSON kasir tanpa `margins.view_sensitive`.

### 6.2 Default FIFO

Lapisan diurutkan dari tanggal masuk paling lama. Jika kasir tidak memilih manual, server tetap melakukan alokasi FIFO sehingga checkout tidak gagal hanya karena pilihan batch belum disentuh.

Kasir dapat memilih batch lain ketika barang fisik yang diambil berbeda. Satu baris penjualan dapat memakai beberapa lapisan bila batch pertama tidak mencukupi.

### 6.3 HPP dan margin

```text
Total HPP transaksi = SUM(qty alokasi x HPP lapisan)
Margin transaksi    = penjualan bersih - total HPP transaksi
```

Contoh penjualan 8 pcs:

| Alokasi | Qty | HPP | Nilai HPP |
| --- | ---: | ---: | ---: |
| Batch lama | 5 | Rp10.000 | Rp50.000 |
| Batch baru | 3 | Rp12.000 | Rp36.000 |
| Total | 8 | - | Rp86.000 |

`PosSaleItem.hpp_snapshot` menyimpan rata-rata tertimbang Rp10.750 untuk kompatibilitas, tetapi dua lapisan asli tetap dapat diaudit.

### 6.4 Void dan retur

Void atau retur mengembalikan kuantitas ke lapisan asal berdasarkan allocation transaksi. Sistem tidak membuat HPP baru dan tidak mengembalikan stok ke batch acak.

## 7. Detail produk dan data sensitif

Halaman detail produk menampilkan:

- saldo per lokasi;
- harga jual aktif per lokasi;
- total nilai persediaan;
- batch, lapisan HPP, sisa, tanggal masuk, dan kedaluwarsa;
- sumber serta riwayat perpindahan bila tersedia.

```text
Nilai persediaan = SUM(sisa kuantitas layak jual lapisan x HPP lapisan)
```

HPP, nilai persediaan, dan margin hanya boleh diterima pengguna dengan `margins.view_sensitive`. Filter wajib diterapkan pada server, bukan hanya CSS.

## 8. Migrasi dan data lama

| Migration | Kegunaan |
| --- | --- |
| `2026_10_30_000000_add_location_stock_layers.php` | Menambah lineage, allocation ledger, harga lokasi, snapshot, serta lapisan `LEGACY`. |
| `2026_10_30_000100_allow_stock_batch_lineage_per_location.php` | Mengizinkan nomor batch sama pada lokasi berbeda. |
| `2026_10_31_000000_recover_missing_stock_layers.php` | Memulihkan saldo lokal yang kehilangan lapisan dan merekonsiliasi pemakaian historis secara FIFO. |

Aturan backfill:

- satu `LEGACY-{stock_id}` dibuat untuk saldo/rak tanpa batch;
- kuantitas mengikuti saldo saat migrasi;
- HPP memakai `products.cost_price` lama karena provenance tidak tersedia;
- harga cabang lama dipetakan ke lokasi kerja;
- saldo dan laporan transaksi lama tidak diubah.

Contoh recovery lokal produk `tumbler`: lapisan HPP Rp21.000 tersisa 19 pcs dan HPP Rp15.000 tersisa 5 pcs, total 24 pcs. Ini hasil rekonstruksi dokumen lokal, bukan data hard-coded untuk produk lain.

## 9. Hak akses dan audit

| Aktivitas | Kontrol |
| --- | --- |
| Melihat stok/lapisan | Permission modul dan scope lokasi. |
| Melihat HPP/margin | `margins.view_sensitive`. |
| Mengubah harga tujuan transfer | `prices.update`. |
| Posting penerimaan | Permission Goods Receipt dan akses tujuan. |
| Koreksi/opname | Permission, approval, dan segregation of duties. |
| Penjualan POS | Shift/lokasi aktif dan permission POS. |

Mutasi kritis harus memakai transaksi database, lock saldo/lapisan, menyimpan actor, dan menghasilkan mutation/audit/allocation yang dapat direkonsiliasi.

## 10. Halaman pengujian manual

| Pengujian | URL lokal |
| --- | --- |
| Detail produk dan lapisan HPP | [Produk 1](http://127.0.0.1:8000/admin/products/1) |
| Daftar Goods Receipt | [Goods Receipt](http://127.0.0.1:8000/warehouse/goods-receipts) |
| Tambah penerimaan | [Tambah Goods Receipt](http://127.0.0.1:8000/warehouse/goods-receipts/create) |
| Stok awal | [Opening Stock](http://127.0.0.1:8000/warehouse/opening-stocks) |
| Transfer | [Buat Transfer](http://127.0.0.1:8000/warehouse/stock-transfers/create) |
| Penyesuaian | [Stock Adjustment](http://127.0.0.1:8000/warehouse/stock-adjustments) |
| Harga per lokasi | [Product Prices](http://127.0.0.1:8000/pricing/product-prices) |
| POS dan batch | [POS](http://127.0.0.1:8000/retail/pos) |

Gunakan akun yang mempunyai permission dan lokasi kerja sesuai modul.

### Skenario dua HPP

1. Terima 10 pcs produk X dengan HPP Rp10.000 dan harga jual Rp15.000.
2. Terima 10 pcs lagi dengan HPP Rp12.000 dan harga jual Rp16.000.
3. Buka detail produk dan pastikan ada dua lapisan.
4. Pastikan lapisan pertama tetap Rp10.000.
5. Pastikan harga aktif hanya berubah pada lokasi penerimaan.

### Skenario POS FIFO

1. Tambahkan produk yang mempunyai dua lapisan ke keranjang.
2. Pastikan **Batch Pengambilan** tampil dan batch tertua menjadi default.
3. Jual kuantitas melebihi batch pertama.
4. Pastikan allocation memakai batch pertama lalu kedua.
5. Void/retur dan pastikan kuantitas kembali ke lapisan asal.

### Skenario transfer

1. Transfer dari lokasi yang mempunyai dua lapisan.
2. Ambil kuantitas dari kedua lapisan.
3. Selesaikan penerimaan tujuan.
4. Pastikan tujuan mempunyai dua lapisan turunan dengan HPP yang sama.
5. Pastikan harga lokasi lain tidak ikut berubah.

## 11. Pengujian otomatis

Guard utama:

- `tests/Feature/Inventory/StockLayerWorkflowTest.php`;
- `tests/Feature/Admin/ProductStockLayerDetailTest.php`;
- `tests/Feature/Warehouse/GoodsReceiptWorkflowTest.php`;
- `tests/Feature/Warehouse/StockTransferWorkflowTest.php`;
- `tests/Feature/Warehouse/ManualOpeningStockTest.php`;
- `tests/Feature/Warehouse/ControlledStockAdjustmentTest.php`;
- `tests/Feature/Retail/PosWorkflowTest.php`;
- `tests/Feature/Retail/EmergencyPurchaseTest.php`;
- `tests/Feature/Retail/StoreInboundWorkflowTest.php`.

Acceptance penting:

- HPP lama tidak berubah setelah penerimaan baru;
- harga jual terisolasi per lokasi;
- transfer menjaga HPP dan nilai persediaan;
- POS default FIFO dan mendukung pilihan manual;
- HPP tidak bocor ke kasir tanpa izin sensitif;
- void/retur mengembalikan lapisan asal;
- koreksi positif tanpa HPP ditolak;
- koreksi negatif tidak melebihi stok lapisan.

Quality gate:

```bash
composer lint
composer analyse
composer test
```

`composer ci` hanya boleh dijalankan pada environment `testing`.

### Hasil verifikasi update 31 Oktober 2026

- `StockLayerWorkflowTest`: 2 test, 9 assertion lulus;
- `ProductStockLayerDetailTest`: 2 test, 20 assertion lulus;
- `GoodsReceiptWorkflowTest`: 9 test, 55 assertion lulus;
- `StockTransferWorkflowTest`: 20 test, 158 assertion lulus;
- `ManualOpeningStockTest`: 3 test, 23 assertion lulus;
- `ControlledStockAdjustmentTest`: 5 test, 27 assertion lulus;
- `PosWorkflowTest`: 10 test, 114 assertion lulus;
- `EmergencyPurchaseTest`: 20 test lulus;
- Larastan/PHPStan melalui `composer analyse`: lulus.

Catatan: satu skenario lama pada `StoreInboundWorkflowTest` masih mendapat HTTP 403 saat approval produk master. Kegagalan tersebut berada pada otorisasi approval inbound dan dicatat terpisah; bukan kegagalan perhitungan lapisan HPP atau pemilihan batch POS.

## 12. Deployment dan rekonsiliasi

1. Backup database.
2. Deploy kode dan asset.
3. Jalankan `php artisan migrate --force`.
4. Jalankan `php artisan config:clear`, `route:clear`, dan `view:clear`.
5. Jalankan `npm run build` bila frontend berubah.
6. Smoke test detail produk, penerimaan, transfer, dan POS.
7. Rekonsiliasi saldo `stocks` dengan total lapisan per produk/lokasi.
8. Periksa log dan audit setelah transaksi percobaan.

Jangan melakukan reset database atau rollback migration pada production.

## 13. Troubleshooting

### Saldo ada tetapi batch POS tidak tersedia

Pastikan total lapisan aktif sama dengan saldo `stocks` dan semua migration sudah berjalan. Jangan membuat batch manual tanpa dokumen sumber atau prosedur recovery yang dapat diaudit.

### Harga POS tidak sesuai lokasi

Periksa `product_prices.work_location_id`, channel, status, masa berlaku, dan lokasi aktif pengguna. Harga global hanya fallback.

### HPP terlihat oleh kasir

Periksa `margins.view_sensitive` dan payload endpoint POS. Data harus dihapus di server, bukan hanya disembunyikan dari UI.

### Nilai persediaan berubah setelah transfer

Bandingkan allocation sumber dan lapisan tujuan. HPP tujuan wajib sama dengan sumber; hanya harga jual tujuan yang dapat berbeda.

## 14. Batasan dan tindak lanjut

- Integrasi B2B, kehilangan, dan laporan laba harus membaca allocation, bukan HPP produk global.
- Modul baru yang mengurangi stok wajib memakai allocator yang sama dan mengembalikan lapisan saat void/reverse.
- Dashboard/ekspor tidak boleh mengirim HPP tanpa `margins.view_sensitive`.
- Rekonsiliasi stok agregat versus lapisan sebaiknya menjadi health check terjadwal.
- Barang rusak tetap membawa HPP asal sampai pemusnahan diposting.

## 15. Referensi implementasi

- `app/Services/Inventory/StockLayerService.php`
- `app/Services/Pricing/LocationProductPriceService.php`
- `app/Services/Warehouse/StockTransferService.php`
- `app/Services/Warehouse/OpeningStockService.php`
- `app/Services/Warehouse/StockDirectAdjustmentService.php`
- `resources/views/retail/pos/index.blade.php`
- `database/migrations/2026_10_30_000000_add_location_stock_layers.php`
- `database/migrations/2026_10_30_000100_allow_stock_batch_lineage_per_location.php`
- `database/migrations/2026_10_31_000000_recover_missing_stock_layers.php`

Dokumen ini adalah baseline perilaku stok, HPP, dan harga jual setelah update 31 Oktober 2026. Perubahan berikutnya pada inventory, POS, transfer, pricing, atau laporan wajib menjaga invariant di atas.
