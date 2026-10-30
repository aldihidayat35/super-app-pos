---
name: laravel-backend
description: >-
  Panduan dan standar pengembangan backend Laravel 12 untuk aplikasi GudangToko.
  Gunakan saat membuat atau memodifikasi Controller, Service, Action, Model Eloquent,
  FormRequest, Policy, Migration, Enum, atau logika mutasi persediaan dan finansial.
---

# Laravel Backend Skill — GudangToko

Gunakan skill ini saat merancang, menulis, atau merefaktor kode backend di proyek **GudangToko** (`Laravel 12`, PHP 8.3+).

---

## 1. Arsitektur Modul

Kode backend dipisahkan per modul bisnis di dalam `app/`:

| Tipe File | Path Lokasi | Contoh |
|---|---|---|
| **Controller** | `app/Http/Controllers/<Module>/` | `Warehouse/StockOpnameController.php`, `Retail/PosSaleController.php` |
| **Service Layer** | `app/Services/<Module>/` | `Inventory/InventoryService.php`, `Pricing/PriceResolverService.php` |
| **Action Class** | `app/Actions/<Module>/` | `Auth/AuthenticateUserAction.php` |
| **Form Request** | `app/Http/Requests/<Module>/` | `Warehouse/CreateStockOpnameRequest.php` |
| **Domain Enum** | `app/Enums/` | `StockOpnameStatus.php`, `MutationType.php` |
| **Policy** | `app/Policies/` | `StockOpnamePolicy.php` |
| **Support Helper**| `app/Support/` | `CurrencyFormatter.php`, helper global `qty()` |

---

## 2. Invarian Domain Wajib

### A. Presisi Angka & Tipe Data
- ❌ **DILARANG menggunakan `float`** untuk nominal uang, harga, diskon, atau HPP.
- Nominal uang & HPP: `decimal(18,2)`.
- Kuantitas & rasio konversi: `decimal(18,4)`.
- Gunakan helper `qty($value)` atau `App\Support\Decimal` untuk komputasi kuantitas.

### B. Mutasi Persediaan (Inventory Mutex)
- Seluruh mutasi persediaan **WAJIB** melalui `App\Services\Inventory\InventoryService`.
- ❌ **Dilarang keras** memanipulasi kolom kuantitas pada tabel `stocks` secara langsung.
- Mutasi harus dibungkus dalam `DB::transaction()` dengan `lockForUpdate()`.
- Mutasi stok bersifat **append-only** di tabel `stock_mutations` dengan `idempotency_key`.

### C. Isolasi Lokasi Kerja & Branch-Only Support
- Setiap akses data transaksi persediaan harus terisolasi berdasarkan `$request->user()->permittedWorkLocationIds()`.
- **Branch-only Support**: Aplikasi mendukung operasional murni Retail tanpa Gudang Pusat. `WarehouseLocation` (rak/bin) dan penerimaan barang (PO/GR) dapat langsung terikat pada `Branch`. Jangan mengasumsikan keberadaan entitas `Warehouse` secara mutlak.

### D. Immutability Transaksi Final
- Transaksi dengan status final (`COMPLETED`, `POSTED`, `SETTLED`, `CLOSED`) **tidak boleh di-delete atau diedit**.
- Pembatalan transaksi hanya melalui alur resmi: `void` atau `reverse`.

---

## 3. Checklist Kualitas Backend
Sebelum menyelesaikan task backend:
1. Pastikan setiap method memiliki type hinting dan return type yang jelas.
2. Jalankan linter dan static analysis:
   ```bash
   composer lint
   composer analyse
   ```
3. Buat atau perbarui unit/feature test terkait.

