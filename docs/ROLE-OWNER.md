buat lebih 
  menarik tapi tetap menggunakan skin metronic 8 ,tambahkan# Dokumentasi Role Owner — Cara Kerja & Fitur

**Versi:** 1.0  
**Tanggal:** 2026-08-11  
**Status:** Aktif

---

## 1. Ringkasan Role Owner

Role Owner adalah level akses tertinggi kedua setelah Super Admin dalam sistem Super App POS. Role ini dirancang untuk pemilik bisnis atau manajemen puncak yang memerlukan pandangan holistik terhadap seluruh operasional — dari stok, penjualan, margin, piutang, hingga audit keamanan.

### 1.1 Sub-Role Owner

Sistem membedakan dua sub-role Owner berdasarkan tingkat akses approval:

| Sub-Role | Deskripsi | Hak Approval |
|----------|-----------|--------------|
| `owner_viewer` | Read-only — dapat melihat dashboard, laporan, audit, dan export tanpa hak menyetujui transaksi sensitif. | ❌ Tidak |
| `owner_approver` | Full access — semua akses owner_viewer ditambah hak approve untuk aksi-aksi sensitif (diskon, koreksi stok, void, dll). | ✅ Ya |

### 1.2 Perbedaan dengan Role Lain

| Aspek | Owner Viewer | Owner Approver | Super Admin | Admin Config |
|-------|--------------|----------------|-------------|--------------|
| Melihat dashboard & laporan | ✅ | ✅ | ✅ | ✅ |
| Mengecek audit & anomali | ✅ | ✅ | ✅ | ✅ |
| Approve transaksi sensitif | ❌ | ✅ | ✅ | ❌ |
| Mengatur sistem & konfigurasi | ❌ | ❌ | ✅ | ✅ |
| Unrestricted location scope | ✅ | ✅ | ✅ | ✅ |

---

## 2. Fitur & Menu yang Dapat Diakses

### 2.1 Dashboard Owner (`/owner/dashboard`)

Dashboard utama yang menampilkan ringkasan KPI bisnis secara real-time:

- **Omzet Hari Ini** — total penjualan (POS + B2B) hari ini
- **Gross Margin** — persentase margin kotor dari snapshot transaksi
- **Transaksi POS** — jumlah transaksi hari ini
- **Order B2B** — order aktif pelanggan langganan
- **Stok Kritis** — produk dengan stok di bawah threshold
- **Piutang Overdue** — tagihan yang jatuh tempo
- **Approval Tertunda** — jumlah approval yang menunggu
- **Anomali** — alert risiko yang belum ditinjau

**Cara kerja backend:**
- Data diambil dari transaksi berstatus final/posted
- Margin dihitung dari snapshot transaksi (bukan harga master terbaru)
- Data dibatasi oleh permission `reports.view` dan `margins.view_sensitive`
- Export laporan diproses async agar tidak membebani request utama

### 2.2 Laporan Harian Owner (`/reports/daily`)

Laporan ringkas harian untuk pengambilan keputusan cepat. Mencakup:
- Ringkasan penjualan hari ini vs kemarin
- Top produk terlaris
- Stok yang perlu restock
- Piutang yang jatuh tempo

### 2.3 Laporan Gudang (`/reports/warehouse`)

Kondisi stok seluruh gudang dan cabang:
- Saldo stok per lokasi
- Mutasi stok (masuk/keluar)
- Stok kritis dan kosong
- Transfer antar lokasi
- Nilai persediaan total

### 2.4 Laporan Toko (`/reports/retail`)

Performa penjualan POS di seluruh cabang:
- Penjualan per cabang
- Closing shift & selisih kas
- Refund & void transaksi
- Performa kasir per shift

### 2.5 Laporan B2B (`/reports/b2b`)

Order pelanggan langganan dan fulfillment:
- Order aktif dan riwayat
- Invoice & status pembayaran
- Pengiriman & resi

### 2.6 Laporan Harga & Margin (`/reports/pricing`)

