# Guide Book Owner

Panduan ini untuk `owner_viewer` dan `owner_approver`. Dalam praktik bisnis, owner melihat kondisi seluruh organisasi, mengambil keputusan strategis, dan menyetujui transaksi sensitif. Owner tidak disarankan melakukan input operasional harian kecuali sebagai tindakan supervisi.

## 1. Tujuan role Owner

Owner memakai aplikasi untuk menjawab pertanyaan bisnis besar:

- Apakah stok cukup dan sehat?
- Apakah penjualan toko dan B2B menguntungkan?
- Apakah HPP, margin, dan harga jual aman?
- Apakah piutang tertagih tepat waktu?
- Apakah ada transaksi janggal?
- Apakah approval tertunda berisiko menghambat operasional?
- Apakah supplier, gudang, toko, dan pelanggan berjalan sesuai target?

Role terkait:

| Role | Fokus |
|---|---|
| `owner_viewer` | Melihat dashboard, laporan, audit, margin sensitif, dan export tanpa approval. |
| `owner_approver` | Semua akses owner viewer ditambah hak approve untuk aksi sensitif. |

## 2. Menu utama Owner

| Menu | URL | Fungsi |
|---|---|---|
| Dashboard Owner | `/owner/dashboard` | Ringkasan omzet, margin, stok, piutang, approval, anomali, dan performa. |
| Laporan Harian Owner | `/reports/daily` | Laporan harian ringkas untuk pengambilan keputusan cepat. |
| Laporan Gudang | `/reports/warehouse` | Kondisi stok, mutasi, stok kritis, transfer, dan nilai persediaan. |
| Laporan Toko | `/reports/retail` | Penjualan POS, closing shift, kas, refund, dan performa cabang. |
| Laporan B2B | `/reports/b2b` | Order pelanggan langganan, invoice, pengiriman, dan status pembayaran. |
| Laporan Harga & Margin | `/reports/pricing` | Analisis margin, HPP, harga minimum, dan harga jual. |
| Laporan Supplier | `/reports/suppliers` | Ketepatan supplier, kualitas penerimaan, tren harga, dan evaluasi. |
| Laporan Piutang | `/reports/receivables` | Aging, overdue, limit kredit, pembayaran, dan saldo tagihan. |
| Pusat Export | `/reports/exports` | Melihat dan mengunduh hasil export laporan. |
| Kotak Masuk Approval | `/approvals` | Menyetujui atau menolak aksi sensitif. |
| Audit Log | `/audit-logs` | Melihat jejak aktivitas pengguna dan perubahan penting. |
| Dashboard Anomali | `/audit/anomalies` | Meninjau alert risiko. |
| Log Login & Keamanan | `/audit/security` | Memantau login, gagal login, dan kejadian keamanan. |
| Invoice | `/invoices` | Melihat invoice dan PDF tagihan. |
| Dashboard Piutang | `/receivables/dashboard` | Ringkasan saldo piutang dan aging. |
| Limit Kredit | `/receivables/credit-limits` | Meninjau atau menyetujui perubahan limit sesuai izin. |

## 3. Alur kerja harian Owner

### 3.1 Buka dashboard owner

1. Login melalui `/login`.
2. Buka `Dashboard Owner`.
3. Periksa KPI utama:
   - omzet hari ini,
   - gross margin,
   - transaksi POS,
   - order B2B,
   - stok kritis,
   - piutang overdue,
   - approval tertunda,
   - anomali.
4. Klik kartu KPI untuk masuk ke laporan detail bila tersedia.

Cara kerja di belakang layar:

- Dashboard mengambil data dari transaksi yang sudah berstatus final atau posted.
- Margin dihitung dari snapshot transaksi, bukan dari harga master terbaru.
- Data dibatasi oleh permission `reports.view` dan `margins.view_sensitive`.
- Export laporan diproses melalui data report export agar file tidak membebani request utama.

### 3.2 Cek approval tertunda

1. Buka `/approvals`.
2. Filter status `pending` atau prioritas tinggi.
3. Buka detail approval.
4. Baca:
   - pemohon,
   - jenis dokumen,
   - nilai risiko,
   - data sebelum dan sesudah,
   - alasan user,
   - dampak stok/uang/harga.
5. Klik `Approve` jika valid atau `Reject` jika tidak valid.
6. Isi alasan keputusan dengan bahasa jelas.

Cara kerja di belakang layar:

- Approval tidak sekadar menyembunyikan tombol. Server memeriksa permission `approvals.approve`.
- Keputusan dicatat ke tabel approval dan audit log.
- Untuk aksi stok/uang, proses final dijalankan dalam transaksi database.
- Pemohon tidak boleh dianggap otomatis berhak approve kecuali policy mengizinkan.

Contoh approval yang perlu perhatian:

- diskon atau harga di bawah minimum,
- perubahan harga sensitif,
- koreksi stok besar,
- void POS,
- retur besar,
- credit note,
- limit kredit,
- pembayaran mencurigakan,
- closing shift dengan selisih besar.

### 3.3 Cek laporan stok dan gudang

1. Buka `/reports/warehouse` atau `/warehouse/stocks`.
2. Filter gudang, cabang, kategori, atau status stok.
3. Periksa stok kosong, stok kritis, reserved stock, damaged stock, dan nilai stok.
4. Untuk produk bermasalah, buka kartu stok `/warehouse/stock-card`.
5. Cocokkan saldo berjalan dengan dokumen asal.

