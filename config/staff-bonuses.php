<?php

return [
    'timezone' => 'Asia/Jakarta',
    'eligible_roles' => ['staff_gudang', 'picker_packer', 'staf_toko', 'kasir', 'sales'],
    'team_roles' => [
        'kepala_gudang' => ['staff_gudang', 'picker_packer'],
        'kepala_toko' => ['staf_toko', 'kasir'],
        'supervisor_shift' => ['staf_toko', 'kasir'],
    ],
    'metrics' => [
        'warehouse_tasks' => ['label' => 'Pekerjaan gudang selesai', 'scope' => 'team', 'roles' => ['staff_gudang', 'picker_packer']],
        'warehouse_accuracy' => ['label' => 'Akurasi pekerjaan gudang', 'scope' => 'team', 'roles' => ['staff_gudang', 'picker_packer']],
        'pos_net_sales' => ['label' => 'Penjualan POS bersih', 'scope' => 'team', 'roles' => ['staf_toko', 'kasir']],
        'cash_accuracy' => ['label' => 'Shift kas tanpa selisih melebihi toleransi', 'scope' => 'team', 'roles' => ['staf_toko', 'kasir']],
        'b2b_net_sales' => ['label' => 'Penjualan B2B bersih', 'scope' => 'individual', 'roles' => ['sales']],
        'attendance_rate' => ['label' => 'Kehadiran terverifikasi', 'scope' => 'individual', 'roles' => ['staff_gudang', 'picker_packer', 'staf_toko', 'kasir', 'sales']],
        'checklist_on_time' => ['label' => 'Checklist harian tepat waktu', 'scope' => 'individual', 'roles' => ['staff_gudang', 'picker_packer', 'staf_toko', 'kasir', 'sales']],
    ],
];
