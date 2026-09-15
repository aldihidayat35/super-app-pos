# Guide Book Gudang

> Target bulanan Staff Gudang dan Picker/Packer beserta rekap kepala gudang dijelaskan pada [Panduan Target dan Bonus Staf](target-bonus.md).

Panduan ini untuk `kepala_gudang`, `staff_gudang`, dan `picker_packer`. Akun manajemen gudang yang juga memiliki role `purchasing` dapat memakai alur pembelian di panduan ini bersama panduan Purchasing & Supplier. Bagian gudang adalah jantung aplikasi karena stok, HPP, pembelian, penerimaan, transfer, fulfillment B2B, opname, retur, dan loss berawal atau bermuara di sini.

## 1. Tujuan role Gudang

| Role | Fokus |
|---|---|
| `kepala_gudang` | Memimpin operasional gudang, approve PO/transfer/opname/retur/loss, memastikan stok akurat. |
| `staff_gudang` | Mencatat penerimaan, transfer, stok, retur, loss, dan shipment. |
| `picker_packer` | Picking, packing, dan update pengiriman tanpa melihat margin sensitif. |
| `purchasing` | Mengelola supplier, permintaan pembelian, PO, dan evaluasi pembelian. |

## 2. Menu utama Gudang

| Menu | URL | Fungsi |
|---|---|---|
| Dashboard Gudang | `/warehouse/dashboard` | KPI stok, mutasi, transfer, receipt, order pending. |
| Zona, Rak, dan Bin | `/warehouse/locations` | Struktur lokasi fisik gudang. |
| Saldo Stok | `/warehouse/stocks` | Stok on hand, reserved, damaged, available per lokasi. |
| Kartu Stok | `/warehouse/stock-card` | Riwayat mutasi produk secara berurutan. |
| Detail Mutasi | `/warehouse/stock-mutations/{id}` | Bukti read-only perubahan stok. |
| Batch/Lot Stok | `/warehouse/batches` | Batch, supplier, tanggal masuk, HPP batch. |
| Transfer Lokasi | `/warehouse/location-transfers` | Pindah stok antar zona/rak/bin internal. |
| Permintaan Pembelian | `/purchasing/requests` | Request pembelian manual/rekomendasi reorder. |
| Purchase Order | `/purchasing/purchase-orders` | PO ke supplier. |
| Penerimaan Barang | `/warehouse/goods-receipts` | Receipt dari PO dan QC. |
| Histori HPP | `/pricing/hpp-history` | Perubahan HPP dari stok awal dan receipt. |
| Performa Supplier | `/reports/suppliers` | Evaluasi supplier. |
| Permintaan Restock Cabang | `/retail/restock-requests` | Antrian permintaan restock dari toko. |
| Transfer Stok | `/warehouse/stock-transfers` | Transfer gudang ke cabang. |
| Order B2B Gudang | `/warehouse/b2b-orders` | Review, reserve, pack, ship order B2B. |
| Reserved Stock | `/warehouse/reservations` | Daftar stok yang sedang dicadangkan. |
| Pengiriman B2B | `/shipments` | Shipment dan proof of delivery. |
| Stok Opname | `/warehouse/stock-opnames` | Hitung fisik, variance, approval koreksi. |
| Barang Rusak & Loss | `/warehouse/losses` | Pencatatan barang rusak/hilang. |
| Retur | `/returns` | Retur pelanggan/toko/B2B dan settlement. |

```guide-flow
warehouse-overview
```

## 3. Prinsip stok yang wajib dipahami

Sistem memecah stok menjadi:

- `quantity_on_hand`: saldo fisik yang tercatat.
- `quantity_reserved`: stok yang sudah dijanjikan untuk dokumen aktif.
- `quantity_damaged`: stok rusak/blocked.
- `available`: stok yang boleh dipakai untuk order baru.

Rumus umum:

```text
available = on_hand - reserved - damaged
```

Aturan utama:

- Stok tidak boleh negatif.
- Semua perubahan stok harus lewat InventoryService.
- Setiap perubahan stok menulis `stock_mutations`.
- Mutasi stok tidak boleh diedit atau dihapus.
- Koreksi dilakukan melalui adjustment/opname/retur/loss/reversal.
- Transaksi stok memakai DB transaction dan locking agar aman dari double issue.

