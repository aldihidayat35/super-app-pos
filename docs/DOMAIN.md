# Domain Model dan Glossary

## 1. Bounded modules

| Modul | Tanggung jawab | Tidak boleh dilakukan |
|---|---|---|
| Identity & Access | user, role, permission, assignment scope | Menentukan aturan stok/harga |
| Organization | warehouse, branch, employee placement | Menyimpan saldo stok |
| Catalog | product, category, unit, conversion, barcode | Mengubah HPP transaksi historis |
| Procurement | supplier, PO, receipt | Mengubah stok tanpa posting inventory |
| Inventory | stock, reservation, mutation, transfer, opname | Menghapus mutation |
| Pricing | HPP snapshot, minimum price, price ring, override | Menyetujui dirinya sendiri |
| Retail | POS sale, shift, closing, retail return | Mengedit transaksi closed |
| B2B | account, catalog, order, fulfillment | Melihat margin internal |
| Billing | invoice, payment, receivable, allocation | Menghapus pembayaran posted |
| Attendance | schedule, attendance, correction | Mengubah shift POS tanpa audit |
| Approval | request, decision, threshold policy | Menjalankan efek stok secara duplikat |
| Audit & Reporting | immutable evidence, projection/report | Menjadi sumber saldo operasional |
| Notification | template, channel, delivery/retry | Menyimpan secret tanpa enkripsi |

## 2. Glossary wajib

| Istilah | Definisi kontraktual |
|---|---|
| Product | Barang yang diperdagangkan, memiliki SKU unik, base unit, status, atribut, dan kebijakan stok/harga. |
| Unit | Satuan dasar atau satuan transaksi. Setiap konversi menuju base unit memiliki faktor decimal dan snapshot pada item transaksi. |
| Warehouse | Lokasi pusat penyimpanan/distribusi yang memiliki area/rack/bin dan saldo sendiri. |
| Branch | Toko internal milik organisasi dengan stok, POS, shift, dan piutang pelanggan toko sendiri. |
| Stock | Saldo teragregasi product pada stock location dan kondisi tertentu; projection dari mutasi yang diposting, bukan catatan manual bebas. |
| Available stock | Kuantitas yang dapat dijanjikan: on hand dikurangi reserved dan blocked/damaged sesuai kebijakan. |
| Reserved stock | Kuantitas yang dialokasikan sementara ke dokumen aktif tetapi belum menjadi barang keluar; wajib dapat dilepas atau dikonsumsi tepat sekali. |
| Mutation | Ledger append-only setiap perubahan stok, lengkap dengan before/after, delta, referensi, lokasi, actor, waktu, dan alasan. |
| HPP | Biaya per base unit menurut metode valuasi yang disepakati, termasuk komponen landed cost yang disetujui. Disnapshot saat transaksi. |
| Minimum price | Harga jual terendah yang diizinkan untuk konteks produk/lokasi/channel pada waktu tertentu. |
| Price ring | Tier/rentang harga jual untuk segmen pelanggan/channel, misalnya Ring 1-3; bukan pengganti minimum price. |
| Purchase Order (PO) | Komitmen pembelian ke supplier; tidak menambah stok sampai receipt diposting. |
| Goods Receipt | Bukti barang supplier yang benar-benar diterima/QC; posting menambah stok dan dapat memperbarui HPP. |
| Transfer | Dokumen perpindahan stok antar stock location/warehouse/branch dengan fase dispatch, in-transit, dan receipt. |
| POS sale | Penjualan retail pada branch dan cash shift aktif, dengan snapshot harga, diskon, HPP, pembayaran, serta status final. |
| Shift | Periode pertanggungjawaban kasir pada branch/register dengan opening cash, transaksi, closing count, dan variance. |
| B2B order | Permintaan pembelian milik B2B account yang melalui validasi stok, harga, kredit, fulfillment, invoice, dan shipment. |
| Invoice | Dokumen tagihan immutable setelah issued, dengan due date, total, paid amount, balance, dan sumber transaksi. |
| Payment | Penerimaan/pengeluaran dana posted yang append-only dan dapat dialokasikan ke satu atau beberapa invoice sesuai kebijakan. |
| Receivable | Hak tagih yang berasal dari invoice kredit; saldo adalah invoice amount dikurangi allocation/reversal/credit note sah. |
| Attendance | Rekaman kehadiran employee terhadap work schedule, termasuk check-in/out, status, evidence, dan correction trail. |
| Approval | Permintaan keputusan untuk aksi sensitif, mencatat requester, approver, policy/threshold, keputusan, alasan, dan expiry. |
| Audit log | Jejak immutable aksi sistem/user yang menjelaskan siapa, apa, kapan, dari mana, dan perubahan apa; bukan pengganti mutation/payment ledger. |