Cara kerja di belakang layar:

- Saldo stok berasal dari tabel `stocks`.
- Detail riwayat berasal dari `stock_mutations` yang append-only.
- Available stock dihitung dari on hand dikurangi reserved dan damaged.
- Mutasi tidak boleh dihapus. Koreksi harus melalui dokumen adjustment, retur, loss, atau reversal.

### 3.4 Cek margin dan harga

1. Buka `/reports/pricing`.
2. Buka juga:
   - `/pricing/product-prices`,
   - `/pricing/rules`,
   - `/pricing/history`,
   - `/pricing/hpp-history`,
   - `/pricing/simulator`.
3. Periksa produk dengan margin rendah, overpricing, atau perubahan HPP tajam.
4. Bila ada request harga, buka `/pricing/approvals`.

Cara kerja di belakang layar:

- Harga jual disimpan per produk, cabang/channel, price ring, dan periode aktif.
- HPP berasal dari penerimaan barang dan histori biaya.
- Transaksi POS/B2B menyimpan snapshot HPP dan harga agar laporan historis stabil.
- Perubahan harga sensitif bisa memicu approval.

### 3.5 Cek piutang

1. Buka `/receivables/dashboard`.
2. Periksa total outstanding, overdue, aging bucket, dan pelanggan risiko tinggi.
3. Buka `/receivables` untuk daftar detail.
4. Buka detail pelanggan untuk histori invoice, pembayaran, dan reminder.
5. Buka `/receivables/credit-limits` jika perlu meninjau limit.

Cara kerja di belakang layar:

- Piutang dibuat dari invoice issued.
- Pembayaran dicatat sebagai payment dan dialokasikan ke invoice/piutang.
- Saldo piutang berasal dari ledger receivable entry, bukan angka manual bebas.
- Credit note atau adjustment harus diaudit dan bisa membutuhkan approval.

### 3.6 Cek audit dan anomali

1. Buka `/audit/anomalies`.
2. Prioritaskan severity tinggi.
3. Buka detail evidence.
4. Jika anomali valid, minta tim terkait memperbaiki dengan dokumen koreksi.
5. Jika false positive, resolve dengan catatan.
6. Buka `/audit-logs` untuk jejak perubahan record.

Cara kerja di belakang layar:

- Audit log menyimpan actor, event, module, before/after, IP/user-agent bila tersedia, dan waktu.
- Anomaly alert dibuat dari aturan risiko, misalnya diskon besar, void, perubahan harga, atau aktivitas login.
- Resolve anomali tidak mengubah transaksi asal; resolve hanya menandai alert sudah ditinjau.

## 4. Panduan membaca status

### 4.1 Purchase Order

| Status | Arti |
|---|---|
| Draft | PO masih disiapkan. |
| Submitted | PO diajukan untuk approval. |
| Approved | PO disetujui dan siap dikirim ke supplier. |
| Sent to Supplier | PO sudah dikirim ke supplier. |
| Partially Received | Sebagian item sudah diterima. |
| Completed | PO selesai diterima. |
| Cancelled | PO dibatalkan sebelum diterima penuh. |

### 4.2 Goods Receipt

| Status | Arti |
|---|---|
| Draft | Penerimaan belum diposting. |
| Posted | Stok dan HPP sudah diperbarui. |
| Corrected/Reversed | Ada koreksi melalui dokumen baru. |

### 4.3 Transfer

| Status | Arti |
|---|---|
| Draft/Pending Approval | Belum mengurangi stok. |
| Approved/Packing | Stok sumber di-reserve. |
| Shipped | Stok sumber keluar/in transit. |
| Partially Received | Sebagian diterima tujuan. |
| Fully Received/Completed | Transfer selesai. |
| Cancelled | Dibatalkan sesuai aturan status. |

### 4.4 POS dan Shift

| Status | Arti |
|---|---|
| Shift Open | Kasir boleh transaksi. |
| Closing Submitted | Kasir sudah submit closing; menunggu supervisor. |
| Approved/Closed | Closing terkunci. |
| Rejected | Closing perlu diperbaiki sesuai catatan. |

## 5. Hal yang tidak boleh dilakukan Owner

- Jangan menyuruh tim mengubah saldo stok langsung di database.
- Jangan approve transaksi tanpa membaca alasan dan dampaknya.
- Jangan memakai akun Super Admin untuk pekerjaan owner harian.
- Jangan menghapus transaksi final untuk "merapikan data".
- Jangan menjalankan seeder demo di production.
- Jangan mengabaikan piutang overdue yang tetap diberi order baru tanpa approval jelas.

## 6. Checklist harian Owner

- [ ] Dashboard owner sudah diperiksa.
- [ ] Approval pending diputuskan atau didelegasikan.
- [ ] Stok kritis/kosong ditindaklanjuti.
- [ ] Margin rendah dan harga tidak wajar ditinjau.
- [ ] Piutang overdue dipantau.
- [ ] Anomali high severity ditinjau.
- [ ] Export/laporan penting sudah diunduh bila diperlukan.

## 7. Checklist mingguan Owner

- [ ] Evaluasi performa supplier.
- [ ] Evaluasi performa cabang.
- [ ] Evaluasi produk slow moving dan fast moving.
- [ ] Evaluasi loss, retur, void, dan koreksi stok.
- [ ] Evaluasi limit kredit pelanggan B2B.
- [ ] Review backup dan health system bersama Super Admin.
