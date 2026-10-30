---
name: domain-compliance
description: >-
  Daftar periksa (checklist) dan panduan audit kepatuhan aturan bisnis serta keamanan GudangToko.
  Gunakan saat meninjau (review) PR/fitur, mengaudit logika mutasi, memeriksa permission RBAC,
  atau memvalidasi snapshot transaksi dan perlindungan margin.
---

# Domain & Business Compliance Skill — GudangToko

Gunakan skill ini sebagai panduan audit domain sebelum suatu fitur atau perubahan kode di-merge ke sistem GudangToko. Aturan lengkap mengacu pada [.agents/rules/business-flows.md](file:///c:/laragon/www/super-app-pos/.agents/rules/business-flows.md) dan [docs/DOMAIN.md](file:///c:/laragon/www/super-app-pos/docs/DOMAIN.md).

---

## Checklist Audit Domain

### 1. Isolasi Lokasi Kerja & Otorisasi
- [ ] Apakah query data persediaan dan transaksi membatasi akses sesuai `$request->user()->permittedWorkLocationIds()`?
- [ ] Apakah fitur mendukung operasional **Branch-only** tanpa asumsi bahwa entitas `Warehouse` harus selalu ada?

### 2. Integritas Saldo Persediaan
- [ ] Apakah perhitungan saldo memenuhi: `available = on_hand - reserved - damaged`?
- [ ] Apakah dicegah saldo bernilai negatif (`available < 0`) sebelum mutasi dilakukan?
- [ ] Apakah semua mutasi stok dieksekusi melalui `InventoryService` (bukan update manual ke tabel `stocks`)?
- [ ] Apakah mutasi dibungkus dalam `DB::transaction()` dengan `lockForUpdate()`?

### 3. Ledger Mutasi Append-Only
- [ ] Apakah setiap pergerakan stok menghasilkan rekaman baru di `stock_mutations` dengan `idempotency_key` unik?
- [ ] Tidak ada kode yang melakukan `update` atau `delete` pada tabel `stock_mutations`.

### 4. Presisi Finansial & Kuantitas
- [ ] Nominal uang, HPP, harga jual: `decimal(18,2)`. **Tidak ada `float`**.
- [ ] Kuantitas stok dan rasio konversi satuan: `decimal(18,4)`.

### 5. Immutability Dokumen Final
- [ ] Transaksi final (`COMPLETED`, `POSTED`, `SETTLED`, dll.) tidak memiliki tombol / aksi `delete` atau `update` langsung.
- [ ] Pembatalan dilakukan via dokumen pembalik: `void` atau `reverse`.

### 6. Snapshot Transaksi
- [ ] Dokumen transaksi (penjualan, pembelian, transfer) menyimpan snapshot:
  - `product_name_snapshot`
  - `product_sku_snapshot`
  - `unit_name_snapshot`
  - `conversion_factor_snapshot`
  - `hpp_snapshot` / `unit_cost_snapshot`
  - `price_snapshot`

### 7. Proteksi Margin & Keamanan
- [ ] Nilai HPP dan persentase margin hanya dapat dilihat oleh user dengan permission `margins.view_sensitive`.
- [ ] Staf kasir, toko, dan pelanggan B2B tidak dapat melihat margin internal.

