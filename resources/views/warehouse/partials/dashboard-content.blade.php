@php($kpis = $dashboard['kpis'])

@if (! $activeWarehouse)
    <div class="alert alert-warning d-flex align-items-center mb-5" role="alert">
        <i class="ki-outline ki-information-5 fs-2x text-warning me-4"></i>
        <div>
            <div class="fw-bold">Belum ada gudang yang dapat ditampilkan</div>
            <div>Akun ini belum memiliki akses ke gudang aktif. Hubungi administrator untuk memeriksa penugasan lokasi kerja.</div>
        </div>
    </div>
@endif

@include('reports.partials.kpi-grid', ['items' => [
    ['label' => 'Total Produk', 'value' => $kpis['total_products'], 'color' => 'primary', 'description' => 'Produk yang memiliki saldo di gudang aktif'],
    ['label' => 'Total Stok', 'value' => qty($kpis['on_hand_quantity']), 'color' => 'info', 'description' => 'Seluruh stok fisik tercatat'],
    ['label' => 'Stok Tersedia', 'value' => qty($kpis['available_quantity']), 'color' => 'success', 'description' => 'Total stok - reserved - rusak'],
    ['label' => 'Stok Dipesan', 'value' => qty($kpis['reserved_quantity']), 'color' => 'warning', 'description' => 'Stok yang sudah dialokasikan'],
    ['label' => 'Stok Rusak', 'value' => qty($kpis['damaged_quantity']), 'color' => 'danger', 'description' => 'Stok yang tidak dapat digunakan'],
    ['label' => 'Nilai Persediaan', 'value' => \App\Support\CurrencyFormatter::rupiah($kpis['stock_value']), 'color' => 'success', 'description' => 'Nilai stok berdasarkan HPP'],
    ['label' => 'Kritis / Kosong', 'value' => $kpis['critical_count'].' / '.$kpis['empty_count'], 'color' => 'danger', 'description' => 'Produk di bawah batas / habis'],
    ['label' => 'Masuk / Keluar', 'value' => $kpis['incoming_count'].' / '.$kpis['outgoing_count'], 'color' => 'info', 'description' => 'Jumlah mutasi selama periode aktif'],
    ['label' => 'PO / Transfer Pending', 'value' => $kpis['pending_po'].' / '.$kpis['pending_transfer'], 'color' => 'warning', 'description' => 'Dokumen operasional yang belum selesai'],
]])