```guide-flow
warehouse-stock-principles
```

## 4. Setup lokasi gudang

### 4.1 Membuat zona/rak/bin

1. Buka `/warehouse/locations`.
2. Klik tambah lokasi.
3. Pilih tipe:
   - zone,
   - rack,
   - bin.
4. Isi kode, nama, parent location, kapasitas, jenis barang, dan status aktif.
5. Simpan.

Cara kerja di belakang layar:

- Lokasi gudang menjadi detail penyimpanan fisik di bawah warehouse.
- Stok dapat dikaitkan ke bin agar picking dan opname lebih akurat.
- Lokasi nonaktif tidak boleh dipakai untuk transaksi baru.

```guide-flow
warehouse-location-setup
```

### 4.2 Transfer internal antar lokasi

Gunakan `/warehouse/location-transfers` untuk memindahkan barang antar bin atau lokasi internal tanpa membuat transfer antarcabang.

1. Pilih lokasi kerja dan bin sumber.
2. Pilih lokasi kerja dan bin tujuan yang aktif.
3. Pilih produk yang memiliki stok tersedia pada sumber.
4. Isi jumlah dan alasan perpindahan.
5. Simpan transfer lokasi.

Sistem langsung membuat mutasi keluar dan masuk dalam satu transaksi. Jika stok tidak cukup atau salah satu lokasi berada di luar penugasan pengguna, seluruh perpindahan ditolak.

```guide-flow
warehouse-location-transfer
```

## 5. Melihat saldo dan kartu stok

### 5.1 Saldo stok

1. Buka `/warehouse/stocks`.
2. Filter gudang/cabang/lokasi/kategori/status.
3. Periksa on hand, reserved, damaged, available, minimum, safety stock, dan nilai HPP.
4. Klik link kartu stok bila ingin melihat riwayat.

```guide-flow
warehouse-stock-balance
```

### 5.2 Kartu stok

1. Buka `/warehouse/stock-card`.
2. Pilih produk.
3. Filter lokasi, tanggal, jenis mutasi, referensi, atau user.
4. Periksa qty masuk/keluar, before, change, after, dokumen asal, dan catatan.

Cara kerja di belakang layar:

- Kartu stok disusun dari `stock_mutations` urut waktu.
- Saldo berjalan harus cocok dengan saldo akhir di tabel `stocks`.
- Jika tidak cocok, jangan edit manual; buat investigasi dan dokumen koreksi.

```guide-flow
warehouse-stock-card
```

### 5.3 Batch/lot stok (opsional)

1. Buka `/warehouse/batches` jika penerimaan produk memang memakai batch atau lot.
2. Filter produk dan lokasi kerja.
3. Periksa nomor lot, supplier, tanggal masuk, saldo, dan biaya batch.
4. Gunakan kartu stok untuk menelusuri mutasi lengkapnya.

Operasional produk tanpa batch tetap menggunakan saldo dan kartu stok biasa. Halaman ini bersifat pemantauan dan tidak menggantikan dokumen penerimaan atau mutasi stok.

```guide-flow
warehouse-batch-lot
```

## 6. Pembelian dan Purchase Order

### 6.1 Permintaan pembelian

1. Buka `/purchasing/requests`.
2. Lihat rekomendasi dari stok minimum/reorder point.
3. Untuk request manual, isi gudang, produk, qty, alasan, prioritas, requester.
4. Submit request.
5. Kepala gudang/purchasing dapat approve, reject, atau convert to PO.

Cara kerja di belakang layar:

- Purchase request belum memengaruhi stok.
- Request menjadi dasar kebutuhan pembelian.
- Saat convert to PO, item dan qty menjadi draft PO.

```guide-flow
purchase-request
```

### 6.2 Membuat Purchase Order

1. Buka `/purchasing/purchase-orders/create`.
2. Isi header:
   - gudang,
   - supplier,
   - tanggal,
   - ETA,
   - termin,
   - catatan.
3. Isi item:
   - produk,
   - unit pembelian,
   - faktor konversi snapshot,
   - qty,
   - harga,
   - diskon,
   - pajak jika dipakai.
4. Isi biaya header:
   - diskon,
   - ongkir,
   - biaya tambahan.
5. Simpan draft atau submit.

Cara kerja di belakang layar:

