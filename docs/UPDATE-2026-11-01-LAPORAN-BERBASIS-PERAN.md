# Update Pusat Laporan Berbasis Peran dan Lokasi

Tanggal: 1 November 2026

## Ringkasan

Menu laporan generik diganti dengan pusat laporan khusus untuk Admin User, Kepala Gudang, Kepala Toko, Owner, dan Super Admin. Setiap profil mempunyai permission, dataset, route, folder Blade, identitas visual, dan daftar sublaporan tersendiri.

## Kontrak akses

| Profil | Role | Permission | Scope |
|---|---|---|---|
| Pengguna | `admin_user` | `reports.users.view/export` | Administrasi pengguna. |
| Gudang | `kepala_gudang` | `reports.warehouse.view/export` | Gudang dalam `permittedWorkLocationIds()`. |
| Toko | `kepala_toko` | `reports.retail.view/export` | Toko dalam `permittedWorkLocationIds()`. |
| Owner | `owner_viewer`, `owner_approver` | `reports.owner.view/export` | Seluruh lokasi berizin. |
| Sistem | `super_admin` | `reports.system.view/export` | Sistem dan gateway seluruh laporan bisnis. |

Akses memerlukan role dan permission secara bersamaan. Role purchasing, supervisor shift, staf, kasir, admin config, dan kepala keuangan tidak memperoleh pusat laporan.

## Perilaku sidebar

- Kepala gudang melihat **Laporan Gudang {nama lokasi aktif/default}**.
- Kepala toko melihat **Laporan Toko {nama lokasi aktif/default}**.
- Akun multi-role memperoleh grup yang sesuai; Super Admin hanya memperoleh satu grup sistem terpadu.
- Lokasi request yang tidak diizinkan tidak dipakai dan diganti dengan lokasi sah milik pengguna.
- Item aktif mengikuti sublaporan yang sedang dibuka.

Filter grafik memakai rentang **Bulanan** secara default. Pengguna tetap dapat memilih Harian atau Tahunan dan pilihan tersebut ikut diterapkan pada query serta snapshot ekspor.

## Tampilan

- Pengguna: ungu, direktori akun dan distribusi role.
- Gudang: biru-oranye, fokus kesehatan persediaan dan arus barang.
- Toko: hijau, fokus penjualan, kas, dan kesiapan stok.
- Owner: navy-emas, fokus keputusan eksekutif.
- Super Admin: indigo gelap, fokus audit dan kesehatan sistem.

Setiap sublaporan mempunyai file Blade sendiri di bawah `resources/views/reports/profiles/{profile}`. Komponen bersama hanya digunakan untuk filter, navigasi, KPI, dan tabel.

## Ekspor

Ekspor XLSX, PDF, dan CSV dibuat melalui queue. Record `report_exports` menyimpan tipe `profile.{profile}.{report}`, format, requester, serta snapshot filter lokasi dan periode. Job melakukan otorisasi profil kembali saat membangun file.

## Keamanan data

- Semua lokasi dibatasi di server melalui `permittedWorkLocationIds()`.
- HPP dan margin tetap membutuhkan `margins.view_sensitive`.
- Laporan pengguna tidak memilih password, remember token, session, atau credential.
- Owner tidak dapat membuka laporan audit sistem.
- Super Admin membuka laporan bisnis tanpa impersonasi pengguna lain.
- Kasir dan role non-kepala menerima HTTP 403 ketika membuka URL pusat laporan.

## Verifikasi

- Test profil laporan: 10 test dan 84 assertion lulus, termasuk regresi summary piutang berbentuk array serta default rentang bulanan.
- Regresi laporan dan navigasi: total gabungan 26 test, 248 assertion lulus sebelum penambahan dua skenario view/export terakhir.
- `composer lint`: lulus.
- `composer analyse`: lulus.
- Full `composer test` melampaui timeout Composer 300 detik; sebelum timeout terdapat kegagalan baseline yang tidak terkait pada audit mapping ikon lokal, margin dashboard cabang, serta template import merek/kategori.
