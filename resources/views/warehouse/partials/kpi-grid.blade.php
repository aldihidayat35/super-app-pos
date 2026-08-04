@php
    $kpis = $kpis ?? $dashboard['kpis'];
    $activeWarehouse = $activeWarehouse ?? null;
    $wlId = $activeWarehouse?->work_location_id;
    $stockUrl = $wlId ? route('warehouse.stocks.index', ['work_location_id' => $wlId]) : route('warehouse.stocks.index');
    $criticalUrl = $wlId ? route('warehouse.stocks.index', ['work_location_id' => $wlId, 'status' => 'critical']) : route('warehouse.stocks.index', ['status' => 'critical']);
    $transferUrl = route('warehouse.location-transfers.index');
    $poUrl = route('purchasing.purchase-orders.index');
@endphp

<div class="row g-4 g-xl-5 mb-6">
    <div class="col-md-6 col-xl-4 col-xxl-3">
        <x-metronic.kpi-card
            title="Total Produk"
            :value="number_format((float) ($kpis['total_products'] ?? 0))"
            icon="ki-outline ki-package"
            color="primary"
            description="Produk dengan saldo di gudang aktif"
            :href="$stockUrl"
            tooltip="Jumlah produk unik yang memiliki stok pada gudang aktif saat ini."
        />
    </div>
    <div class="col-md-6 col-xl-4 col-xxl-3">
        <x-metronic.kpi-card
            title="Total Stok"
            :value="qty($kpis['on_hand_quantity'] ?? 0)"
            icon="ki-outline ki-archive"
            color="info"
            description="Seluruh stok fisik tercatat"
            tooltip="Akumulasi quantity_on_hand seluruh produk pada gudang aktif."
        />
    </div>
    <div class="col-md-6 col-xl-4 col-xxl-3">
        <x-metronic.kpi-card
            title="Stok Tersedia"
            :value="qty($kpis['available_quantity'] ?? 0)"
            icon="ki-outline ki-check-square"
            color="success"
            description="Siap pakai untuk transaksi"
            :href="$stockUrl"
            tooltip="Stok on_hand dikurangi reserved dan damaged. Stok ini siap dipakai untuk transaksi baru."
        />
    </div>
    <div class="col-md-6 col-xl-4 col-xxl-3">
        <x-metronic.kpi-card
            title="Stok Dipesan"
            :value="qty($kpis['reserved_quantity'] ?? 0)"
            icon="ki-outline ki-lock"
            color="warning"
            description="Sudah dialokasikan ke pesanan"
            tooltip="Stok yang sudah di-reserve untuk Order B2B atau dokumen outgoing lain yang sudah approved."
        />
    </div>
    <div class="col-md-6 col-xl-4 col-xxl-3">
        <x-metronic.kpi-card
            title="Stok Rusak"
            :value="qty($kpis['damaged_quantity'] ?? 0)"
            icon="ki-outline ki-disconnect"
            color="danger"
            description="Tidak dapat digunakan"
            tooltip="Stok berstatus rusak/loss. Pemutihan dilakukan lewat modul Barang Rusak & Loss."
        />
    </div>
    <div class="col-md-6 col-xl-4 col-xxl-3">
        <x-metronic.kpi-card
            title="Nilai Persediaan"
            :value="\App\Support\CurrencyFormatter::rupiah($kpis['stock_value'] ?? 0)"
            icon="ki-outline ki-wallet"
            color="success"
            description="Nilai stok berdasarkan HPP"
            tooltip="Total nilai persediaan dihitung dari kolom stocks.cost_value dengan metode HPP rata-rata."
        />
    </div>
    <div class="col-md-6 col-xl-4 col-xxl-3">
        <x-metronic.kpi-card
            title="Stok Kritis"
            :value="number_format((float) ($kpis['critical_count'] ?? 0))"
            icon="ki-outline ki-warning"
            color="warning"
            description="Di bawah batas minimum stok"
            :href="$criticalUrl"
            tooltip="Produk dengan available stock kurang atau sama dengan minimum_stock yang ditentukan master produk."
        />
    </div>
    <div class="col-md-6 col-xl-4 col-xxl-3">
        <x-metronic.kpi-card
            title="Stok Kosong"
            :value="number_format((float) ($kpis['empty_count'] ?? 0))"
            icon="ki-outline ki-cross-circle"
            color="danger"
            description="Available stock nol/negatif"
            :href="$criticalUrl"
            tooltip="Produk yang stok available-nya sudah nol atau negatif sehingga tidak bisa memenuhi permintaan."
        />
    </div>
    <div class="col-md-6 col-xl-4 col-xxl-3">
        <x-metronic.kpi-card
            title="Mutasi Masuk"
            :value="number_format((float) ($kpis['incoming_count'] ?? 0))"
            icon="ki-outline ki-down-square"
            color="info"
            description="Receive, transfer in, return in"
            tooltip="Jumlah mutasi masuk (receive + return in + transfer in) pada gudang aktif selama periode filter."
        />
    </div>
    <div class="col-md-6 col-xl-4 col-xxl-3">
        <x-metronic.kpi-card
            title="Mutasi Keluar"
            :value="number_format((float) ($kpis['outgoing_count'] ?? 0))"
            icon="ki-outline ki-up-square"
            color="danger"
            description="Issue, transfer out, return out"
            tooltip="Jumlah mutasi keluar (issue + return out + transfer out) pada gudang aktif selama periode filter."
        />
    </div>
    <div class="col-md-6 col-xl-4 col-xxl-3">
        <x-metronic.kpi-card
            title="PO Pending"
            :value="number_format((float) ($kpis['pending_po'] ?? 0))"
            icon="ki-outline ki-purchase"
            color="warning"
            description="Purchase Order belum selesai"
            :href="$poUrl"
            tooltip="Purchase Order yang masih dalam status submitted, approved, sent_to_supplier, atau partially_received."
        />
    </div>
    <div class="col-md-6 col-xl-4 col-xxl-3">
        <x-metronic.kpi-card
            title="Transfer Pending"
            :value="number_format((float) ($kpis['pending_transfer'] ?? 0))"
            icon="ki-outline ki-arrow-right-left"
            color="warning"
            description="Transfer stok belum selesai"
            :href="$transferUrl"
            tooltip="Stock Transfer dengan status pending approval, approved, packing, atau shipped."
        />
    </div>
</div>
