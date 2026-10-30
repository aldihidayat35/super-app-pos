---
name: metronic-blade
description: >-
  Panduan dan standar pengembangan antarmuka Blade Metronic 8 untuk aplikasi GudangToko.
  Gunakan saat membuat atau memodifikasi view Blade, form modal, DataTables, Select2,
  SweetAlert2, komponen Metronic, atau mengatasi masalah halaman render kosong (blank).
---

# Metronic Blade Frontend Skill — GudangToko

Gunakan skill ini saat membuat atau memperbarui tampilan (view) Blade dengan framework tema **Metronic 8** dan **Bootstrap 5** di proyek GudangToko.

---

## 1. Aturan Emas Tampilan Blade

> [!CAUTION]
> 1. **WAJIB `@extends('layouts.metronic.app')`** pada setiap file view halaman. View tanpa `@extends` akan menghasilkan body 0 byte tanpa error log.
> 2. **DILARANG NESTED SLOT DENGAN NAMA YANG SAMA**: Jangan pernah membuat `<x-slot:parts>` di dalam `<x-slot:parts>`. Blade tidak mendukung nested slot dengan nama yang sama dan akan merender halaman menjadi kosong total.

---

## 2. Komponen Blade Resmi

Komponen Blade siap pakai berada di `resources/views/components/metronic/`:

```blade
{{-- Card Kontainer --}}
<x-metronic.card title="Daftar Produk" :toolbar="$toolbarHtml">
    <!-- Konten tabel / data -->
</x-metronic.card>

{{-- Form Group Standar --}}
<x-metronic.form-group label="Nama Produk" name="name" required>
    <input type="text" name="name" class="form-control" value="{{ old('name') }}">
</x-metronic.form-group>

{{-- Status Badge Dinamis --}}
<x-metronic.status-badge :status="$order->status" />

{{-- Header Judul Halaman --}}
<x-metronic.page-title title="Stok Opname" :breadcrumbs="[
    ['title' => 'Gudang', 'url' => route('warehouse.dashboard')],
    ['title' => 'Stok Opname', 'url' => '']
]" />

{{-- Panduan Halaman (Ikon ?) --}}
@section('page_guide')
    <x-metronic.page-guide>
        <x-slot:function>Fungsi halaman ini...</x-slot:function>
        <x-slot:workflow>Alur kerja langkah demi langkah...</x-slot:workflow>
        <x-slot:warnings>Peringatan penting bagi pengguna...</x-slot:warnings>
    </x-metronic.page-guide>
@endsection
```

---

## 3. Format Angka & Mata Uang di View

- Kuantitas stok: Gunakan helper `qty($item->quantity)`.
- Nilai mata uang Rupiah: Gunakan `\App\Support\CurrencyFormatter::rupiah($order->total_amount)`.

---

## 4. Asset & Javascript (Vite)

- Semua plugin pihak ketiga (DataTables, Select2, SweetAlert2, Flatpickr) telah dibundel melalui Vite (`resources/js/vendor.js`).
- ❌ **Dilarang menambahkan tag `<script src="https://cdn...">`** untuk pustaka yang sudah tersedia.
- Untuk inisialisasi script pada halaman tertentu, gunakan stack:
  ```blade
  @push('scripts')
  <script>
      $(function() {
          // Inisialisasi DataTables / Select2
      });
  </script>
  @endpush
  ```

