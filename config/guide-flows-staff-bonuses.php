<?php

return [
    'bonus-program-setup' => [
        'title' => 'Menyusun dan mengaktifkan program bonus',
        'summary' => 'Owner memilih KPI resmi, target, bobot 100%, lokasi, penerima, dan bonus maksimum sebelum bulan dimulai.',
        'verified_by' => ['app/Http/Controllers/StaffBonus/StaffBonusController.php', 'app/Services/StaffBonus/StaffBonusService.php'],
        'lanes' => [['label' => 'Persiapan program', 'tone' => 'primary', 'steps' => [
            ['label' => 'Pilih bulan dan role', 'detail' => 'Periode wajib belum dimulai.'],
            ['label' => 'Pilih lokasi dan staf', 'detail' => 'Sales global; toko dan gudang mengikuti lokasi utama karyawan.'],
            ['label' => 'Atur KPI dan target', 'detail' => 'Hanya KPI resmi untuk role yang dapat dipilih.'],
            ['label' => 'Periksa bobot 100%', 'detail' => 'Aktivasi ditolak jika bobot atau penerima tidak valid.', 'kind' => 'decision'],
            ['label' => 'Aktifkan program', 'detail' => 'Konfigurasi dan akun penerima disimpan sebagai snapshot.', 'kind' => 'result'],
        ]]],
    ],
    'bonus-calculation' => [
        'title' => 'Menghitung progres dan estimasi bonus',
        'summary' => 'Scheduler membaca transaksi final, absensi terverifikasi, dan checklist lalu memperbarui nilai tanpa mengubah sumber data.',
        'verified_by' => ['app/Services/StaffBonus/StaffBonusService.php', 'app/Console/Commands/CalculateStaffBonusesCommand.php'],
        'lanes' => [['label' => 'Perhitungan harian', 'tone' => 'info', 'steps' => [
            ['label' => 'Ambil data periode', 'detail' => 'Void dikecualikan dan retur selesai mengurangi penjualan.'],
            ['label' => 'Hitung aktual per KPI', 'detail' => 'Metrik tim memakai lokasi; metrik individu memakai akun.'],
            ['label' => 'Bandingkan dengan target', 'detail' => 'Skor KPI dibatasi antara 0 sampai 100.'],
            ['label' => 'Terapkan bobot', 'detail' => 'Seluruh nilai berbobot dijumlahkan.'],
            ['label' => 'Hitung estimasi bonus', 'detail' => 'Bonus maksimum dikali nilai akhir dibagi 100.', 'kind' => 'result'],
        ]]],
    ],
    'bonus-approval' => [
        'title' => 'Menutup dan menyetujui bonus bulanan',
        'summary' => 'Hasil hanya dapat diajukan setelah bulan selesai dan seluruh absensi terkait tidak lagi menunggu verifikasi.',
        'verified_by' => ['app/Services/StaffBonus/StaffBonusService.php', 'app/Services/Control/ApprovalWorkflowService.php'],
        'lanes' => [['label' => 'Penutupan periode', 'tone' => 'warning', 'steps' => [
            ['label' => 'Bulan berakhir', 'detail' => 'Owner atau admin menjalankan hitung akhir.'],
            ['label' => 'Absensi sudah diverifikasi?', 'detail' => 'Pengajuan ditolak jika masih ada status pending.', 'kind' => 'decision'],
            ['label' => 'Buat approval', 'detail' => 'Total bonus dan snapshot dikirim ke kotak persetujuan.'],
            ['label' => 'Owner menilai hasil', 'detail' => 'Setujui atau tolak dengan alasan.'],
            ['label' => 'Hasil dikunci', 'detail' => 'Hasil disetujui siap dibayar; hasil ditolak dapat dihitung ulang.', 'kind' => 'result'],
        ]]],
    ],
    'bonus-payment' => [
        'title' => 'Mencatat pembayaran bonus',
        'summary' => 'Pembayaran dicatat sebesar nilai yang disetujui tanpa membuat payroll atau pengeluaran shift.',
        'verified_by' => ['app/Http/Controllers/StaffBonus/StaffBonusController.php', 'app/Models/StaffBonusPayment.php'],
        'lanes' => [['label' => 'Pencairan', 'tone' => 'success', 'steps' => [
            ['label' => 'Pilih bonus disetujui', 'detail' => 'Bonus yang belum disetujui tidak dapat dibayar.'],
            ['label' => 'Isi tanggal dan metode', 'detail' => 'Tunai, transfer bank, dompet digital, atau lainnya.'],
            ['label' => 'Isi referensi', 'detail' => 'Referensi wajib; bukti pembayaran opsional.'],
            ['label' => 'Catat pembayaran', 'detail' => 'Nominal harus sama dengan hasil yang disetujui.'],
            ['label' => 'Tutup periode', 'detail' => 'Periode ditutup setelah seluruh penerima dibayar.', 'kind' => 'result'],
        ]]],
    ],
    'bonus-access-report' => [
        'title' => 'Melihat progres, tim, dan rekap',
        'summary' => 'Setiap akses diperiksa menurut permission, kepemilikan akun, role bawahan, dan lokasi kerja.',
        'verified_by' => ['app/Policies/StaffBonusResultPolicy.php', 'app/Http/Controllers/StaffBonus/StaffBonusController.php'],
        'lanes' => [['label' => 'Akses informasi', 'tone' => 'primary', 'steps' => [
            ['label' => 'Staf membuka Target Saya', 'detail' => 'Hanya hasil milik akun tersebut.'],
            ['label' => 'Kepala membuka Kinerja Tim', 'detail' => 'Hanya role bawahan pada lokasi penugasan.'],
            ['label' => 'Owner membuka Rekap', 'detail' => 'Seluruh lokasi, role, pembayaran, dan skema lama.'],
            ['label' => 'Terapkan filter', 'detail' => 'Periode, lokasi, role, dan status pembayaran.'],
            ['label' => 'Unduh CSV', 'detail' => 'Ekspor mengikuti filter dan permission pengguna.', 'kind' => 'result'],
        ]]],
    ],
];
