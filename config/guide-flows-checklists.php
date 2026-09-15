<?php

return [
    'checklist-generation' => [
        'title' => 'Pembentukan checklist akun',
        'summary' => 'Generator memakai akun aktif, role, lokasi efektif, frekuensi, dan versi template yang berlaku pada awal periode.',
        'verified_by' => ['app/Services/WorkChecklist/WorkChecklistService.php', 'routes/console.php'],
        'lanes' => [
            ['label' => 'Jadwal', 'tone' => 'primary', 'steps' => [
                ['label' => 'Scheduler berjalan', 'detail' => 'Perintah dijalankan setiap 15 menit dalam zona Asia/Jakarta.'],
                ['label' => 'Pilih akun internal aktif', 'detail' => 'Akun yang hanya memiliki role pelanggan B2B tidak diproses.', 'kind' => 'decision'],
                ['label' => 'Baca role dan lokasi efektif', 'detail' => 'Penugasan belum berlaku, berakhir, atau nonaktif dilewati.'],
                ['label' => 'Pilih versi template', 'detail' => 'Versi ditentukan dari tanggal awal periode.'],
                ['label' => 'Gabungkan poin', 'detail' => 'Poin dengan item_key sama disatukan dan asal role disimpan.'],
                ['label' => 'Simpan snapshot', 'detail' => 'Checklist dibuat satu kali per akun, lingkup, frekuensi, dan periode.', 'kind' => 'result'],
            ]],
        ],
    ],
    'checklist-fill' => [
        'title' => 'Mengisi dan menyelesaikan checklist',
        'summary' => 'Pengguna mengisi checklist miliknya; status kendala dan tidak berlaku wajib memiliki penjelasan.',
        'verified_by' => ['app/Http/Controllers/WorkChecklist/WorkChecklistController.php', 'app/Services/WorkChecklist/WorkChecklistService.php', 'app/Policies/WorkChecklistPolicy.php'],
        'lanes' => [
            ['label' => 'Pengisian', 'tone' => 'primary', 'steps' => [
                ['label' => 'Buka Checklist Saya', 'detail' => 'Halaman memastikan checklist periode aktif sudah tersedia.'],
                ['label' => 'Pilih status poin', 'detail' => 'Belum diisi, selesai, terkendala, atau tidak berlaku.'],
                ['label' => 'Perlu catatan?', 'detail' => 'Terkendala dan tidak berlaku wajib disertai catatan.', 'kind' => 'decision'],
                ['label' => 'Simpan respons', 'detail' => 'Waktu respons dan perubahan dicatat dalam audit.'],
                ['label' => 'Semua sudah direspons?', 'detail' => 'Poin pending menahan penyelesaian checklist.', 'kind' => 'decision'],
                ['label' => 'Selesaikan checklist', 'detail' => 'Waktu selesai pertama menentukan tepat waktu atau terlambat.', 'kind' => 'result'],
            ]],
            ['label' => 'Koreksi', 'tone' => 'warning', 'steps' => [
                ['label' => 'Checklist sudah selesai', 'detail' => 'Jawaban berada dalam keadaan terkunci.'],
                ['label' => 'Isi alasan koreksi', 'detail' => 'Alasan minimal lima karakter.'],
                ['label' => 'Buka kembali', 'detail' => 'Status kembali terbuka dan audit menyimpan alasannya.', 'kind' => 'result'],
            ]],
        ],
    ],
    'checklist-recap' => [
        'title' => 'Riwayat dan rekap checklist',
        'summary' => 'Rekap mengikuti permission, hubungan role atasan, dan lokasi kerja yang dapat diakses.',
        'verified_by' => ['app/Http/Controllers/WorkChecklist/WorkChecklistController.php', 'app/Policies/WorkChecklistPolicy.php'],
        'lanes' => [
            ['label' => 'Akses data', 'tone' => 'primary', 'steps' => [
                ['label' => 'Pilih tab rekap', 'detail' => 'Tab tersedia untuk atasan, Owner, dan Super Admin.'],
                ['label' => 'Periksa lingkup', 'detail' => 'Kepala Gudang melihat tim gudang; kepala toko dan supervisor melihat tim toko pada lokasi tugas.', 'kind' => 'decision'],
                ['label' => 'Terapkan filter', 'detail' => 'Tanggal, frekuensi, status, lokasi, akun, dan role.'],
                ['label' => 'Hitung indikator', 'detail' => 'Wajib, selesai, tepat waktu, terlambat, terbuka, kendala, tingkat pengisian, dan penyelesaian.'],
                ['label' => 'Buka detail atau CSV', 'detail' => 'Data hasil tetap mengikuti scope akses pengguna.', 'kind' => 'result'],
            ]],
        ],
    ],
    'checklist-template' => [
        'title' => 'Mengelola versi template',
        'summary' => 'Super Admin dan Admin Config dapat menjadwalkan isi checklist tanpa mengubah histori yang sudah terbentuk.',
        'verified_by' => ['app/Http/Requests/WorkChecklist/StoreWorkChecklistTemplateRequest.php', 'app/Services/WorkChecklist/WorkChecklistService.php'],
        'lanes' => [
            ['label' => 'Versi template', 'tone' => 'success', 'steps' => [
                ['label' => 'Isi identitas template', 'detail' => 'Nama, kode, frekuensi, lingkup, dan jenis lokasi.'],
                ['label' => 'Pilih role', 'detail' => 'Hanya role internal yang tersedia.'],
                ['label' => 'Susun poin', 'detail' => 'Setiap item_key unik menjadi identitas deduplikasi.'],
                ['label' => 'Pilih mulai berlaku', 'detail' => 'Tanggal harus setelah hari berjalan; mingguan dinormalisasi ke Senin.'],
                ['label' => 'Simpan versi baru', 'detail' => 'Versi lama ditutup sehari sebelum versi baru berlaku.', 'kind' => 'result'],
                ['label' => 'Nonaktifkan?', 'detail' => 'Template berhenti setelah periode aktif berakhir.', 'kind' => 'decision'],
            ]],
        ],
    ],
];
