# Modul Pajak & Kepatuhan

## Tujuan

Modul ini membentuk register pajak yang dapat ditelusuri dari transaksi GudangToko, mendukung rekonsiliasi internal sebelum pelaporan resmi, dan menjaga histori setelah masa pajak dikunci.

## Akses sementara

- `owner_approver`: akses penuh untuk konfigurasi, rekonsiliasi, approval, ekspor, dan penguncian masa.
- `super_admin`: akses penuh sistem.
- Role lain, termasuk `owner_viewer`, `admin_config`, kepala gudang, dan kepala toko, tidak memperoleh akses modul pajak.

## Alur kerja

1. Buat aturan pajak dengan tarif, faktor DPP, dan tanggal efektif.
2. Isi profil pajak perusahaan dan status PKP.
3. Tentukan aturan default serta klasifikasi produk kena pajak.
4. Lengkapi NPWP/NIK dan alamat pajak customer/supplier.
5. Aktifkan kalkulasi. Hanya transaksi POS dan B2B baru yang terpengaruh.
6. Sinkronkan transaksi final ke register masa pajak.
7. Tambahkan faktur masukan atau bukti potong yang belum memiliki sumber internal.
8. Rekonsiliasi dokumen dengan data eksternal/Coretax.
9. Jalankan status `open → reviewed → approved → reported → paid → locked`.
10. Jika ada pembetulan, buka kembali masa terkunci dengan alasan. Semua tindakan dicatat pada audit log.

## Prinsip data

- Tarif tidak di-hardcode pada transaksi; aturan dipilih berdasarkan tanggal efektif.
- Transaksi menyimpan snapshot nilai pajak agar perubahan aturan berikutnya tidak mengubah histori.
- Dokumen final tidak dihapus. Koreksi menggunakan reversal dan referensi ke dokumen asal.
- PPN keluaran bersumber dari invoice B2B dan transaksi POS final.
- Retur POS membentuk pengurang pada register pajak.
- PPN masukan dan PPh dapat dimasukkan manual sampai tersedia sumber transaksi pemasok yang lengkap.
- CSV yang dihasilkan adalah staging rekonsiliasi, bukan bukti bahwa SPT telah terkirim ke Coretax.

## Perintah penerapan

```bash
php artisan migrate
php artisan db:seed --class=RolePermissionSeeder
php artisan optimize:clear
```

Setelah penerapan, buka `/tax/settings` dengan akun owner approver atau super admin.
