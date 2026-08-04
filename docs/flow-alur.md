# Dokumentasi Alur Perhitungan Margin

## Overview

File ini mendokumentasikan alur perhitungan margin di aplikasi Super App POS, khususnya pada fitur **Simulasi Margin**.

## File Utama

| File | Deskripsi |
|------|-----------|
| `app/Services/Pricing/PriceResolverService.php` | Layanan utama yang melakukan logika perhitungan margin |
| `resources/views/pricing/simulator/simulator.blade.php` | Tampilan form input dan hasil simulasi |
| `resources/views/pricing/simulator/partials/flow-card.blade.php` | Komponen visual alur perhitungan (step-by-step) |

## Alur Perhitungan (7 Langkah)

### 1. Input Validation
- Memeriksa parameter input: produk, kuantitas, cabang, pelanggan, channel, discount, harga uji
- Normalisasi kuantitas dan konversi unit

### 2. HPP Calculation
- Mengambil `cost_price` dari model Product
- Menghitung HPP per unit sesuai konversi unit

### 3. Margin Rule Selection
- Mencari aturan margin aktif berdasarkan: channel, cabang, pelanggan, waktu
- Urutan prioritas aturan:
  1. Aturan pelanggan khusus (customer special price)
  2. Aturan produk sesuai price category
  3. Aturan produk sesuai cabang
  4. Aturan produk global/channel
  5. Fallback dari HPP + aturan margin minimum

### 4. Candidate Selection
- Mengumpulkan semua kemungkinan harga berdasarkan aturan yang ditemukan
- Mengurutkan kandidats berdasarkan prioritas (kecil → besar)
- Memilih kandidat dengan prioritas tertinggi (kecil)

### 5. Discount Application
- Menerapkan diskon persentase dari hasil perhitungan
- Memeriksa apakah diskon melebihi maksimum yang diizinkan

### 6. Validation
- Memeriksa apakah harga di bawah minimum
- Memeriksa overpricing (di atas maksimum)
- Memeriksa apakah diskon terlalu tinggi
- Menentukan apakah approval diperlukan

### 7. Final Result
- Menampilkan harga jual final ke pelanggan
- Menampilkan margin (jumlah dan persentase)
- MenStatusLabel approval (Approved / Memerlukan Persetujuan)

## Data Struktur Keluaran

`PriceResolverService::resolve()` mengembalikan array dengan kunci:

```php
[
    'product_id' => int,
    'hpp_base' => float,
    'hpp_unit' => float,
    'unit_factor' => float,
    'quantity_base' => float,
    'minimum_price' => float,
    'maximum_price' => float,
    'selected_price' => float,
    'discounted_price' => float,
    'margin_amount' => float,
    'margin_percent' => float,
    'margin_method' => string,
    'margin_percent_value' => float,
    'margin_amount_value' => float,
    'computed_minimum' => float,
    'candidates' => array,
    'selected_source' => string,
    'reason' => string,
    'approval_required' => bool,
    'approval_reasons' => array,
    'can_view_sensitive_margin' => bool,
    'rule_id' => int,
    'rule_name' => string,
    'rule_priority' => int,
    'max_discount_percent' => float,
    'overpricing_tolerance_percent' => float,
]
```

## Logika Determinasi Approval

Approval diperlukan jika salah satu dari kondisi berikut terpenuhi:
- `discounted_price < minimum_price` (di bawah minimum)
- `selected_price > maximum_price` (overpricing)
- `discount_percent > max_discount_percent` (diskon terlalu tinggi)

## Visual Flow Diagram

```
Input (Produk, Qty, Cabang, Pelanggan, Channel)
         │
         ▼
Validasi Input
         │
         ▼
HPP Calculation (cost_price × unit_factor)
         │
         ▼
Margin Rule Selection (cari aturan aktif)
         │
         ▼
Candidate Selection (kumpulkan semua harga layak)
         │
         ▼
Discount Application (terapkan diskon %)
         │
         ▼
Validation (periksa minimum, maksimum, diskon)
         │
         ▼
Result Final (harga, margin, status approval)