- PO tidak menambah stok.
- Sistem menyimpan snapshot unit, faktor konversi, harga, dan subtotal.
- Nomor PO dibuat melalui document number service.
- PO approved tidak boleh diedit bebas.

```guide-flow
purchase-order-create
```

### 6.3 Approval dan kirim PO

1. Buka detail PO `/purchasing/purchase-orders/{id}`.
2. Periksa supplier, item, qty, harga, dan total.
3. Klik approve jika sesuai.
4. Klik send jika PO sudah dikirim ke supplier.
5. Cetak/unduh PO melalui `/purchasing/purchase-orders/{id}/print`.

Status PO:

- Draft,
- Submitted,
- Approved,
- Sent to Supplier,
- Partially Received,
- Completed,
- Cancelled.

```guide-flow
purchase-order-lifecycle
```

## 7. Penerimaan barang dan QC

### 7.1 Buat receipt dari PO

1. Buka `/warehouse/goods-receipts/create`.
2. Pilih PO.
3. Isi nomor surat jalan dan tanggal datang.
4. Periksa item outstanding.
5. Isi qty datang, qty diterima, qty ditolak, qty rusak.
6. Pilih lokasi rak/bin.
7. Isi batch/lot jika dipakai.
8. Upload foto/bukti jika perlu.
9. Simpan draft.

```guide-flow
goods-receipt-create
```

### 7.2 Posting receipt

1. Buka detail receipt.
2. Periksa QC dan lokasi.
3. Klik posting.

Cara kerja di belakang layar:

- Receipt draft belum menambah stok.
- Saat posted:
  - accepted qty menambah stok tersedia,
  - damaged qty masuk ke saldo fisik lalu dipindahkan ke stok rusak,
  - rejected qty dicatat sebagai hasil QC tanpa menambah stok,
  - stock mutation dibuat,
  - qty received PO diperbarui,
  - status PO bisa menjadi partially received atau completed,
  - HPP moving average dihitung,
  - histori HPP dan harga supplier tersimpan.
- Posting idempotent agar double submit tidak menggandakan stok.

```guide-flow
goods-receipt-post
```

## 8. Restock dan transfer stok ke toko

### 8.1 Review request toko

1. Buka `/retail/restock-requests`.
2. Periksa cabang, produk, qty, prioritas, dan catatan.
3. Bandingkan dengan stok pusat.
4. Approve, revisi qty, reject, atau convert menjadi transfer.

Jumlah yang disetujui tidak boleh lebih besar daripada jumlah permintaan. Permintaan yang disetujui belum mengubah stok sampai dikonversi dan transfer diproses.

```guide-flow
warehouse-restock-review
```

### 8.2 Buat transfer stok

1. Buka `/warehouse/stock-transfers/create`.
2. Pilih source gudang/lokasi.
3. Pilih destination cabang/lokasi.
4. Pilih request asal jika ada.
5. Isi item dan qty disetujui.
6. Submit/approve sesuai role.

```guide-flow
stock-transfer-create
```

### 8.3 Picking dan packing

1. Buka detail transfer.
2. Klik packing `/warehouse/stock-transfers/{id}/packing`.
3. Scan/cek produk dan lokasi.
4. Isi qty picked.
5. Catat short pick jika stok fisik kurang.
6. Isi nomor paket/foto/checker jika diperlukan.

```guide-flow
stock-transfer-packing
```

### 8.4 Pengiriman

1. Buka `/warehouse/stock-transfers/{id}/ship`.
2. Isi kurir/ekspedisi, kendaraan/resi, tanggal, biaya, bukti.
3. Klik kirim.

```guide-flow
stock-transfer-shipping
```

### 8.5 Penerimaan cabang

Cabang membuka `/retail/stock-transfers/{id}/receive`, lalu mengisi qty diterima, rusak, kurang, foto, dan catatan.

Cara kerja di belakang layar:

- Draft/pending belum mengurangi stok.
- Approved melakukan reserve stok sumber.
- Packing mencatat jumlah yang benar-benar dipilih dan short pick.
- Shipped melepas reservation lalu melakukan issue dari source.
- Receive menambah stok tujuan sesuai qty diterima.
- Selisih menjadi discrepancy dan perlu penyelesaian.
- Pembatalan sebelum shipped melepas reservation.

```guide-flow
transfer-receive
```