Analisis margin dan pengaturan harga:
- Produk dengan margin rendah
- Overpricing detection
- Perubahan HPP tajam
- Simulasi margin

### 2.7 Laporan Supplier (`/reports/suppliers`)

Evaluasi performa supplier:
- Ketepatan pengiriman
- Kualitas penerimaan barang
- Tren harga pembelian
- Skor performa supplier

### 2.8 Laporan Piutang (`/reports/receivables`)

Manajemen piutang pelanggan:
- Aging piutang
- Pelanggan overdue
- Limit kredit
- Saldo tagihan

### 2.9 Pusat Export (`/reports/exports`)

Menampilkan dan mengunduh hasil export laporan:
- Riwayat export
- Status processing
- Unduh file Excel/PDF

### 2.10 Kotak Masuk Approval (`/approvals`)

Hanya untuk `owner_approver`. Halaman untuk meninjau dan memutuskan approval:
- Filter berdasarkan status (pending/high priority)
- Detail approval: pemohon, jenis dokumen, nilai risiko, before/after data
- Tombol Approve/Reject dengan alasan
- Approval dicatat ke audit log

**Contoh transaksi yang butuh approval:**
- Diskon atau harga di bawah minimum
- Perubahan harga sensitif
- Koreksi stok besar
- Void POS
- Retur besar
- Credit note
- Limit kredit
- Pembayaran mencurigakan
- Closing shift dengan selisih besar

### 2.11 Audit Log (`/audit-logs`)

Jejak aktivitas pengguna dan perubahan penting:
- Actor (siapa yang melakukan)
- Event (tipe aksi)
- Module (modul yang diubah)
- Before/After data
- IP address & user agent
- Timestamp

### 2.12 Dashboard Anomali (`/audit/anomalies`)

Meninjau alert risiko otomatis:
- Prioritas berdasarkan severity (high/medium/low)
- Evidence detail
- Resolve anomali (false positive)
- Request koreksi untuk anomali valid

**Sumber anomali:**
- Diskon besar
- Void transaksi
- Perubahan harga tidak wajar
- Aktivitas login mencurigakan
- Stok opname selisih besar

### 2.13 Log Login & Keamanan (`/audit/security`)

Pemantauan keamanan akses:
- Login berhasil/gagal
- Pola login mencurigakan
- Session management
- Security events

### 2.14 Invoice (`/invoices`)

Melihat invoice dan PDF tagihan:
- Riwayat invoice
- Status pembayaran
- Download PDF

### 2.15 Dashboard Piutang (`/receivables/dashboard`)

Ringkasan saldo piutang dan aging bucket.

### 2.16 Limit Kredit (`/receivables/credit-limits`)

Meninjau atau menyetujui perubahan limit kredit pelanggan B2B.

---

## 3. Alur Kerja Harian Owner

### 3.1 Langkah 1: Buka Dashboard Owner

1. Login melalui `/login`
2. Sistem otomatis mengarahkan ke Dashboard Owner
3. Periksa KPI utama di kartu-kartu dashboard
4. Klik kartu KPI untuk masuk ke laporan detail

### 3.2 Langkah 2: Cek Approval Tertunda

1. Buka `/approvals` (khusus `owner_approver`)
2. Filter status `pending` atau prioritas tinggi
3. Buka detail approval
4. Baca informasi lengkap:
   - Pemohon
   - Jenis dokumen
   - Nilai risiko
   - Data sebelum & sesudah
   - Alasan user
   - Dampak stok/uang/harga
5. Klik `Approve` jika valid atau `Reject` jika tidak valid
6. Isi alasan keputusan dengan bahasa jelas

**Catatan:** Approval tidak hanya menyembunyikan tombol. Server memeriksa permission `approvals.approve`. Keputusan dicatat ke tabel approval dan audit log.

### 3.3 Langkah 3: Cek Laporan Stok & Gudang

1. Buka `/reports/warehouse` atau `/warehouse/stocks`
2. Filter berdasarkan gudang, cabang, kategori, atau status stok
3. Periksa:
   - Stok kosong
   - Stok kritis
   - Reserved stock
   - Damaged stock
   - Nilai stok total
