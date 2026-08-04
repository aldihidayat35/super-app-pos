# Dokumentasi Model Produk

## Overview

File ini mendokumentasikan struktur model `Product` dan hubungannya dengan sistem harga & margin di Super App POS.

## Struktur Database (tabel `products`)

| Kolom | Tipe | Deskripsi |
|-------|------|-----------|
| `id` | int | Primary Key, auto increment |
| `name` | varchar(255) | Nama produk |
| `code` | varchar(100) | Kode produk unik |
| `cost_price` | decimal(16,2) | Harga beli / HPP per unit |
| `selling_price` | decimal(16,2) | Harga jual default (opsional) |
| `description` | text | Deskripsi produk |
| `status` | enum('active','inactive') | Status aktif/nonaktif |
| `created_at` | timestamp | Waktu penciptaan |
| `updated_at` | timestamp | Waktu pembaruan |

## Hubungan dengan Tabel Lainnya

### 1. Product Price (tabel `product_prices`)
- Satu produk dapat memiliki banyak harga per saluran dan cabang
- Kolom kunci: `product_id`, `channel`, `branch_id`

### 2. Product Unit (tabel `product_units`)
- Definisi satuan produk (pcs, kg, lusin, dll)
- Kolom kunci: `product_id`, `unit_type`, `unit_factor`

### 3. Price Rule (tabel `price_rules`)
- Aturan margin yang diterapkan pada produk
- Kolom kunci: `channel`, `branch_id`, `customer_id`

### 4. Customer Price Override (tabel `customer_price_overrides`)
- Harga khusus untuk pelanggan tertentu

## Logika Pricing

### Priority Order
1. **Customer Special Price** – harga khusus berdasarkan pelanggan + produk + cabang + tanggal + qty minimum
2. **Product Price by Customer Price Category** – harga produk sesuai kategori pelanggan
3. **Product Price by Branch** – harga produk sesuai cabang
4. **Product Price by Channel/Global** – harga produk secara global
5. **Computed Minimum** – fallback: HPP + margin minimum dari aturan

### Unit Factor System
- Produk bisa memiliki beberapa unit (example: 1 lusin = 12 pcs)
- Logika konversi otomatis saat harga dihitung

## Contoh Penggunaan

```php
use App\Models\Product;

$product = Product::find(1);
HPP = $product->cost_price; // Rp 35,000

// Simulasi margin dengan service
$result = (new PriceResolverService())->resolve(
    $product,
    quantity: 1,
    channel: 'retail',
    branch: $branch,
    customer: $customer
);

echo $result['selected_price']; // Harga terpilih (contoh: Rp 45,000)
echo $result['discounted_price']; // Harga setelah diskon (contoh: Rp 30,000)
echo $result['approval_required'] ? 'Butuh approval' : 'Otomatis';
