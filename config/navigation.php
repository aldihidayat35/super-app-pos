<?php

return [
    // 1. Dashboard
    [
        'label' => 'Dashboard',
        'icon' => 'ki-outline ki-home-2',
        'route' => 'dashboard',
        'active' => ['dashboard'],
        'permission' => 'dashboard.view',
    ],

    // 2. Shortcut Kasir POS (Tepat di bawah Dashboard)
    [
        'label' => 'Kasir POS',
        'icon' => 'ki-outline ki-calculator',
        'route' => 'retail.pos.index',
        'active' => ['retail.pos.*'],
        'permission' => 'pos.view',
        'access' => 'retail.location',
    ],

    // 3. Administrasi Sistem (Hanya 3 submenu)
    [
        'label' => 'Administrasi Sistem',
        'icon' => 'ki-outline ki-setting-2',
        'children' => [
            [
                'label' => 'Pengguna',
                'route' => 'admin.users.index',
                'active' => ['admin.users.*', 'admin.roles.*', 'admin.permissions.*'],
                'permission' => 'admin.users.view',
            ],
            [
                'label' => 'Lokasi Operasional',
                'route' => 'admin.operational-locations.index',
                'active' => ['admin.operational-locations.*', 'admin.warehouses.*', 'admin.branches.*'],
                'permission' => 'admin.warehouses.view|admin.branches.view',
            ],
            [
                'label' => 'Pengaturan Umum',
                'route' => 'admin.settings.general.edit',
                'active' => ['admin.settings.*', 'admin.whatsapp.*'],
                'permission' => 'admin.settings.view|whatsapp_connection.view|stock_direct_adjustments.manage_access',
            ],
        ],
    ],

    // 4. Master Produk (Single direct menu tanpa dropdown)
    [
        'label' => 'Master Produk',
        'icon' => 'ki-outline ki-parcel',
        'route' => 'admin.products.index',
        'active' => ['admin.products.*', 'admin.product-categories.*', 'admin.product-brands.*', 'admin.units.*'],
        'permission' => 'products.view',
    ],

    // 5. Relasi Bisnis (Single direct menu tanpa dropdown)
    [
        'label' => 'Relasi Bisnis',
        'icon' => 'ki-outline ki-people',
        'route' => 'admin.business-relations.index',
        'active' => ['admin.business-relations.*', 'admin.suppliers.*', 'admin.customers.*'],
        'permission' => 'suppliers.view|customers.view',
    ],

    // 6. Gudang & Stok (Tepat 5 submenu)
    [
        'label' => 'Gudang & Stok',
        'icon' => 'ki-outline ki-delivery-3',
        'children' => [
            [
                'label' => 'Zona, Rak, dan Bin',
                'route' => 'warehouse.locations.index',
                'active' => ['warehouse.locations.*'],
                'permission' => 'stock.view',
            ],
            [
                'label' => 'Saldo Stok',
                'route' => 'warehouse.stocks.index',
                'active' => ['warehouse.stocks.*', 'warehouse.stock-card.*', 'warehouse.batches.*', 'pricing.hpp-history.*'],
                'permission' => 'stock.view',
            ],
            [
                'label' => 'Penerimaan Barang',
                'route' => 'warehouse.goods-receipts.index',
                'active' => ['warehouse.goods-receipts.*'],
                'permission' => 'goods_receipts.view',
            ],
            [
                'label' => 'Pengiriman Barang',
                'route' => 'shipments.index',
                'active' => ['shipments.*'],
                'permission' => 'shipments.view',
            ],
            [
                'label' => 'Retur',
                'route' => 'returns.index',
                'active' => ['returns.*'],
                'permission' => 'returns.view',
            ],
        ],
    ],

    // 7. Harga & Margin
    [
        'label' => 'Harga & Margin',
        'icon' => 'ki-outline ki-price-tag',
        'children' => [
            [
                'label' => 'Harga Produk',
                'route' => 'pricing.product-prices.index',
                'active' => ['pricing.product-prices.*'],
                'permission' => 'prices.view',
            ],
            [
                'label' => 'Histori Harga',
                'route' => 'pricing.history.index',
                'active' => ['pricing.history.*'],
                'permission' => 'prices.view',
            ],
        ],
    ],

    // 8. Toko Internal (Tepat 4 submenu)
    [
        'label' => 'Toko Internal',
        'icon' => 'ki-outline ki-shop',
        'access' => 'retail.location',
        'children' => [
            [
                'label' => 'Kasir POS',
                'route' => 'retail.pos.index',
                'active' => ['retail.pos.*'],
                'permission' => 'pos.view',
            ],
            [
                'label' => 'Riwayat Transaksi',
                'route' => 'retail.sales.index',
                'active' => ['retail.sales.*'],
                'permission' => 'pos.view',
            ],
            [
                'label' => 'Etalase Produk',
                'route' => 'retail.storefront.index',
                'active' => ['retail.storefront.*', 'retail.product-requests.*'],
                'permission' => 'retail.catalog.view',
            ],
            [
                'label' => 'Daftar Pembelian Darurat',
                'route' => 'retail.emergency.index',
                'active' => ['retail.emergency.*'],
                'permission' => 'emergency_purchases.view',
            ],
        ],
    ],

    // 9. Kehadiran
    [
        'label' => 'Kehadiran',
        'icon' => 'ki-outline ki-calendar-tick',
        'children' => [
            [
                'label' => 'Absensi & Tim',
                'route' => 'attendance.dashboard',
                'active' => ['attendance.dashboard', 'attendance.check.*'],
                'permission' => 'attendance.check|attendance.view',
            ],
            [
                'label' => 'Jadwal Mingguan',
                'route' => 'attendance.schedules.index',
                'active' => ['attendance.schedules.*'],
                'permission' => 'attendance.view',
            ],
            [
                'label' => 'Pengajuan Izin',
                'route' => 'attendance.requests.index',
                'active' => ['attendance.requests.*'],
                'permission' => 'attendance.check|attendance.approve',
            ],
            [
                'label' => 'Koreksi Absensi',
                'route' => 'attendance.corrections.index',
                'active' => ['attendance.corrections.*'],
                'permission' => 'attendance.update|attendance.approve',
            ],
            [
                'label' => 'Pengaturan Karyawan',
                'route' => 'attendance.employees.index',
                'active' => ['attendance.employees.*'],
                'permission' => 'attendance.update',
            ],
            [
                'label' => 'Pengaturan Shift',
                'route' => 'attendance.work-shifts.index',
                'active' => ['attendance.work-shifts.*'],
                'permission' => 'attendance.update',
            ],
        ],
    ],

    // 10. Kontrol & Audit (Memuat approval, audit, dan kontrol teknis)
    [
        'label' => 'Kontrol & Audit',
        'icon' => 'ki-outline ki-shield-tick',
        'children' => [
            [
                'label' => 'Kotak Masuk Approval',
                'route' => 'approvals.index',
                'active' => ['approvals.*'],
                'permission' => 'approvals.view',
            ],
            [
                'label' => 'Audit Log',
                'route' => 'audit-logs.index',
                'active' => ['audit-logs.*'],
                'permission' => 'audit.view',
            ],
            [
                'label' => 'Dashboard Anomali',
                'route' => 'audit.anomalies.index',
                'active' => ['audit.anomalies.*'],
                'permission' => 'audit.view',
            ],
            [
                'label' => 'Log Login & Keamanan',
                'route' => 'audit.security.index',
                'active' => ['audit.security.*'],
                'permission' => 'audit.view',
            ],
            [
                'label' => 'Kesehatan Sistem',
                'route' => 'admin.system.health',
                'active' => ['admin.system.health', 'system.health'],
                'permission' => 'system.health.view',
            ],
            [
                'label' => 'Backup',
                'route' => 'admin.system.backups.index',
                'active' => ['admin.system.backups.*'],
                'permission' => 'system.operations.view',
            ],
            [
                'label' => 'Log QW',
                'route' => 'admin.system.logs.index',
                'active' => ['admin.system.logs.*'],
                'permission' => 'system.operations.view',
            ],
            [
                'label' => 'Maintenance',
                'route' => 'admin.system.maintenance.index',
                'active' => ['admin.system.maintenance.*'],
                'permission' => 'system.operations.view',
            ],
            [
                'label' => 'Go Live',
                'route' => 'admin.system.maintenance.index',
                'route_params' => ['section' => 'golive'],
                'active' => ['admin.system.maintenance.*'],
                'permission' => 'system.operations.view',
            ],
        ],
    ],

    // Portal Langganan (Khusus pelanggan B2B)
    [
        'label' => 'Portal Langganan',
        'icon' => 'ki-outline ki-basket',
        'access' => 'b2b.customer',
        'children' => [
            [
                'label' => 'Dashboard Langganan',
                'route' => 'langganan.dashboard',
                'active' => ['langganan.dashboard'],
                'permission' => 'dashboard.view',
            ],
            [
                'label' => 'Katalog',
                'route' => 'langganan.katalog.index',
                'active' => ['langganan.katalog.*'],
                'permission' => 'b2b_orders.view',
            ],
            [
                'label' => 'Keranjang',
                'route' => 'langganan.keranjang.index',
                'active' => ['langganan.keranjang.*'],
                'permission' => 'b2b_orders.create',
            ],
            [
                'label' => 'Checkout',
                'route' => 'langganan.checkout.show',
                'active' => ['langganan.checkout.*'],
                'permission' => 'b2b_orders.create',
            ],
            [
                'label' => 'Reorder Cepat',
                'route' => 'langganan.reorder.index',
                'active' => ['langganan.reorder.*'],
                'permission' => 'b2b_orders.create',
            ],
            [
                'label' => 'Riwayat Order',
                'route' => 'langganan.orders.index',
                'active' => ['langganan.orders.*'],
                'permission' => 'b2b_orders.view',
            ],
            [
                'label' => 'Invoice',
                'route' => 'invoices.index',
                'active' => ['invoices.*'],
                'permission' => 'receivables.view',
            ],
            [
                'label' => 'Komplain',
                'route' => 'langganan.complaints.index',
                'active' => ['langganan.complaints.*'],
                'permission' => 'complaints.create',
            ],
            [
                'label' => 'Profil Usaha',
                'route' => 'langganan.profil.edit',
                'active' => ['langganan.profil.*'],
                'permission' => 'customers.view_own',
            ],
        ],
    ],

    // Dokumentasi Panduan
    [
        'label' => 'Dokumentasi Panduan',
        'icon' => 'ki-outline ki-book-open',
        'route' => 'guides.index',
        'active' => ['guides.*'],
    ],
];