## 9. Fulfillment order B2B

### 9.1 Review order

1. Buka `/warehouse/b2b-orders`.
2. Pilih order yang perlu diproses.
3. Buka review.
4. Periksa pelanggan, limit kredit, item, stok available, harga, dan tanggal kirim.

```guide-flow
warehouse-b2b-review
```

### 9.2 Reserve

1. Klik reserve jika stok cukup dan order valid.
2. Sistem mencadangkan stok agar tidak dipakai transaksi lain.

Reservasi memiliki masa berlaku. Order yang dibatalkan, ditolak, atau kedaluwarsa harus melepaskan sisa reservasi agar stok kembali tersedia.

```guide-flow
warehouse-b2b-reserve
```

### 9.3 Pack dan ship

1. Klik pack setelah picking.
2. Buat shipment atau buka `/shipments`.
3. Isi kurir, jadwal, biaya, item.
4. Posting shipment jika barang dikirim.

Cara kerja di belakang layar:

- Reservasi mengurangi available, bukan on hand.
- Saat shipment/posting, stok benar-benar keluar sesuai aturan fulfillment.
- Invoice dapat diterbitkan dari order sesuai status.
- Pelanggan dapat melihat shipment dan mengonfirmasi penerimaan.

```guide-flow
warehouse-b2b-fulfillment
```

## 10. Stok opname

### 10.1 Membuat opname

1. Buka `/warehouse/stock-opnames`.
2. Buat opname.
3. Pilih lokasi, metode, PIC, threshold, dan jadwal.
4. Start opname.

Saat opname dimulai, sistem mengambil snapshot stok sesuai lingkup lokasi dan kategori. Snapshot ini menjadi acuan selisih selama counting.

```guide-flow
stock-opname-create
```

### 10.2 Counting

1. Buka halaman count `/warehouse/stock-opnames/{id}/count`.
2. Isi qty fisik per item.
3. Submit hasil counting.

Semua item wajib dihitung sebelum submit. Sistem juga memberi peringatan jika ada mutasi stok setelah opname dimulai.

```guide-flow
stock-opname-count
```

### 10.3 Variance dan approval

1. Buka variance `/warehouse/stock-opnames/{id}/variance`.
2. Periksa selisih qty dan nilai.
3. Submit opname ke antrean approval.
4. Jika selisih qty atau nilai melebihi threshold, approval wajib dilakukan `owner_approver` atau `super_admin`; selain itu kepala gudang dapat memutuskan.
5. Approver approve/reject.
6. Complete opname yang sudah approved untuk membuat adjustment.

Cara kerja di belakang layar:

- Sistem membandingkan system qty dengan counted qty.
- Semua opname yang disubmit menunggu approval; threshold menentukan tingkat approver.
- Adjustment stok hanya terjadi saat opname approved di-complete.
- Mutasi adjustment tetap append-only.

```guide-flow
stock-opname-approval
```

## 11. Retur dan loss

### 11.1 Retur

1. Buka `/returns`.
2. Buat retur.
3. Pilih sumber retur: POS, B2B, supplier, atau internal sesuai opsi.
4. Isi item, qty, alasan, kondisi barang, dan solusi diminta.
5. Lakukan inspeksi QC.
6. Ajukan approval jika diperlukan.
7. Settlement: refund, replacement, credit note, atau return supplier sesuai opsi dokumen.

Saat QC, qty baik langsung masuk stok tersedia. Qty rusak masuk ke saldo fisik lalu ditandai damaged. Qty yang ditolak tidak masuk stok. Nilai kerugian menentukan apakah retur harus menunggu approval sebelum settlement.

```guide-flow
warehouse-return
```

### 11.2 Loss/barang rusak

1. Buka `/warehouse/losses`.
2. Catat produk, lokasi, qty, jenis loss, disposisi, alasan, dan bukti.
3. Submit.
4. Kepala gudang/owner approve jika perlu.

Cara kerja di belakang layar:

- Nilai loss dihitung dari qty dan snapshot HPP produk.
- Loss di bawah atau sama dengan batas approval langsung berstatus approved dan membuat mutasi.
- Loss di atas batas menunggu approval dan baru memutasi stok setelah disetujui.
- Disposisi `damage` memindahkan qty ke damaged; disposisi `issue` mengurangi stok fisik.
- Mutasi ditolak jika stok tersedia tidak mencukupi.

