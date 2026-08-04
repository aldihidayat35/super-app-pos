@php
    $kpis = $dashboard['kpis'];
    $activeWarehouse = $activeWarehouse ?? null;
    $wlId = $activeWarehouse?->work_location_id;
    $stockUrl = $wlId ? route('warehouse.stocks.index', ['work_location_id' => $wlId, 'status' => 'critical']) : route('warehouse.stocks.index', ['status' => 'critical']);
    $transferUrl = route('warehouse.location-transfers.index');
    $poUrl = route('purchasing.purchase-orders.index');
    $b2bUrl = route('warehouse.b2b-orders.index');
    $opnameUrl = route('warehouse.stock-opnames.index');
    $receiptUrl = route('warehouse.goods-receipts.index');
    $mutationUrl = route('warehouse.stock-card.index', ['work_location_id' => $wlId]);
@endphp

@if (! $activeWarehouse)
    <div class="alert alert-warning d-flex align-items-center mb-5" role="alert">
        <i class="ki-outline ki-information-5 fs-2x text-warning me-4"></i>
        <div>
            <div class="fw-bold">Belum ada gudang yang dapat ditampilkan</div>
            <div>Akun ini belum memiliki akses ke gudang aktif. Hubungi administrator untuk memeriksa penugasan lokasi kerja.</div>
        </div>
    </div>
@endif

{{-- KPI Cards --}}
@include('warehouse.partials.kpi-grid')

