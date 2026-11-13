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
                'icon' => 'ki-outline ki-profile-user',
                'route' => 'admin.users.index',
                'active' => ['admin.users.*', 'admin.roles.*', 'admin.permissions.*'],
                'permission' => 'admin.users.view',
            ],
            [
                'label' => 'Lokasi Operasional',
                'icon' => 'ki-outline ki-geolocation',
                'route' => 'admin.operational-locations.index',
                'active' => ['admin.operational-locations.*', 'admin.warehouses.*', 'admin.branches.*'],
                'permission' => 'admin.warehouses.view|admin.branches.view',
            ],
            [
                'label' => 'Pengaturan Umum',
                'icon' => 'ki-outline ki-setting-3',
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
        'icon' => 'ki-outline ki-warehouse',
        'children' => [
            [
                'label' => 'Zona, Rak, dan Bin',
                'icon' => 'ki-outline ki-row-horizontal',
                'route' => 'warehouse.locations.index',
                'active' => ['warehouse.locations.*'],
                'permission' => 'stock.view',
            ],
            [
                'label' => 'Saldo Stok',
                'icon' => 'ki-outline ki-package',
                'route' => 'warehouse.stocks.index',
                'active' => ['warehouse.stocks.*', 'warehouse.stock-card.*', 'warehouse.batches.*', 'pricing.hpp-history.*'],
                'permission' => 'stock.view',
            ],
            [
                'label' => 'Penerimaan Barang',
                'icon' => 'ki-outline ki-box-3',
                'route' => 'warehouse.goods-receipts.index',
                'active' => ['warehouse.goods-receipts.*'],
                'permission' => 'goods_receipts.view',
            ],
            [
                'label' => 'Pengiriman Barang',
                'icon' => 'ki-outline ki-delivery-2',
                'route' => 'shipments.index',
                'active' => ['shipments.*'],
                'permission' => 'shipments.view',
            ],
            [
                'label' => 'Retur',
                'icon' => 'ki-outline ki-arrows-loop',
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
                'icon' => 'ki-outline ki-price-tag',
                'route' => 'pricing.product-prices.index',
                'active' => ['pricing.product-prices.*'],
                'permission' => 'prices.view',
            ],
            [
                'label' => 'Histori Harga',
                'icon' => 'ki-outline ki-time',
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
                'icon' => 'ki-outline ki-calculator',
                'route' => 'retail.pos.index',
                'active' => ['retail.pos.*'],
                'permission' => 'pos.view',
            ],
            [
                'label' => 'Riwayat Transaksi',
                'icon' => 'ki-outline ki-receipt',
                'route' => 'retail.sales.index',
                'active' => ['retail.sales.*'],
                'permission' => 'pos.view',
            ],
            [
                'label' => 'Etalase Produk',
                'icon' => 'ki-outline ki-shop',
                'route' => 'retail.storefront.index',
                'active' => ['retail.storefront.*', 'retail.product-requests.*'],
                'permission' => 'retail.catalog.view',
            ],
            [
                'label' => 'Daftar Pembelian Darurat',
                'icon' => 'ki-outline ki-flash',
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
                'icon' => 'ki-outline ki-badge',
                'route' => 'attendance.dashboard',
                'active' => ['attendance.dashboard', 'attendance.check.*'],
                'permission' => 'attendance.check|attendance.view',
            ],
            [
                'label' => 'Jadwal Mingguan',
                'icon' => 'ki-outline ki-calendar-tick',
                'route' => 'attendance.schedules.index',
                'active' => ['attendance.schedules.*'],
                'permission' => 'attendance.view',
            ],
            [
                'label' => 'Pengajuan Izin',
                'icon' => 'ki-outline ki-document',
                'route' => 'attendance.requests.index',
                'active' => ['attendance.requests.*'],
                'permission' => 'attendance.check|attendance.approve',
            ],
            [
                'label' => 'Koreksi Absensi',
                'icon' => 'ki-outline ki-notepad-edit',
                'route' => 'attendance.corrections.index',
                'active' => ['attendance.corrections.*'],
                'permission' => 'attendance.update|attendance.approve',
            ],
            [
                'label' => 'Pengaturan Karyawan',
                'icon' => 'ki-outline ki-people',
                'route' => 'attendance.employees.index',
                'active' => ['attendance.employees.*'],
                'permission' => 'attendance.update',
            ],
            [
                'label' => 'Pengaturan Shift',
                'icon' => 'ki-outline ki-time',
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
                'icon' => 'ki-outline ki-check-circle',
                'route' => 'approvals.index',
                'active' => ['approvals.*'],
                'permission' => 'approvals.view',
            ],
            [
                'label' => 'Audit Log',
                'icon' => 'ki-outline ki-document-filter',
                'route' => 'audit-logs.index',
                'active' => ['audit-logs.*'],
                'permission' => 'audit.view',
            ],
            [
                'label' => 'Dashboard Anomali',
                'icon' => 'ki-outline ki-chart-line-down',
                'route' => 'audit.anomalies.index',
                'active' => ['audit.anomalies.*'],
                'permission' => 'audit.view',
            ],
            [
                'label' => 'Log Login & Keamanan',
                'icon' => 'ki-outline ki-shield',
                'route' => 'audit.security.index',
                'active' => ['audit.security.*'],
                'permission' => 'audit.view',
            ],
            [
                'label' => 'Kesehatan Sistem',
                'icon' => 'ki-outline ki-heart',
                'route' => 'admin.system.health',
                'active' => ['admin.system.health', 'system.health'],
                'permission' => 'system.health.view',
            ],
            [
                'label' => 'Backup',
                'icon' => 'ki-outline ki-data',
                'route' => 'admin.system.backups.index',
                'active' => ['admin.system.backups.*'],
                'permission' => 'system.operations.view',
            ],
            [
                'label' => 'Log QW',
                'icon' => 'ki-outline ki-code',
                'route' => 'admin.system.logs.index',
                'active' => ['admin.system.logs.*'],
                'permission' => 'system.operations.view',
            ],
            [
                'label' => 'Maintenance',
                'icon' => 'ki-outline ki-wrench',
                'route' => 'admin.system.maintenance.index',
                'active' => ['admin.system.maintenance.*'],
                'permission' => 'system.operations.view',
            ],
            [
                'label' => 'Go Live',
                'icon' => 'ki-outline ki-rocket',
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
                'icon' => 'ki-outline ki-home-2',
                'route' => 'langganan.dashboard',
                'active' => ['langganan.dashboard'],
                'permission' => 'dashboard.view',
            ],
            [
                'label' => 'Katalog',
                'icon' => 'ki-outline ki-category',
                'route' => 'langganan.katalog.index',
                'active' => ['langganan.katalog.*'],
                'permission' => 'b2b_orders.view',
            ],
            [
                'label' => 'Keranjang',
                'icon' => 'ki-outline ki-basket',
                'route' => 'langganan.keranjang.index',
                'active' => ['langganan.keranjang.*'],
                'permission' => 'b2b_orders.create',
            ],
            [
                'label' => 'Checkout',
                'icon' => 'ki-outline ki-credit-cart',
                'route' => 'langganan.checkout.show',
                'active' => ['langganan.checkout.*'],
                'permission' => 'b2b_orders.create',
            ],
            [
                'label' => 'Reorder Cepat',
                'icon' => 'ki-outline ki-repeat',
                'route' => 'langganan.reorder.index',
                'active' => ['langganan.reorder.*'],
                'permission' => 'b2b_orders.create',
            ],
            [
                'label' => 'Riwayat Order',
                'icon' => 'ki-outline ki-receipt',
                'route' => 'langganan.orders.index',
                'active' => ['langganan.orders.*'],
                'permission' => 'b2b_orders.view',
            ],
            [
                'label' => 'Invoice',
                'icon' => 'ki-outline ki-bill',
                'route' => 'invoices.index',
                'active' => ['invoices.*'],
                'permission' => 'receivables.view',
            ],
            [
                'label' => 'Komplain',
                'icon' => 'ki-outline ki-message-question',
                'route' => 'langganan.complaints.index',
                'active' => ['langganan.complaints.*'],
                'permission' => 'complaints.create',
            ],
            [
                'label' => 'Profil Usaha',
                'icon' => 'ki-outline ki-user',
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