4. Untuk produk bermasalah, buka kartu stok `/warehouse/stock-card`
5. Cocokkan saldo berjalan dengan dokumen asal

### 3.4 Langkah 4: Cek Margin & Harga

1. Buka `/reports/pricing`
2. Tinjau juga halaman terkait:
   - `/pricing/product-prices` — harga jual per produk/cabang
   - `/pricing/rules` — aturan harga
   - `/pricing/history` — histori perubahan harga
   - `/pricing/hpp-history` — histori HPP
   - `/pricing/simulator` — simulasi margin
3. Identifikasi produk dengan:
   - Margin rendah
   - Overpricing
   - Perubahan HPP tajam
4. Bila ada request harga, buka `/pricing/approvals`

### 3.5 Langkah 5: Cek Piutang

1. Buka `/receivables/dashboard`
2. Periksa:
   - Total outstanding
   - Overdue
   - Aging bucket
   - Pelanggan risiko tinggi
3. Buka `/receivables` untuk daftar detail
4. Buka detail pelanggan untuk histori invoice, pembayaran, dan reminder
5. Buka `/receivables/credit-limits` untuk peninjauan limit

### 3.6 Langkah 6: Cek Audit & Anomali

1. Buka `/audit/anomalies`
2. Prioritaskan severity tinggi
3. Buka detail evidence
4. Jika anomali valid → minta tim terkait memperbaiki dengan dokumen koreksi
5. Jika false positive → resolve dengan catatan
6. Buka `/audit-logs` untuk jejak perubahan record

---

## 4. Sistem Permission & RBAC

### 4.1 Permission untuk Owner Viewer

```php
[
    '*.view',              // View semua modul
    'audit.view',          // Akses audit log
    'audit.export',        // Export audit
    'reports.export',      // Export laporan
    'notifications.view',  // Lihat notifikasi
    'margins.view_sensitive', // Lihat margin sensitif
]
```

### 4.2 Permission untuk Owner Approver

```php
[
    '*.view',                    // View semua modul
    '*.approve',                 // Approve semua aksi sensitif
    'audit.*',                   // Full akses audit
    'reports.export',            // Export laporan
    'notifications.view',        // Lihat notifikasi
    'notifications.send',        // Kirim notifikasi
    'margins.view_sensitive',    // Lihat margin sensitif
]
```

### 4.3 Unrestricted Location Scope

User dengan role `owner_viewer` atau `owner_approver` memiliki akses ke **semua lokasi** (warehouse, branch) tanpa batasan. Ini diatur di `app/Models/User.php`:

```php
public function hasUnrestrictedLocationScope(): bool
{
    return $this->hasAnyRole(['super_admin', 'owner_viewer', 'owner_approver', 'admin_config']);
}
```

### 4.4 Routing & Middleware

Contoh route protection untuk owner:

```php
// Dashboard Owner
Route::get('/owner/dashboard', OwnerDashboardController::class)
    ->middleware('permission:reports.view')
    ->name('owner.dashboard');

// Approvals (hanya approver)
Route::get('/approvals', ...)
    ->middleware('permission:approvals.view')
    ->name('approvals.index');
```

---

## 5. Panduan Membaca Status Dokumen

### 5.1 Purchase Order (PO)

| Status | Arti |
|--------|------|
| Draft | PO masih disiapkan |
| Submitted | PO diajukan untuk approval |
| Approved | PO disetujui, siap dikirim ke supplier |
| Sent to Supplier | PO sudah dikirim ke supplier |
| Partially Received | Sebagian item sudah diterima |
| Completed | PO selesai diterima |
| Cancelled | PO dibatalkan sebelum diterima penuh |

### 5.2 Goods Receipt

| Status | Arti |
|--------|------|
| Draft | Penerimaan belum diposting |
| Posted | Stok dan HPP sudah diperbarui |
| Corrected/Reversed | Ada koreksi melalui dokumen baru |

### 5.3 Transfer Stok