<div class="row g-5 mb-5">
    <div class="col-xl-4">
        <x-metronic.card title="Dokumen Operasional">
            <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
                <span class="text-gray-700">Order B2B Pending</span>
                <span class="badge badge-light-warning fw-bold">{{ $kpis['pending_order'] }}</span>
            </div>
            <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
                <span class="text-gray-700">Penerimaan Diposting</span>
                <span class="badge badge-light-success fw-bold">{{ $kpis['posted_receipts'] }}</span>
            </div>
            <div class="d-flex align-items-center justify-content-between">
                <span class="text-gray-700">Stock Opname Terbuka</span>
                <span class="badge badge-light-primary fw-bold">{{ $kpis['open_opname'] }}</span>
            </div>
        </x-metronic.card>
    </div>

    <div class="col-xl-8">
        <x-metronic.card title="Peringatan Stok">
            <x-slot:toolbar>
                <a href="{{ route('warehouse.stocks.index', ['work_location_id' => $activeWarehouse?->work_location_id, 'status' => 'critical']) }}" class="btn btn-sm btn-light-danger">
                    Lihat Saldo Stok
                </a>
            </x-slot:toolbar>

            <div class="table-responsive">
                <table class="table table-row-dashed align-middle mb-0">
                    <thead>
                    <tr class="text-muted fw-bold text-uppercase fs-7">
                        <th>Produk</th>
                        <th class="text-end">Tersedia</th>
                        <th class="text-end">Minimum</th>
                        <th class="text-end">Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($dashboard['stock_alerts'] as $alert)
                        <tr>
                            <td>
                                <a href="{{ route('warehouse.stock-card.index', ['product_id' => $alert['product_id'], 'work_location_id' => $activeWarehouse?->work_location_id]) }}" class="fw-bold text-gray-800 text-hover-primary">
                                    {{ $alert['sku'] }} — {{ $alert['product'] }}
                                </a>
                            </td>
                            <td class="text-end fw-semibold">{{ qty($alert['available']) }}</td>
                            <td class="text-end">{{ qty($alert['minimum_stock']) }}</td>
                            <td class="text-end">
                                <span class="badge {{ $alert['status'] === 'empty' ? 'badge-light-danger' : 'badge-light-warning' }}">
                                    {{ $alert['status'] === 'empty' ? 'Kosong' : 'Kritis' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-metronic.empty-state title="Stok dalam kondisi aman" description="Tidak ada produk kritis atau kosong pada gudang aktif." />
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-metronic.card>
    </div>
</div>

<div class="row g-5 mb-5">
    <div class="col-lg-7">
        <x-metronic.card title="Pergerakan Stok">
            <x-slot:toolbar>
                <div class="btn-group btn-group-sm" role="group" id="warehouse-movement-period" aria-label="Periode pergerakan stok">
                    <button type="button" class="btn btn-light-primary active" data-period="daily">Harian</button>
                    <button type="button" class="btn btn-light-primary" data-period="monthly">Bulanan</button>
                    <button type="button" class="btn btn-light-primary" data-period="yearly">Tahunan</button>
                </div>
            </x-slot:toolbar>
            <div class="text-muted fs-7 mb-3">Perbandingan mutasi masuk dan keluar pada gudang aktif.</div>
            <div id="warehouse-daily-movement" style="min-height: 340px; height: 340px;"></div>
        </x-metronic.card>
    </div>
    <div class="col-lg-5">
        <x-metronic.card title="Distribusi Stok">
            <div class="text-muted fs-7 mb-3">Komposisi stok tersedia, dipesan, dan rusak saat ini.</div>
            <div id="warehouse-stock-distribution" style="min-height: 340px; height: 340px;"></div>
        </x-metronic.card>
    </div>
</div>

<div class="row g-5">
    <div class="col-xl-8">
        <x-metronic.card title="Mutasi Besar Terbaru">
            <x-slot:toolbar>
                <span class="text-muted fs-7">Diperbarui {{ $dashboard['last_updated_at']->format('d/m/Y H:i:s') }}</span>
            </x-slot:toolbar>
            <div class="table-responsive">
                <table class="table table-row-dashed align-middle mb-0">
                    <thead>
                    <tr class="text-muted fw-bold text-uppercase fs-7">
                        <th>Waktu</th>
                        <th>Produk</th>
                        <th>Lokasi</th>
                        <th>Jenis</th>
                        <th class="text-end">Perubahan</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($dashboard['large_mutations'] as $mutation)
                        <tr>
                            <td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($mutation['occurred_at'])->format('d/m/Y H:i') }}</td>
                            <td>{{ $mutation['sku'] }} — {{ $mutation['product'] }}</td>
                            <td>{{ $mutation['location'] ?: '-' }}</td>
                            <td><span class="badge badge-light-primary">{{ str($mutation['mutation_type'])->replace('_', ' ')->title() }}</span></td>
                            <td class="text-end fw-bold">{{ qty($mutation['quantity_on_hand_change']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5"><x-metronic.empty-state title="Belum ada mutasi besar" description="Mutasi besar akan tampil setelah stok bergerak." /></td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-metronic.card>
    </div>

    <div class="col-xl-4">
        <x-metronic.card title="Cara Membaca Dashboard">
            <ul class="text-gray-700 mb-0 ps-5">
                @foreach($definitions as $definition)
                    <li class="mb-3">{{ $definition }}</li>
                @endforeach
            </ul>
        </x-metronic.card>
    </div>
</div>
