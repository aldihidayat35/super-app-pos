# Panduan Pendataan Stok Lokasi

## Tujuan

Pendataan Stok Lokasi digunakan untuk memasukkan jumlah barang yang benar-benar ada di toko atau gudang berdasarkan produk yang sudah tersedia di database. Fitur ini menjadi alternatif import stok awal menggunakan Excel.

Jumlah yang dimasukkan adalah **saldo akhir hasil hitung fisik**, bukan jumlah tambahan. Jika stok sistem 20 dan fisik 27, sistem mencatat +7. Jika fisik 15, sistem mencatat -5.

## Alur utama

```mermaid
flowchart LR
    SA[Super Admin Mengaktifkan Lokasi] --> PT[Petugas Membuka Pendataan Stok]
    PT --> PR[Pilih Produk dari Database]
    PR --> FS[Isi Jumlah Fisik Sekarang]
    FS --> DF[Simpan Draft]
    DF --> AJ[Ajukan Pendataan]
    AJ -->|Dibuat staf| KP[Persetujuan Kepala Lokasi]
    AJ -->|Dibuat kepala| PS[Posting Sendiri]
    KP --> PS
    PS --> MT[Mutasi Selisih Stok]
    MT --> KS[Kartu Stok dan Audit]
```

## Mengaktifkan lokasi

Super Admin membuka **Administrasi Sistem > Akses Koreksi Stok**, memilih lokasi, lalu mengisi masa aktif dan alasan. Akses terkunci otomatis ketika kedaluwarsa.

```mermaid
flowchart LR
    SA[Super Admin] --> LK[Pilih Lokasi]
    LK --> WT[Isi Masa Aktif dan Alasan]
    WT --> AK[Aktifkan]
    AK --> IP[Lokasi Dapat Mengisi dan Memposting]
    WT --> KD[Kedaluwarsa]
    KD --> TK[Fitur Terkunci]
```

## Staf mencatat stok

1. Buka **Persediaan > Pendataan Stok Lokasi**.
2. Pilih lokasi penugasan dan gunakan dropdown pencarian untuk memilih banyak produk sekaligus.
3. Gunakan **Pilih Semua Hasil Filter** bila seluruh hasil pencarian akan dihitung.
4. Terapkan satu rak ke semua produk atau tentukan rak berbeda per baris.
5. Isi **Jumlah Fisik Sekarang**. Angka 0 digunakan bila produk sudah diperiksa tetapi kosong.
6. Simpan satu draft massal lalu ajukan.

```mermaid
flowchart LR
    ST[Staff Gudang atau Staf Toko] --> CP[Centang Produk]
    CP --> RK[Pilih Rak]
    RK --> JF[Isi Jumlah Fisik]
    JF --> SD[Simpan Draft]
    SD --> AJ[Ajukan]
    AJ --> KP[Menunggu Kepala Lokasi]
```

## Pemeriksaan dan posting

Kepala Gudang memeriksa gudang dan Kepala Toko memeriksa cabang. Kepala dapat membuat dan memposting dokumennya sendiri; sistem mencatat posting mandiri tersebut sebagai audit kritis.

```mermaid
flowchart LR
    KP[Kepala Membuka Detail] --> CK[Periksa Produk Rak dan Jumlah]
    CK -->|Benar| PS[Posting Saldo Akhir]
    CK -->|Tidak Benar| RJ[Tolak dengan Alasan]
    PS --> LK[Kunci Dokumen dan Stok]
    LK --> MT[Mutasi Sebesar Selisih]
    MT --> SL[Dokumen Selesai]
```

Jika stok berubah setelah pengajuan, dokumen kembali menjadi Draft agar jumlah diperiksa dan diajukan ulang.

## Pendataan ulang

Pendataan ulang diperbolehkan dan selalu membuat dokumen baru. Jumlah yang sama dicatat sebagai hasil verifikasi tanpa membuat mutasi kuantitas.

```mermaid
flowchart LR
    UL[Pendataan Ulang] --> SS[Bandingkan Stok Terbaru]
    SS --> BD{Ada Selisih?}
    BD -->|Ya| MT[Mutasi Selisih]
    BD -->|Tidak| VF[Catat Terverifikasi]
    MT --> AU[Riwayat dan Audit]
    VF --> AU
```

## Perbedaan fitur

| Fitur | Kegunaan |
|---|---|
| Pendataan Stok Lokasi | Input kondisi fisik langsung berdasarkan produk database. |
| Import Data Awal | Input massal melalui Excel oleh Super Admin. |
| Koreksi Stok | Memperbaiki selisih operasional. |
| Stok Opname | Pemeriksaan stok berkala. |

HPP dibaca dari master produk dan tidak dapat diubah oleh petugas. Stok darurat tidak termasuk dalam pendataan ini.