{{-- Perlu Perhatian --}}
@if (($kpis['critical_count'] ?? 0) > 0 || ($kpis['empty_count'] ?? 0) > 0 || ($kpis['pending_po'] ?? 0) > 0 || ($kpis['pending_transfer'] ?? 0) > 0 || ($kpis['pending_order'] ?? 0) > 0 || ($kpis['open_opname'] ?? 0) > 0)
    <div class="card mb-6">
        <div class="card-header border-0 pt-5">
            <h3 class="card-title fw-bold">
                <i class="ki-outline ki-alert-triangle text-warning me-2"></i>
                Perlu Perhatian
                @php
                    $totalAlerts = ($kpis['critical_count'] ?? 0) + ($kpis['empty_count'] ?? 0) + ($kpis['pending_po'] ?? 0) + ($kpis['pending_transfer'] ?? 0) + ($kpis['pending_order'] ?? 0) + ($kpis['open_opname'] ?? 0);
                @endphp
                <span class="badge badge-light-danger fs-7 fw-bold ms-2">{{ $totalAlerts }}</span>
            </h3>
        </div>
        <div class="card-body pt-0">
            <div class="d-flex flex-column gap-3">
                @if(($kpis['critical_count'] ?? 0) > 0)
                    <a href="{{ $stockUrl }}" class="d-flex align-items-center gap-3 p-3 rounded bg-light-warning border border-warning border-opacity-25 text-hover-dark text-decoration-none">
                        <span class="symbol symbol-40px symbol-circle bg-light-danger">
                            <i class="ki-outline ki-warning fs-1 text-danger"></i>
                        </span>
                        <div class="flex-grow-1">
                            <div class="fw-bold text-gray-900">Stok Kritis / Kosong</div>
                            <div class="text-muted fs-7">
                                <span class="fw-semibold text-danger">{{ $kpis['critical_count'] + $kpis['empty_count'] }}</span>
                                produk membutuhkan perhatian
                            </div>
                        </div>
                        <i class="ki-outline ki-arrow-right fs-4 text-gray-400"></i>
                    </a>
                @endif
                @if(($kpis['pending_po'] ?? 0) > 0)
                    <a href="{{ $poUrl }}" class="d-flex align-items-center gap-3 p-3 rounded bg-light-warning border border-warning border-opacity-25 text-hover-dark text-decoration-none">
                        <span class="symbol symbol-40px symbol-circle bg-light-warning">
                            <i class="ki-outline ki-purchase fs-1 text-warning"></i>
                        </span>
                        <div class="flex-grow-1">
                            <div class="fw-bold text-gray-900">Purchase Order Pending</div>
                            <div class="text-muted fs-7">
                                <span class="fw-semibold text-warning">{{ $kpis['pending_po'] }}</span>
                                PO menunggu proses penerimaan
                            </div>
                        </div>
                        <i class="ki-outline ki-arrow-right fs-4 text-gray-400"></i>
                    </a>
                @endif
                @if(($kpis['pending_transfer'] ?? 0) > 0)
                    <a href="{{ $transferUrl }}" class="d-flex align-items-center gap-3 p-3 rounded bg-light-warning border border-warning border-opacity-25 text-hover-dark text-decoration-none">
                        <span class="symbol symbol-40px symbol-circle bg-light-warning">
                            <i class="ki-outline ki-arrow-right-left fs-1 text-warning"></i>
                        </span>
                        <div class="flex-grow-1">
                            <div class="fw-bold text-gray-900">Transfer Stok Pending</div>
                            <div class="text-muted fs-7">
                                <span class="fw-semibold text-warning">{{ $kpis['pending_transfer'] }}</span>
                                transfer dalam antrian atau proses
                            </div>
                        </div>
                        <i class="ki-outline ki-arrow-right fs-4 text-gray-400"></i>
                    </a>
                @endif
                @if(($kpis['pending_order'] ?? 0) > 0)
                    <a href="{{ $b2bUrl }}" class="d-flex align-items-center gap-3 p-3 rounded bg-light-warning border border-warning border-opacity-25 text-hover-dark text-decoration-none">
                        <span class="symbol symbol-40px symbol-circle bg-light-warning">
                            <i class="ki-outline ki-basket fs-1 text-warning"></i>
                        </span>
                        <div class="flex-grow-1">
                            <div class="fw-bold text-gray-900">Order B2B Memerlukan Tindakan</div>
                            <div class="text-muted fs-7">
                                <span class="fw-semibold text-warning">{{ $kpis['pending_order'] }}</span>
                                order dalam antrian gudang
                            </div>
                        </div>
                        <i class="ki-outline ki-arrow-right fs-4 text-gray-400"></i>
                    </a>
                @endif
                @if(($kpis['open_opname'] ?? 0) > 0)
                    <a href="{{ $opnameUrl }}" class="d-flex align-items-center gap-3 p-3 rounded bg-light-warning border border-warning border-opacity-25 text-hover-dark text-decoration-none">
                        <span class="symbol symbol-40px symbol-circle bg-light-primary">
                            <i class="ki-outline ki-clipboard fs-1 text-primary"></i>
                        </span>
                        <div class="flex-grow-1">
                            <div class="fw-bold text-gray-900">Stock Opname Terbuka</div>
                            <div class="text-muted fs-7">
                                <span class="fw-semibold text-primary">{{ $kpis['open_opname'] }}</span>
                                opname dalam draft atau penghitungan
                            </div>
                        </div>
                        <i class="ki-outline ki-arrow-right fs-4 text-gray-400"></i>
                    </a>
                @endif
                @if(($kpis['posted_receipts'] ?? 0) > 0)
                    <a href="{{ $receiptUrl }}" class="d-flex align-items-center gap-3 p-3 rounded bg-light-success border border-success border-opacity-25 text-hover-dark text-decoration-none">
                        <span class="symbol symbol-40px symbol-circle bg-light-success">
                            <i class="ki-outline ki-delivery fs-1 text-success"></i>
                        </span>
                        <div class="flex-grow-1">
                            <div class="fw-bold text-gray-900">Penerimaan Barang Diposting</div>
                            <div class="text-muted fs-7">
                                <span class="fw-semibold text-success">{{ $kpis['posted_receipts'] }}</span>
                                penerimaan periode ini
                            </div>
                        </div>
                        <i class="ki-outline ki-arrow-right fs-4 text-gray-400"></i>
                    </a>
                @endif
            </div>
        </div>
    </div>
@else
    <div class="card mb-6">
        <div class="card-body d-flex align-items-center py-6">
            <span class="symbol symbol-45px symbol-circle bg-light-success me-4">
                <i class="ki-outline ki-check fs-2x text-success"></i>
            </span>
            <div>
                <div class="fw-bold text-gray-900 fs-5">Semua Kondisi Normal</div>
                <div class="text-muted fs-7">Tidak ada peringatan stok, dokumen pending, atau aktivitas kritis pada periode ini.</div>
            </div>
        </div>
    </div>