```guide-flow
inventory-loss
```

## 12. HPP dan performa supplier

### 12.1 Histori HPP

1. Buka `/pricing/hpp-history`.
2. Filter produk, supplier, sumber, atau tanggal.
3. Periksa HPP sebelum/sesudah, incoming cost, landed cost, dan metode.

Cara kerja di belakang layar:

- Minimal metode yang dipakai adalah moving weighted average.
- Stok awal dicatat sebagai sumber `opening_stock` tanpa Goods Receipt.
- HPP dihitung dengan decimal, bukan float.
- HPP transaksi lama tidak berubah karena disimpan snapshot.

```guide-flow
hpp-history
```

### 12.2 Performa supplier

1. Buka `/reports/suppliers`.
2. Filter periode/supplier/produk.
3. Lihat jumlah receipt yang dinilai, rata-rata skor, skor kualitas, tingkat penerimaan, ranking, dan tren.
4. Tinjau supplier dengan skor total di bawah 80.

```guide-flow
supplier-performance
```

## 13. Hal yang tidak boleh dilakukan tim gudang

- Jangan mengeluarkan barang tanpa dokumen transfer, shipment, POS, retur, atau adjustment.
- Jangan menerima barang ke stok tanpa goods receipt posted.
- Jangan mengubah saldo stok langsung.
- Jangan menghapus stock mutation.
- Jangan memproses transfer dua kali jika koneksi lambat; tunggu respon sistem.
- Jangan memilih lokasi bin sembarangan saat receipt/picking.
- Jangan approve opname/loss tanpa bukti fisik.

```guide-flow
warehouse-guardrails
```

## 14. Checklist harian gudang

- [ ] Dashboard gudang diperiksa.
- [ ] Receipt draft yang sudah fisik diterima diposting.
- [ ] Transfer pending diproses.
- [ ] Order B2B pending direview.
- [ ] Reserved stock abnormal dicek.
- [ ] Stok kritis dilaporkan ke purchasing.
- [ ] Loss/damaged dicatat.
- [ ] Mutasi besar direview.

```guide-flow
warehouse-daily
```

## 15. Mencatat checklist gudang

Gunakan menu **Checklist Kerja** (`/checklist-kerja`) pada setiap gudang penugasan. Kepala Gudang dapat memantau checklist Staff Gudang dan Picker Packer pada lokasi yang sama. Rincian pengisian dan koreksi tersedia pada [Panduan Checklist Kerja](checklist-kerja.md).

```guide-flow
checklist-fill
```

## 16. Kehadiran tim gudang

### 16.1 Absen masuk dan pulang

```guide-flow
attendance-check
```

Staff Gudang dan Picker Packer login dengan akun masing-masing lalu membuka `/attendance`. Tombol **Absen Masuk** langsung menyimpan jam server Asia/Jakarta berdasarkan jadwal dan gudang penugasan. Saat pekerjaan selesai, seluruh poin checklist harian harus direspons sebelum **Absen Pulang** dapat digunakan. Shift lintas tengah malam tetap memakai tanggal mulai shift.

### 16.2 Verifikasi kepala gudang

```guide-flow
attendance-verification
```

Setelah jam pulang tercatat, Kepala Gudang memeriksa absensi Staff Gudang dan Picker Packer pada gudang penugasannya. Persetujuan atau penolakan tidak mengubah jam asli. Penolakan wajib memiliki alasan dan perbaikan dilakukan melalui Koreksi Absensi. Kepala Gudang tidak dapat menyetujui absensinya sendiri; Owner Approver atau Super Admin yang memeriksanya.

Kepala Gudang dapat mencatat pulang darurat bagi staf jika checklist atau sistem terganggu. Alasan wajib diisi dan tindakan tampil sebagai pengecualian pada audit serta laporan kehadiran.

### 16.3 Pola jadwal mingguan

```guide-flow
attendance-schedule-pattern
```

Kepala Gudang memilih shift atau libur untuk setiap hari Senin sampai Minggu melalui `/attendance/schedules`. Pola berlaku mulai Senin berikutnya dan generator menjaga jadwal 28 hari ke depan. Perubahan manual untuk tanggal tertentu menjadi pengecualian yang tidak ditimpa generator.
