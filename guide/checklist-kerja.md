# Panduan Checklist Kerja

## 1. Tujuan

Menu **Checklist Kerja** membantu setiap akun internal membuktikan pemeriksaan kerja harian atau mingguan. Isi checklist mengikuti role dan lokasi kerja yang aktif pada awal periode. Riwayat disimpan sebagai snapshot sehingga perubahan role atau template tidak mengubah hasil lama.

URL: `/checklist-kerja`

```guide-flow
checklist-generation
```

## 2. Checklist Saya

Tab **Checklist Saya** menampilkan tugas periode aktif. Checklist harian berakhir pukul 23:59 WIB pada hari yang sama. Checklist mingguan berjalan dari Senin sampai Minggu. Akun yang mempunyai beberapa role menerima gabungan poin; poin dengan kode yang sama hanya tampil satu kali.

Cara mengisi:

1. Baca uraian dan asal role setiap poin.
2. Pilih **Selesai**, **Terkendala**, atau **Tidak Berlaku**.
3. Isi catatan untuk status Terkendala dan Tidak Berlaku.
4. Tekan **Simpan** pada poin tersebut.
5. Setelah semua poin direspons, tekan **Selesaikan Checklist**. Jika Anda masih memiliki absensi aktif pada lokasi dan tanggal yang sama, tombol menjadi **Selesaikan Checklist & Absen Pulang**.

Penyelesaian checklist dan pencatatan jam pulang diproses dalam satu transaksi. Jika salah satunya gagal, keduanya dibatalkan agar status checklist dan absensi tidak berbeda. Jika checklist sudah selesai tetapi jam pulang belum tercatat, tombol **Absen Pulang** tetap tersedia. Kasir harus menutup shift kas lebih dahulu.

Checklist yang diselesaikan setelah tenggat tetap diterima dan ditandai **Terlambat**.

```guide-flow
checklist-fill
```

## 3. Koreksi jawaban

Checklist selesai dikunci agar jawaban tidak berubah tanpa disengaja. Jika ada kesalahan, isi alasan pada bagian **Koreksi**, lalu buka kembali checklist. Waktu penyelesaian pertama tetap disimpan untuk menentukan ketepatan waktu dan alasan koreksi masuk ke audit.

```guide-flow
checklist-fill
```

## 4. Riwayat pribadi

Tab **Riwayat** dapat digunakan semua akun internal. Gunakan filter tanggal, frekuensi, status, dan lokasi. Tombol **Detail** menampilkan jawaban, catatan, serta waktu respons tanpa berpindah halaman.

```guide-flow
checklist-recap
```

## 5. Rekap Tim

Tab ini tersedia sesuai kewenangan:

- Kepala Gudang melihat Staff Gudang dan Picker Packer pada gudang penugasannya.
- Kepala Toko dan Supervisor Shift melihat Staf Toko serta Kasir pada cabang penugasannya.
- Owner dan Super Admin melihat seluruh lokasi.

Indikator rekap membedakan tingkat pengisian dari tingkat penyelesaian. Status Terkendala tidak dihitung sebagai pekerjaan selesai, sedangkan Tidak Berlaku dikeluarkan dari pembagi tingkat penyelesaian. Hasil filter dapat diunduh sebagai CSV.

```guide-flow
checklist-recap
```

## 6. Pengaturan Template

Super Admin dan Admin Config dapat membuat template atau menjadwalkan versi baru. Tentukan kode, nama, frekuensi, lingkup global/lokasi, jenis lokasi, role, tanggal mulai berlaku, dan daftar poin. Gunakan `item_key` yang sama jika dua role memiliki pekerjaan identik dan harus digabung.

Perubahan berlaku pada periode berikutnya. Menonaktifkan template menghentikan pembentukan checklist baru setelah periode berjalan selesai.

```guide-flow
checklist-template
```

## 7. Arti status

| Status | Arti |
|---|---|
| Belum Diisi | Poin belum diberi jawaban dan menahan penyelesaian checklist. |
| Selesai | Pekerjaan atau pemeriksaan sudah dilakukan. |
| Terkendala | Pekerjaan belum selesai karena kendala yang dijelaskan pada catatan. |
| Tidak Berlaku | Poin tidak relevan untuk kondisi hari atau lokasi tersebut dan wajib dijelaskan. |

## 8. Hal yang harus diperhatikan

- Gunakan akun sendiri karena seluruh hasil terikat ke akun yang login.
- Pastikan penugasan lokasi sudah benar sebelum periode dibuat.
- Jangan memakai Tidak Berlaku untuk melewati pekerjaan yang sebenarnya wajib.
- Jelaskan kendala secara operasional agar atasan dapat menindaklanjuti.
- Template lama dan checklist selesai tidak dihapus karena menjadi bagian riwayat audit.