## 3. Nilai dan presisi

- Money: `decimal(18,2)`; tidak menggunakan float.
- Quantity dan conversion: `decimal(18,4)`; aturan pembulatan wajib diputuskan per unit.
- Percentage/rate disimpan decimal dengan skala yang cukup, bukan float.
- Nominal, qty, HPP, tax/discount, dan conversion penting disnapshot pada item dokumen.

## 4. Aggregate dan invariants

| Aggregate root | Child/ledger | Invariant utama |
|---|---|---|
| PurchaseOrder | PurchaseOrderItem | total received tidak melebihi qty ordered kecuali over-receipt policy disetujui |
| GoodsReceipt | GoodsReceiptItem, Mutation | hanya posted receipt mengubah stok; posting idempotent |
| Stock | Reservation, Mutation | tidak negatif; lock saldo sebelum delta |
| StockTransfer | StockTransferItem, Mutation | dispatched/received tidak melebihi approved; lokasi berbeda |
| PosSale | PosSaleItem, Payment | shift aktif; harga valid; complete tepat sekali |
| CashShift | CashMovement, PosSale | hanya satu shift aktif per register/kasir sesuai keputusan |
| B2bOrder | B2bOrderItem, Reservation | reservation tidak melebihi available; scope account sendiri |
| Invoice | Receivable, Allocation | allocated <= payable balance; issued tidak diedit |
| Payment | Allocation | posted/reversed append-only; total allocation konsisten |
| StockOpname | StockOpnameItem, Mutation | adjustment hanya setelah approval yang diwajibkan |
| Approval | ApprovalDecision | requester tidak boleh self-approve bila segregation diwajibkan |

## 5. Ubiquitous language

Gunakan `post`, `approve`, `reject`, `dispatch`, `receive`, `reserve`, `release`, `allocate`, `close`, `void`, dan `reverse` secara konsisten. Hindari method generik `process()` untuk perubahan domain kritis. `Delete` hanya untuk draft/master yang aman; transaksi final memakai `void` atau `reverse`.

## 6. Rekomendasi struktur folder

Struktur tetap mengikuti Laravel dan tidak membutuhkan package modular khusus:

```text
app/
  Domain/
    Identity/
    Catalog/
    Procurement/
    Inventory/
    Pricing/
    Retail/
    B2B/
    Billing/
    Attendance/
    Approval/
    Audit/
    Notification/
    Reporting/
      Actions/
      Data/
      Enums/
      Events/
      Exceptions/
      Models/
      Policies/
      Services/
  Http/
    Controllers/{Admin,Portal}/
    Requests/{Module}/
  Jobs/
  Listeners/
  Providers/
database/
  factories/
  migrations/
  seeders/{Local,Testing}/
resources/views/
  layout-main/
  components/
  admin/{module}/
  portal/{module}/
routes/
  web.php
  admin.php
  portal.php
tests/
  Feature/{Module}/
  Unit/{Module}/
```

Controller memanggil Action/Service; transaksi dan locking berada di boundary use case; event dikirim setelah commit bila efek samping memerlukan data committed.

## 7. Rekomendasi package (belum diinstal)

| Kebutuhan | Kandidat | Catatan keputusan |
|---|---|---|
| RBAC | `spatie/laravel-permission` | Cocok untuk permission granular; location scope tetap custom policy/query scope |
| Audit | `spatie/laravel-activitylog` atau ledger audit internal | Evaluasi masking, volume, dan immutability |
| Excel | `maatwebsite/excel` | Hanya jika format XLSX/import dibutuhkan |
| PDF | `barryvdh/laravel-dompdf` atau renderer eksternal | Pilih setelah template invoice diuji |
| 2FA | Laravel Fortify | Pilih bila owner/super admin wajib 2FA |
| Monitoring | Laravel Horizon (Redis) | Untuk queue production jika Redis tersedia |

Tidak ada package yang dipasang pada tahap analisis ini.

