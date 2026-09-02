# Tabel Responsif Mobile

Seluruh tabel aplikasi otomatis memakai enhancer di `resources/js/modules/responsive-mobile-tables.js`. Pada lebar di bawah 768 px setiap baris tampil sebagai kartu ringkas yang dapat dibuka. Pada lebar 768 px ke atas, tabel asli tetap digunakan.

Enhancer memindahkan node isi sel ke pembungkus tampilan, bukan menyalinnya. Karena itu form, tombol, link, pagination, filter, pencarian, sorting, dan sumber data tetap sama. Baris yang ditambahkan DataTables/AJAX diproses otomatis oleh `MutationObserver`.

Saat kartu dibuka, nilai yang sudah tampil pada ringkasan tidak diulang di area detail. Jika satu sel juga memiliki informasi tambahan, hanya bagian ringkasannya yang disembunyikan dan informasi tambahannya tetap tersedia.

## Konfigurasi ringkasan

Tanpa konfigurasi, kolom ringkasan dipilih dari nama header. Untuk konteks khusus, tandai elemen paling relevan di dalam baris:

```html
<span data-mobile-primary>SKU-001</span>
<span data-mobile-secondary>Nama Produk</span>
<span data-mobile-subtitle>Gudang Utama</span>
<span data-mobile-highlight data-mobile-highlight-label="Available">24</span>
```

Alternatif pada level tabel menggunakan nama header atau indeks kolom berbasis nol:

```html
<table
    data-mobile-primary="Produk"
    data-mobile-secondary="Nama"
    data-mobile-subtitle="Lokasi"
    data-mobile-highlight="Available"
>
```

Role juga dapat ditetapkan langsung pada header, misalnya `<th data-mobile-role="primary">Produk</th>`. Satu header dapat memiliki beberapa role yang dipisahkan spasi.

Secara default hanya satu kartu pada tabel yang dapat terbuka. Gunakan `data-mobile-accordion="multiple"` bila suatu tabel memang perlu membuka beberapa kartu sekaligus.

## Pengecualian

Tabel untuk layout cetak, barcode, atau pasangan key/value yang sudah ringkas harus memakai opt-out eksplisit:

```html
<table data-mobile-table="off">
```

Untuk tabel tanpa `<thead>` yang tetap perlu diproses, enhancer membuat label generik `Detail 1`, `Detail 2`, dan seterusnya.

## Refresh manual

Refresh manual biasanya tidak diperlukan. Jika integrasi khusus mengganti isi tabel di luar siklus DOM normal, panggil:

```js
window.GudangTokoResponsiveTables.refresh(document.querySelector('#table-id'));
```

Setiap tombol kartu memakai elemen `<button type="button">`, `aria-expanded`, dan `aria-controls`. Detail tetap dapat dioperasikan dengan keyboard, dan animasi dinonaktifkan ketika pengguna memilih `prefers-reduced-motion`.