@endif

{{-- Charts Row --}}
<div class="row g-5 mb-6">
    <div class="col-xl-8">
        <x-metronic.card title="Pergerakan Stok" flush>
            <x-slot:toolbar>
                <div class="btn-group btn-group-sm" role="group" id="warehouse-movement-period" aria-label="Periode pergerakan stok">
                    <button type="button" class="btn btn-light-primary active" data-period="daily">Harian</button>
                    <button type="button" class="btn btn-light-primary" data-period="monthly">Bulanan</button>
                    <button type="button" class="btn btn-light-primary" data-period="yearly">Tahunan</button>
                </div>
            </x-slot:toolbar>
            <div class="p-5" style="min-height: 340px;">
                <div id="warehouse-daily-movement" style="min-height: 300px; height: 300px;"></div>
            </div>
        </x-metronic.card>
    </div>
    <div class="col-xl-4">
        <x-metronic.card title="Distribusi Stok" flush>
            <div class="p-5">
                <div id="warehouse-stock-distribution" style="min-height: 300px; height: 300px;"></div>
            </div>
            <div class="card-footer bg-body border-top-0 py-4">
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="d-flex align-items-center gap-2">
                            <span class="symbol symbol-sm symbol-circle bg-light-success">
                                <span class="symbol-icon bg-success"></span>
                            </span>
                            <span class="text-muted fs-7">Tersedia</span>
                        </span>
                        <span class="fw-bold text-gray-900 fs-7">{{ qty($kpis['available_quantity'] ?? 0) }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="d-flex align-items-center gap-2">
                            <span class="symbol symbol-sm symbol-circle bg-light-warning">
                                <span class="symbol-icon bg-warning"></span>
                            </span>
                            <span class="text-muted fs-7">Dipesan</span>
                        </span>
                        <span class="fw-bold text-gray-900 fs-7">{{ qty($kpis['reserved_quantity'] ?? 0) }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="d-flex align-items-center gap-2">
                            <span class="symbol symbol-sm symbol-circle bg-light-danger">
                                <span class="symbol-icon bg-danger"></span>
                            </span>
                            <span class="text-muted fs-7">Rusak</span>
                        </span>
                        <span class="fw-bold text-gray-900 fs-7">{{ qty($kpis['damaged_quantity'] ?? 0) }}</span>
                    </div>
                </div>
            </div>
        </x-metronic.card>
    </div>
</div>

{{-- Bottom Section --}}
<div class="row g-5">
    <div class="col-xl-5">
        <x-metronic.card title="Peringatan Stok" flush>
            <x-slot:toolbar>
                <a href="{{ $stockUrl }}" class="btn btn-sm btn-light-primary">
                    <i class="ki-outline ki-eye fs-6"></i> Lihat Semua
                </a>
            </x-slot:toolbar>
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-7 gy-5 mb-0">
                    <thead>
                        <tr class="text-muted fw-bold text-uppercase fs-7 border-bottom-2">
                            <th class="ps-5">Produk</th>
                            <th class="text-end">Tersedia</th>
                            <th class="text-end">Min</th>
                            <th class="text-end pe-5">Status</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold fs-6">
                        @forelse ($dashboard['stock_alerts'] as $alert)
                            <tr class="border-bottom">
                                <td class="ps-5">
                                    <a href="{{ route('warehouse.stock-card.index', ['product_id' => $alert['product_id'], 'work_location_id' => $wlId]) }}" class="text-gray-800 text-hover-primary">
                                        <span class="d-block fw-bold">{{ $alert['product'] }}</span>
                                        <span class="text-muted fs-7">{{ $alert['sku'] }}</span>
                                    </a>
                                </td>
                                <td class="text-end pe-2">
                                    <span class="badge {{ $alert['status'] === 'empty' ? 'badge-light-danger' : 'badge-light-warning' }} fs-7">{{ qty($alert['available']) }}</span>
                                </td>
                                <td class="text-end pe-2">{{ qty($alert['minimum_stock']) }}</td>
                                <td class="text-end pe-5">
                                    @if($alert['status'] === 'empty')
                                        <span class="badge badge-light-danger">Kosong</span>
                                    @else
                                        <span class="badge badge-light-warning">Kritis</span>
                                    @endif
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

    <div class="col-xl-4">
        <x-metronic.card title="Produk Paling Bergerak" flush>
            <x-slot:toolbar>
                <span class="text-muted fs-7">{{ count($dashboard['top_movers'] ?? []) }} produk</span>
            </x-slot:toolbar>
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-7 gy-5 mb-0">
                    <thead>
                        <tr class="text-muted fw-bold text-uppercase fs-7 border-bottom-2">
                            <th class="ps-5">Produk</th>
                            <th class="text-end pe-5">Mutasi</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold fs-6">
                        @forelse ($dashboard['top_movers'] as $item)
                            <tr class="border-bottom">
                                <td class="ps-5">
                                    <a href="{{ route('warehouse.stock-card.index', ['product_id' => $item['product_id'], 'work_location_id' => $wlId]) }}" class="text-gray-800 text-hover-primary">
                                        <span class="d-block">{{ $item['product'] }}</span>
                                        <span class="text-muted fs-7">{{ $item['sku'] }}</span>
                                    </a>
                                </td>
                                <td class="text-end pe-5">
                                    <span class="badge badge-light-primary">{{ qty($item['total_movement']) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2">
                                    <x-metronic.empty-state title="Belum ada pergerakan" description="Produk bergerak akan muncul di sini saat ada mutasi stok." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-metronic.card>
    </div>

    <div class="col-xl-3">
        <x-metronic.card title="Perlu Restock" flush>
            <x-slot:toolbar>
                <a href="{{ $poUrl }}" class="btn btn-sm btn-light-warning">
                    <i class="ki-outline ki-plus fs-6"></i> Buat PO
                </a>
            </x-slot:toolbar>
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-7 gy-5 mb-0">
                    <thead>
                        <tr class="text-muted fw-bold text-uppercase fs-7 border-bottom-2">
                            <th class="ps-5">Produk</th>
                            <th class="text-end pe-5">Tersedia</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold fs-6">
                        @forelse ($dashboard['restock_needed'] as $item)
                            <tr class="border-bottom">
                                <td class="ps-5">
                                    <a href="{{ route('warehouse.stock-card.index', ['product_id' => $item['product_id'], 'work_location_id' => $wlId]) }}" class="text-gray-800 text-hover-primary">
                                        <span class="d-block">{{ $item['product'] }}</span>
                                        <span class="text-muted fs-7">{{ $item['sku'] }}</span>
                                    </a>
                                </td>
                                <td class="text-end pe-5">
                                    <span class="badge badge-light-danger">{{ qty($item['available']) }} / {{ qty($item['minimum_stock']) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2">
                                    <x-metronic.empty-state title="Tidak ada yang perlu restock" description="Semua produk sudah memenuhi batas minimum stok." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-metronic.card>
    </div>
</div>

<div class="row g-5 mt-5">
    <div class="col-xl-8">
        <x-metronic.card title="Mutasi Besar Terbaru" flush>
            <x-slot:toolbar>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted fs-7">Diperbarui {{ $dashboard['last_updated_at']->format('d/m/Y H:i:s') }}</span>
                    <a href="{{ $mutationUrl }}" class="btn btn-sm btn-light-primary">Lihat Semua</a>
                </div>
            </x-slot:toolbar>
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-7 gy-5 mb-0">
                    <thead>
                        <tr class="text-muted fw-bold text-uppercase fs-7 border-bottom-2">
                            <th class="ps-5">Waktu</th>
                            <th>Produk</th>
                            <th>Jenis</th>
                            <th>Lokasi</th>
                            <th class="text-end pe-5">Perubahan</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold fs-6">
                        @forelse ($dashboard['large_mutations'] as $mutation)
                            <tr class="border-bottom">
                                <td class="text-nowrap ps-5 text-muted">{{ \Illuminate\Support\Carbon::parse($mutation['occurred_at'])->format('d/m/Y H:i') }}</td>
                                <td>
                                    <a href="{{ route('warehouse.stock-card.index', ['product_id' => $mutation['product_id'] ?? 0, 'work_location_id' => $wlId]) }}" class="text-gray-800 text-hover-primary">
                                        <span class="d-block fw-bold">{{ $mutation['product'] }}</span>
                                        <span class="text-muted fs-7">{{ $mutation['sku'] }}</span>
                                    </a>
                                </td>
                                <td>
                                    @php
                                        $typeColors = [
                                            'receive' => 'success',
                                            'issue' => 'danger',
                                            'transfer_in' => 'info',
                                            'transfer_out' => 'warning',
                                            'adjustment' => 'secondary',
                                            'return_in' => 'success',
                                            'return_out' => 'danger',
                                        ];
                                        $typeName = str($mutation['mutation_type'])->replace('_', ' ')->title();
                                        $typeColor = $typeColors[$mutation['mutation_type']] ?? 'primary';
                                    @endphp
                                    <span class="badge badge-light-{{ $typeColor }}">{{ $typeName }}</span>
                                </td>
                                <td class="text-muted">{{ $mutation['location'] ?: '-' }}</td>
                                <td class="text-end pe-5">
                                    @php
                                        $change = (float)($mutation['quantity_on_hand_change'] ?? 0);
                                    @endphp
                                    <span class="{{ $change >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ $change >= 0 ? '+' : '' }}{{ qty($mutation['quantity_on_hand_change']) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <x-metronic.empty-state title="Belum ada mutasi besar" description="Mutasi besar akan tampil setelah stok bergerak." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-metronic.card>
    </div>

    <div class="col-xl-4">
        <x-metronic.card title="Top 5 Stok Terbanyak" flush>
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-7 gy-5 mb-0">
                    <thead>
                        <tr class="text-muted fw-bold text-uppercase fs-7 border-bottom-2">
                            <th class="ps-5">Produk</th>
                            <th class="text-end pe-5">Qty</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold fs-6">
                        @forelse ($dashboard['top_stocked_products'] as $item)
                            <tr class="border-bottom">
                                <td class="ps-5">
                                    <a href="{{ route('warehouse.stock-card.index', ['product_id' => $item['product_id'], 'work_location_id' => $wlId]) }}" class="text-gray-800 text-hover-primary">
                                        <span class="d-block">{{ $item['product'] }}</span>
                                        <span class="text-muted fs-7">{{ $item['sku'] }}</span>
                                    </a>
                                </td>
                                <td class="text-end pe-5">
                                    <span class="badge badge-light-primary">{{ qty($item['quantity']) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2">
                                    <x-metronic.empty-state title="Belum ada data" description="Data produk dengan stok terbesar akan muncul di sini." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-metronic.card>

        <x-metronic.card title="Stok Menganggur" flush class="mt-5">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-7 gy-5 mb-0">
                    <thead>
                        <tr class="text-muted fw-bold text-uppercase fs-7 border-bottom-2">
                            <th class="ps-5">Produk</th>
                            <th class="text-end pe-5">Nilai</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold fs-6">
                        @forelse ($dashboard['dead_stock'] as $item)
                            <tr class="border-bottom">
                                <td class="ps-5">
                                    <a href="{{ route('warehouse.stock-card.index', ['product_id' => $item['product_id'], 'work_location_id' => $wlId]) }}" class="text-gray-800 text-hover-primary">
                                        <span class="d-block">{{ $item['product'] }}</span>
                                        <span class="text-muted fs-7">{{ $item['sku'] }}</span>
                                    </a>
                                </td>
                                <td class="text-end pe-5">
                                    <span class="badge badge-light-warning">{{ $item['stock_value'] }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2">
                                    <x-metronic.empty-state title="Tidak ada stok menganggur" description="Semua stok memiliki pergerakan pada periode ini." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-metronic.card>
    </div>
</div>

{{-- Help Offcanvas --}}
<div class="offcanvas offcanvas-end" tabindex="-1" id="warehouse-dashboard-help" aria-labelledby="warehouse-dashboard-help-label">
    <div class="offcanvas-header">
        <h5 id="warehouse-dashboard-help-label">Cara Membaca Dashboard</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <ul class="list-group list-group-flush">
            @foreach($definitions as $definition)
                <li class="list-group-item border-0 ps-0 pe-0">
                    <div class="d-flex align-items-start gap-2">
                        <i class="ki-outline ki-information-2 text-primary mt-1"></i>
                        <span class="text-gray-700">{{ $definition }}</span>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</div>
