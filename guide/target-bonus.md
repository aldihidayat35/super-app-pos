# Panduan Target dan Bonus Staf

Panduan ini digunakan oleh staf gudang, picker/packer, staf toko, kasir, Sales, kepala lokasi, dan Owner. Buka menu **Target & Bonus** atau URL `/target-bonus`.

## 1. Membuat program dan target

Owner Approver atau Super Admin membuat program untuk bulan yang belum dimulai. Pilih role, lokasi, staf, KPI, target, bobot, dan bonus maksimum. Sales memakai lingkup global; staf toko dan gudang memakai lokasi utama pada master karyawan.

```guide-flow
bonus-program-setup
```

Program disimpan sebagai draft. Setelah diperiksa, tekan **Aktifkan**. Program aktif tidak dapat diubah agar staf mendapat target yang pasti sejak awal bulan. Satu akun hanya boleh menerima satu program pada bulan yang sama.

## 2. Membaca perhitungan bonus

Setiap KPI mempunyai nilai aktual, target, skor, bobot, dan nilai berbobot. Rumusnya adalah:

- skor KPI = aktual dibagi target dikali 100, maksimal 100;
- nilai akhir = jumlah nilai seluruh KPI setelah bobot;
- bonus = bonus maksimum dikali nilai akhir dibagi 100.

```guide-flow
bonus-calculation
```

Penjualan POS mengecualikan transaksi void dan dikurangi retur selesai. Penjualan B2B mengikuti `sales_user_id` pada order selesai dan dikurangi retur B2B yang selesai. Absensi hanya menghitung kehadiran yang sudah diverifikasi. Hari izin, sakit, atau cuti yang disetujui tidak menjadi hari wajib hadir. Checklist hanya memperoleh nilai jika selesai tepat waktu.

## 3. Mengajukan dan menyetujui hasil

Setelah bulan berakhir, tekan **Hitung Ulang**, kemudian **Ajukan**. Sistem menolak pengajuan jika masih ada absensi penerima yang menunggu verifikasi.

```guide-flow
bonus-approval
```

Owner Approver membuka tab **Persetujuan & Pembayaran** atau kotak approval. Penolakan wajib disertai alasan dan membuat periode dapat dihitung serta diajukan kembali. Persetujuan mengunci hasil untuk pembayaran.

## 4. Mencatat pembayaran

Pembayaran hanya dapat dicatat pada hasil yang sudah disetujui. Isi tanggal, metode, dan referensi pembayaran. Bukti dapat dilampirkan bila tersedia.

```guide-flow
bonus-payment
```

Pembayaran tidak membuat pengeluaran shift dan tidak terhubung ke payroll. Sistem menutup periode setelah seluruh penerima dibayar. Pembayaran yang sudah tercatat tidak dapat dihapus atau dicatat dua kali.

## 5. Target Saya, Kinerja Tim, dan Rekap

Staf melihat nilai sendiri pada **Target Saya**. Kepala gudang melihat staff gudang dan picker/packer pada gudangnya. Kepala toko dan Supervisor Shift melihat staf toko dan kasir pada cabangnya. Owner dapat melihat seluruh organisasi dan mengunduh CSV.

```guide-flow
bonus-access-report
```

Gunakan tombol **Rincian** untuk melihat asal nilai setiap KPI. Histori Sales dari sistem sebelumnya ditampilkan sebagai **Skema Lama** dan tidak dihitung ulang.

## 6. Arti informasi pada halaman

| Informasi | Arti |
|---|---|
| Aktual | Hasil nyata yang dibaca dari transaksi aplikasi. |
| Target | Nilai yang ditentukan Owner sebelum bulan dimulai. |
| Skor | Persentase pencapaian KPI, maksimal 100. |
| Bobot | Besar pengaruh KPI terhadap nilai akhir. |
| Bonus maksimum | Batas bonus akun jika nilai akhir mencapai 100. |
| Estimasi bonus | Nilai berjalan yang masih dapat berubah selama bulan aktif. |
| Disetujui | Hasil sudah dikunci oleh Owner dan siap dibayar. |
| Dibayar | Tanggal, metode, dan referensi pembayaran telah tercatat. |

## 7. Pemeriksaan sebelum penutupan

- Pastikan seluruh transaksi final dan retur sudah diproses.
- Pastikan kepala lokasi menyelesaikan verifikasi absensi.
- Pastikan checklist harian sudah terisi dengan status sebenarnya.
- Periksa rincian KPI dan akun penerima sebelum mengajukan.
- Jangan mengubah transaksi sumber hanya untuk menaikkan nilai bonus; seluruh tindakan tersimpan pada audit log.