| Status | Arti |
|--------|------|
| Draft/Pending Approval | Belum mengurangi stok |
| Approved/Packing | Stok sumber di-reserve |
| Shipped | Stok sumber keluar / in transit |
| Partially Received | Sebagian diterima tujuan |
| Fully Received/Completed | Transfer selesai |
| Cancelled | Dibatalkan sesuai aturan status |

### 5.4 POS & Shift

| Status | Arti |
|--------|------|
| Shift Open | Kasir boleh transaksi |
| Closing Submitted | Kasir submit closing; menunggu supervisor |
| Approved/Closed | Closing terkunci |
| Rejected | Closing perlu diperbaiki |

---

## 6. Checklist Harian Owner

- [ ] Dashboard owner sudah diperiksa
- [ ] Approval pending diputuskan atau didelegasikan
- [ ] Stok kritis/kosong ditindaklanjuti
- [ ] Margin rendah dan harga tidak wajar ditinjau
- [ ] Piutang overdue dipantau
- [ ] Anomali high severity ditinjau
- [ ] Export/laporan penting sudah diunduh bila diperlukan

---

## 7. Checklist Mingguan Owner

- [ ] Evaluasi performa supplier
- [ ] Evaluasi performa cabang
- [ ] Evaluasi produk slow moving dan fast moving
- [ ] Evaluasi loss, retur, void, dan koreksi stok
- [ ] Evaluasi limit kredit pelanggan B2B
- [ ] Review backup dan health system bersama Super Admin

---

## 8. Hal yang Tidak Boleh Dilakukan Owner

1. **Jangan** menyuruh tim mengubah saldo stok langsung di database
2. **Jangan** approve transaksi tanpa membaca alasan dan dampaknya
3. **Jangan** memakai akun Super Admin untuk pekerjaan owner harian
4. **Jangan** menghapus transaksi final untuk "merapikan data"
5. **Jangan** menjalankan seeder demo di production
6. **Jangan** mengabaikan piutang overdue yang tetap diberi order baru tanpa approval jelas

---

## 9. Catatan Teknis untuk Developer

### 9.1 Cara Kerja Margin

Margin dihitung dari **snapshot transaksi**, bukan dari harga master terbaru. Ini memastikan laporan historis tetap stabil meskipun harga berubah.

```php
// app/Services/Reports/ReportMetricService.php
// Margin = (Harga Jual - HPP Snapshot) / Harga Jual
```

### 9.2 Stok & Mutasi

- Saldo stok berasal dari tabel `stocks`
- Detail riwayat dari `stock_mutations` yang **append-only**
- Available stock = on hand - reserved - damaged
- Mutasi tidak boleh dihapus; koreksi harus melalui dokumen adjustment/retur/loss/reversal

### 9.3 Piutang & Ledger

- Piutang dibuat dari invoice issued
- Pembayaran dicatat sebagai payment dan dialokasikan ke invoice/piutang
- Saldo piutang berasal dari ledger receivable entry, bukan angka manual bebas
- Credit note atau adjustment harus diaudit dan bisa membutuhkan approval

### 9.4 Approval Flow

1. User melakukan aksi sensitif → sistem membuat record approval
2. Owner approver menerima notifikasi
3. Owner meninjau detail approval
4. Owner Approve/Reject dengan alasan
5. Sistem menjalankan aksi final dalam transaksi database
6. Audit log mencatat keputusan

---

## 10. Akun Demo untuk Testing

| Role | Username | Email | Password |
|------|----------|-------|----------|
| Owner Approver | `owner` | `owner@gudangtoko.test` | `password` |
| Owner Viewer | `owner_viewer` | `owner_viewer@gudangtoko.test` | `password` |

---

**Dokumen ini dibuat berdasarkan:**
- `config/rbac.php` — konfigurasi role & permission
- `app/Models/User.php` — logic unrestricted location scope
- `config/navigation.php` — struktur menu berdasarkan permission
- `guide/owner.md` — panduan pengguna asli
- `app/Http/Controllers/Reports/OwnerDashboardController.php` — implementasi dashboard
