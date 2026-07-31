---
description: "Use when menerima task dari user di proyek Laravel GudangToko. Menegakkan pembuatan todo list, eksekusi todo secara berurutan, dan update status todo secara real-time (in_progress → completed) setiap kali todo selesai."
name: "Todo Workflow Real-Time"
applyTo: "**"
---

# Workflow Todo Real-Time

Aturan ini memastikan setiap task dari user dipecah menjadi todo yang jelas dan status todo di-update secara real-time, bukan hanya dicatat di awal lalu dilupakan.

## 1. Wajib buat todo list di awal task

Setiap kali user memberikan task yang terdiri dari **2 langkah atau lebih**, agent **harus** langsung membuat todo list menggunakan tool `manage_todo_list` sebelum mulai bekerja.

### Kapan wajib buat todo

- ✅ Task yang jelas-jelas multi-langkah (mis. "buat seeder, jalankan, verifikasi").
- ✅ Task yang menyentuh lebih dari satu file atau satu modul.
- ✅ Task yang butuh testing bertahap.
- ✅ Task besar yang dipecah oleh user sendiri (bullet/nomor di chat user).

### Kapan boleh skip

- ⚠️ Task satu langkah trivial (mis. "apa itu RouteServiceProvider?").
- ⚠️ Pertanyaan informatif tanpa implikasi perubahan kode.
- ⚠️ Koreksi minor satu baris.

## 2. Format todo list standar

Gunakan struktur 3 field wajib:

| Field | Wajib | Keterangan |
|---|---|---|
| `id` | ✅ | Nomor urut mulai dari 1. |
| `status` | ✅ | Salah satu: `not-started`, `in-progress`, `completed`. |
| `step` (content) | ✅ | Deskripsi todo yang actionable, singkat, dan jelas. |

### Contoh todo list untuk task "Buat WarehouseSeeder dan verifikasi"

```
[
  { "id": 1, "status": "in-progress",  "content": "Buat WarehouseSeeder dengan data lengkap" },
  { "id": 2, "status": "not-started", "content": "Jalankan seeder dan verifikasi via tinker/sqlite3" },
  { "id": 3, "status": "not-started", "content": "Verifikasi data muncul di dashboard" }
]
```

## 3. Aturan status real-time

### Hanya satu todo yang boleh `in-progress`

- ⛔ Dilarang ada 2 todo `in-progress` secara bersamaan.
- ✅ Saat todo ke-1 selesai dan todo ke-2 akan dimulai, **update dulu** todo ke-1 ke `completed` dan todo ke-2 ke `in-progress` dalam satu panggilan `manage_todo_list`.

### Update status langsung di setiap milestone

| Milestone | Update yang wajib |
|---|---|
| Mulai kerja sebuah todo | `in-progress` |
| Selesai + sudah dites (lihat [testing-workflow.instructions.md](testing-workflow.instructions.md)) | `completed` |
| Todo dibatalkan atau scope berubah | `not-started` + jelaskan alasan di laporan |

### Jangan tandai `completed` tanpa bukti

- ❌ **Jangan** tandai `completed` hanya karena kode selesai diketik.
- ✅ **Wajib** jalankan pengujian sesuai [testing-workflow.instructions.md](testing-workflow.instructions.md) sebelum pindah ke `completed`.
- ✅ **Wajib** tampilkan bukti pengujian (output test, ukuran response, log) di laporan ke user.

## 4. Urutan eksekusi standar

1. **Baca task user** dan identifikasi langkah-langkah.
2. **Buat todo list** dengan `manage_todo_list` (todo pertama = `in-progress`, sisanya = `not-started`).
3. **Kerjakan todo `in-progress`** sampai lulus pengujian.
4. **Update status** dalam satu panggilan `manage_todo_list`:
   - todo selesai → `completed`
   - todo berikutnya → `in-progress`
5. **Lapor hasil** ke user dalam format daftar bernomor sesuai [bahasa-indonesia.instructions.md](../../bahasa-indonesia.instructions.md) di root project.
6. **Ulangi** langkah 3–5 sampai semua todo `completed`.

## 5. Laporan hasil per todo

Setiap todo selesai, laporkan ke user dengan struktur:

1. ✅ **Yang sudah diuji** — jenis test + hasilnya (lulus/gagal + ukuran/angka).
2. ❌ **Yang masih gagal** (jika ada) — error + lokasi + langkah perbaikan.
3. ⚠️ **Yang perlu perhatian** — peringatan, risiko, atau asumsi.
4. 🔧 **Langkah berikutnya** — todo berikutnya yang akan dijalankan.

Jika ada todo yang dibatalkan atau dipecah ulang, jelaskan alasannya sebelum lanjut.

## 6. Aturan wajib dan larangan

### Wajib ✅

- ✅ Buat todo list di awal task multi-langkah.
- ✅ Hanya satu todo yang `in-progress` pada satu waktu.
- ✅ Update status todo real-time, bukan di akhir saja.
- ✅ Ikuti [testing-workflow.instructions.md](testing-workflow.instructions.md) untuk kriteria `completed`.
- ✅ Lapor hasil ke user setiap todo selesai dalam format daftar bernomor.
- ✅ Bahasa Indonesia untuk seluruh laporan.

### Larangan ❌

- ❌ **Jangan** buat todo list lalu abaikan update status-nya.
- ❌ **Jangan** tandai `completed` sebelum pengujian hijau.
- ❌ **Jangan** ada 2 todo `in-progress` bersamaan.
- ❌ **Jangan** skip todo atau ubah urutan tanpa alasan yang dilaporkan ke user.
- ❌ **Jangan** tandai `completed` massal di akhir tanpa laporan per todo.
- ❌ **Jangan** hapus todo yang sudah `completed` dari list (biarkan sebagai jejak audit).

## 7. Interaksi dengan instruksi lain

- **Bahasa & format**: [bahasa-indonesia.instructions.md](../../bahasa-indonesia.instructions.md)
- **Workflow testing**: [testing-workflow.instructions.md](testing-workflow.instructions.md) — dipakai sebagai gate sebelum `completed`.
- **Konvensi proyek**: [AGENTS.md](../../AGENTS.md) — dipakai untuk struktur kode, naming, dan jebakan umum Laravel.
- **Domain & kontrak**: [docs/DOMAIN.md](../../docs/DOMAIN.md), [docs/SRS.md](../../docs/SRS.md)

## 8. Referensi cepat

- Akun demo lokal: `superadmin@gudangtoko.test` / `password`.
- Server dev lokal: `php artisan serve` di `http://127.0.0.1:8000` atau `http://gudangtoko.test`.
- Cookie session: `gudangtoko-session`.
- Field login form: `login` (menerima email atau username).