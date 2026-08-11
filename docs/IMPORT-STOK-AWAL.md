# Data Excel Stok Awal

Data berikut sesuai dengan stok awal yang diterapkan ke database pada gudang `GDG-01`, rak `GU-ZONA1-RAK1`: 10 unit per produk dengan HPP 0.

> Unduh template **Stok Awal** dari halaman **Import Data Awal**, buka file `.xlsx`, lalu isi sheet pertama mulai dari baris kedua. Simpan tetap dalam format **Excel Workbook (`.xlsx`)** sebelum melakukan preview.

Urutan kolom pada template XLSX wajib tetap: `SKU Produk`, `Kode Gudang/Cabang`, `Kode Lokasi Gudang`, `Jumlah Stok Awal`, `HPP`, dan `Alasan`. Data berikut adalah referensi pengisian; jangan mengunggah blok teks ini secara langsung.

Saat commit berhasil, nilai `HPP` disimpan sebagai HPP aktif produk, nilai persediaan seluruh stok produk dihitung ulang, dan perubahannya dicatat pada Histori HPP dengan sumber **Stok Awal**. File yang sama dapat di-preview dan di-commit ulang untuk mengoreksi HPP meskipun jumlah stok tidak berubah.

```text
SKU Produk,Kode Gudang/Cabang,Kode Lokasi Gudang,Jumlah Stok Awal,HPP,Alasan
PRD-MYK-001,GDG-01,GU-ZONA1-RAK1,10,5000,Saldo awal persediaan
PRD-MYK-002,GDG-01,GU-ZONA1-RAK1,10,10000,Saldo awal persediaan
PRD-MYK-003,GDG-01,GU-ZONA1-RAK1,10,90000,Saldo awal persediaan
PRD-MYK-004,GDG-01,GU-ZONA1-RAK1,10,7000,Saldo awal persediaan
PRD-MYK-005,GDG-01,GU-ZONA1-RAK1,10,70000,Saldo awal persediaan
PRD-MYK-006,GDG-01,GU-ZONA1-RAK1,10,40000,Saldo awal persediaan
PRD-MYK-007,GDG-01,GU-ZONA1-RAK1,10,75000,Saldo awal persediaan
PRD-MYK-008,GDG-01,GU-ZONA1-RAK1,10,7000,Saldo awal persediaan
PRD-MYK-009,GDG-01,GU-ZONA1-RAK1,10,0,Saldo awal persediaan
PRD-MYK-010,GDG-01,GU-ZONA1-RAK1,10,0,Saldo awal persediaan
PRD-NKE-011,GDG-01,GU-ZONA1-RAK1,10,0,Saldo awal persediaan
PRD-NKE-012,GDG-01,GU-ZONA1-RAK1,10,0,Saldo awal persediaan
PRD-NKE-013,GDG-01,GU-ZONA1-RAK1,10,0,Saldo awal persediaan
PRD-NKE-014,GDG-01,GU-ZONA1-RAK1,10,0,Saldo awal persediaan
PRD-NKE-015,GDG-01,GU-ZONA1-RAK1,10,0,Saldo awal persediaan
PRD-NKE-016,GDG-01,GU-ZONA1-RAK1,10,0,Saldo awal persediaan
PRD-NKE-017,GDG-01,GU-ZONA1-RAK1,10,0,Saldo awal persediaan
PRD-NKE-018,GDG-01,GU-ZONA1-RAK1,10,0,Saldo awal persediaan
PRD-NKE-019,GDG-01,GU-ZONA1-RAK1,10,0,Saldo awal persediaan
PRD-NKE-020,GDG-01,GU-ZONA1-RAK1,10,0,Saldo awal persediaan
PRD-RNI-021,GDG-01,GU-ZONA1-RAK1,10,0,Saldo awal persediaan
PRD-RNI-022,GDG-01,GU-ZONA1-RAK1,10,0,Saldo awal persediaan
PRD-RNI-023,GDG-01,GU-ZONA1-RAK1,10,0,Saldo awal persediaan
PRD-RNI-024,GDG-01,GU-ZONA1-RAK1,10,0,Saldo awal persediaan
PRD-RNI-025,GDG-01,GU-ZONA1-RAK1,10,0,Saldo awal persediaan
PRD-RNI-026,GDG-01,GU-ZONA1-RAK1,10,0,Saldo awal persediaan
PRD-RNI-027,GDG-01,GU-ZONA1-RAK1,10,0,Saldo awal persediaan
PRD-RNI-028,GDG-01,GU-ZONA1-RAK1,10,0,Saldo awal persediaan
PRD-RNI-029,GDG-01,GU-ZONA1-RAK1,10,0,Saldo awal persediaan
PRD-RNI-030,GDG-01,GU-ZONA1-RAK1,10,0,Saldo awal persediaan
```
