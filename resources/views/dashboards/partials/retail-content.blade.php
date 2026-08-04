@php
    $kpis = $dashboard['kpis'];
    $charts = $dashboard['charts'];
    $topProducts = collect($charts['top_products'] ?? []);
    $slowProducts = collect($charts['slow_products'] ?? []);
    $maxTopQuantity = max(1, (float) $topProducts->max('quantity'));
    $targetProgress = min(100, max(0, (float) ($kpis['target_achievement'] ?? 0)));
@endphp

@if (! $activeBranch)
    <div class="alert alert-warning d-flex align-items-center mb-5" role="alert">
        <i class="ki-outline ki-information-5 fs-2x text-warning me-4"></i>
        <div>
            <div class="fw-bold">Belum ada cabang yang dapat ditampilkan</div>
            <div>Akun ini belum memiliki akses ke cabang aktif. Hubungi administrator untuk memeriksa penugasan lokasi kerja.</div>
        </div>
    </div>
@endif

<div class="card border-0 shadow-sm mb-5 overflow-hidden">
    <div class="card-body p-6 p-lg-8 bg-light-primary">
        <div class="row align-items-center g-5">
            <div class="col-xl-7">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-60px symbol-circle bg-primary me-5">
                        <span class="symbol-label bg-primary"><i class="ki-outline ki-shop fs-2x text-white"></i></span>
                    </div>
                    <div>
                        <div class="text-muted fw-semibold fs-7 text-uppercase mb-1">Kinerja cabang aktif</div>
                        <h2 class="fw-bold text-gray-900 mb-1">{{ $activeBranch ? $activeBranch->code.' — '.$activeBranch->name : 'Belum ada cabang' }}</h2>
                        <div class="text-gray-600">Periode {{ \Illuminate\Support\Carbon::parse($filters['start_date'])->format('d/m/Y') }}–{{ \Illuminate\Support\Carbon::parse($filters['end_date'])->format('d/m/Y') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="d-flex justify-content-between align-items-end mb-2">
                    <div>
                        <div class="text-muted fw-semibold fs-7">Pencapaian Target Penjualan</div>
                        <div class="fs-3 fw-bold text-gray-900">{{ qty($kpis['target_achievement'], 2, '0') }}%</div>
                    </div>
                    <div class="text-end">
                        <div class="text-muted fs-8">Target</div>
                        <div class="fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($kpis['sales_target']) }}</div>
                    </div>
                </div>
                <div class="progress h-8px bg-white">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $targetProgress }}%" aria-valuenow="{{ $targetProgress }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                @if ((float) $kpis['target_achievement'] > 100)
                    <div class="text-success fw-semibold fs-8 mt-2">Target terlampaui {{ qty((float) $kpis['target_achievement'] - 100, 2, '0') }}%</div>
                @endif
            </div>
        </div>
    </div>
</div>

@php
    $statCards = [
        ['label' => 'Omzet', 'value' => \App\Support\CurrencyFormatter::rupiah($kpis['revenue']), 'icon' => 'ki-dollar', 'color' => 'primary', 'help' => 'Penjualan selesai pada periode aktif.'],
        ['label' => 'Transaksi', 'value' => $kpis['transaction_count'], 'icon' => 'ki-receipt-square', 'color' => 'info', 'help' => 'Jumlah nota selesai dan retur.'],
        ['label' => 'Rata-rata Nota', 'value' => \App\Support\CurrencyFormatter::rupiah($kpis['average_ticket']), 'icon' => 'ki-chart-line-up-2', 'color' => 'success', 'help' => 'Rata-rata nilai setiap transaksi.'],
        ['label' => 'Stok Tersedia', 'value' => qty($kpis['available_stock']), 'icon' => 'ki-package', 'color' => 'primary', 'help' => 'Stok fisik dikurangi reserved dan rusak.'],
        ['label' => 'Stok Kritis / Kosong', 'value' => $kpis['critical_stock_count'].' / '.$kpis['empty_stock_count'], 'icon' => 'ki-information-5', 'color' => 'danger', 'help' => 'Produk di batas minimum / tanpa stok tersedia.'],
        ['label' => 'Shift Aktif', 'value' => $kpis['active_shift_count'], 'icon' => 'ki-time', 'color' => 'success', 'help' => 'Shift buka atau menunggu closing.'],
        ['label' => 'Closing Pending', 'value' => $kpis['closing_pending_count'], 'icon' => 'ki-verify', 'color' => 'warning', 'help' => 'Closing yang menunggu pemeriksaan.'],
        ['label' => 'Selisih Kas', 'value' => \App\Support\CurrencyFormatter::rupiah($kpis['cash_difference']), 'icon' => 'ki-wallet', 'color' => ((float) $kpis['cash_difference'] === 0.0 ? 'success' : 'danger'), 'help' => 'Selisih kas pada closing periode aktif.'],
        ['label' => 'Piutang Terbit', 'value' => \App\Support\CurrencyFormatter::rupiah($kpis['receivable_today']), 'icon' => 'ki-credit-cart', 'color' => 'warning', 'help' => 'Piutang retail yang diterbitkan pada periode.'],
        ['label' => 'Transaksi Void', 'value' => $kpis['void_count'], 'icon' => 'ki-cross-circle', 'color' => 'danger', 'help' => 'Transaksi dengan void disetujui.'],
        ['label' => 'Nilai Retur', 'value' => \App\Support\CurrencyFormatter::rupiah($kpis['return_amount']), 'icon' => 'ki-arrow-circle-left', 'color' => 'warning', 'help' => 'Nilai pengembalian pada periode aktif.'],
    ];
    if ($canViewSensitiveMargin) {
        array_splice($statCards, 1, 0, [[
            'label' => 'Margin',
            'value' => \App\Support\CurrencyFormatter::rupiah($kpis['margin']).' · '.qty($kpis['margin_percent'], 2, '0').'%',
            'icon' => 'ki-graph-up',
            'color' => 'success',
            'help' => 'Margin aktual berdasarkan snapshot HPP.',
        ]]);
    }
@endphp

<div class="row g-5 mb-5">
    @foreach ($statCards as $stat)
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body p-5">
                    <div class="d-flex align-items-start justify-content-between mb-4">
                        <div class="symbol symbol-45px">
                            <span class="symbol-label bg-light-{{ $stat['color'] }}"><i class="ki-outline {{ $stat['icon'] }} fs-2 text-{{ $stat['color'] }}"></i></span>
                        </div>
                        <span class="badge badge-light-{{ $stat['color'] }}">Periode aktif</span>
                    </div>
                    <div class="fs-3 fw-bold text-gray-900 mb-1">{{ $stat['value'] }}</div>
                    <div class="fw-semibold text-gray-700 mb-1">{{ $stat['label'] }}</div>
                    <div class="text-muted fs-8">{{ $stat['help'] }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-5 mb-5">
    <div class="col-xl-8">
        <x-metronic.card title="Tren Omzet">
            <x-slot:toolbar>
                <div class="btn-group btn-group-sm" id="retail-range-buttons" role="group" aria-label="Rentang grafik">
                    @foreach (['daily' => 'Harian', 'monthly' => 'Bulanan', 'yearly' => 'Tahunan'] as $key => $label)
                        <button type="button" class="btn btn-light-primary {{ ($filters['range'] ?? 'daily') === $key ? 'active' : '' }}" data-range="{{ $key }}">{{ $label }}</button>
                    @endforeach
                </div>
            </x-slot:toolbar>
            <div class="text-muted fs-7 mb-3">Perubahan omzet berdasarkan transaksi POS yang selesai.</div>
            <div id="retail-revenue-chart" style="min-height: 340px; height: 340px;"></div>
        </x-metronic.card>
    </div>
    <div class="col-xl-4">
        <x-metronic.card title="Metode Pembayaran">
            <div class="text-muted fs-7 mb-3">Komposisi pembayaran transaksi pada periode aktif.</div>
            <div id="retail-payment-chart" style="min-height: 340px; height: 340px;"></div>
        </x-metronic.card>
    </div>
</div>

<div class="row g-5 mb-5">
    <div class="col-xl-7">
        <x-metronic.card title="Volume Transaksi">
            <div class="text-muted fs-7 mb-3">Jumlah transaksi berdasarkan rentang grafik yang dipilih.</div>
            <div id="retail-transactions-chart" style="min-height: 315px; height: 315px;"></div>
        </x-metronic.card>
    </div>
    <div class="col-xl-5">
        <x-metronic.card title="Produk Terlaris">
            <x-slot:toolbar><span class="badge badge-light-success">Top {{ $topProducts->count() }}</span></x-slot:toolbar>
            <div class="mh-325px overflow-auto pe-2">
                @forelse ($topProducts as $index => $product)
                    @php($productProgress = min(100, ((float) $product['quantity'] / $maxTopQuantity) * 100))
                    <div class="mb-5">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center min-w-0">
                                <span class="badge badge-circle badge-light-primary me-3">{{ $index + 1 }}</span>
                                <div class="text-truncate">
                                    <div class="fw-bold text-gray-800 text-truncate">{{ $product['product'] }}</div>
                                    <div class="text-muted fs-8">{{ $product['sku'] }}</div>
                                </div>
                            </div>
                            <div class="text-end ms-3">
                                <div class="fw-bold">{{ qty($product['quantity']) }} unit</div>
                                <div class="text-muted fs-8">{{ \App\Support\CurrencyFormatter::rupiah($product['revenue']) }}</div>
                            </div>
                        </div>
                        <div class="progress h-4px"><div class="progress-bar bg-success" style="width: {{ $productProgress }}%"></div></div>
                    </div>
                @empty
                    <x-metronic.empty-state title="Belum ada produk terjual" description="Produk terlaris muncul setelah transaksi POS selesai." />
                @endforelse
            </div>
        </x-metronic.card>
    </div>
</div>

<div class="row g-5 mb-5">
    <div class="col-xl-7">
        <x-metronic.card title="Peringatan Stok Cabang">
            <x-slot:toolbar>
                @can('stock.view')
                    <a href="{{ route('warehouse.stocks.index', ['work_location_id' => $activeBranch?->work_location_id, 'status' => 'critical']) }}" class="btn btn-sm btn-light-danger">Lihat Saldo</a>
                @endcan
            </x-slot:toolbar>
            <div class="table-responsive">
                <table class="table table-row-dashed align-middle mb-0">
                    <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th class="text-end">Tersedia</th><th class="text-end">Minimum</th><th class="text-end">Status</th></tr></thead>
                    <tbody>
                    @forelse ($dashboard['stock_alerts'] as $alert)
                        <tr>
                            <td><div class="fw-bold text-gray-800">{{ $alert['product'] }}</div><div class="text-muted fs-8">{{ $alert['sku'] }}</div></td>
                            <td class="text-end fw-semibold">{{ qty($alert['available']) }}</td>
                            <td class="text-end">{{ qty($alert['minimum_stock']) }}</td>
                            <td class="text-end"><span class="badge {{ $alert['status'] === 'empty' ? 'badge-light-danger' : 'badge-light-warning' }}">{{ $alert['status'] === 'empty' ? 'Kosong' : 'Kritis' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-metronic.empty-state title="Stok cabang aman" description="Tidak ada produk kritis atau kosong pada cabang aktif." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-metronic.card>
    </div>
    <div class="col-xl-5">
        <x-metronic.card title="Produk Belum Bergerak">
            <div class="text-muted fs-7 mb-4">Produk bersaldo yang belum terjual dalam periode aktif.</div>
            <div class="mh-350px overflow-auto pe-2">
                @forelse ($slowProducts as $product)
                    <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
                        <div class="min-w-0 me-3"><div class="fw-bold text-gray-800 text-truncate">{{ $product['product'] }}</div><div class="text-muted fs-8">{{ $product['sku'] }}</div></div>
                        <div class="text-end text-nowrap"><div class="fw-bold">{{ qty($product['quantity']) }} unit</div>@if(isset($product['stock_value']))<div class="text-muted fs-8">{{ \App\Support\CurrencyFormatter::rupiah($product['stock_value']) }}</div>@endif</div>
                    </div>
                @empty
                    <x-metronic.empty-state title="Tidak ada stok lambat" description="Seluruh produk bersaldo memiliki pergerakan pada periode ini." />
                @endforelse
            </div>
        </x-metronic.card>
    </div>
</div>

<div class="row g-5 mb-5">
    <div class="col-xl-8">
        <x-metronic.card title="Aktivitas Penjualan Terbaru">
            <x-slot:toolbar><span class="text-muted fs-8">Diperbarui {{ $dashboard['last_updated_at']->format('d/m/Y H:i:s') }}</span></x-slot:toolbar>
            <div class="table-responsive">
                <table class="table table-row-dashed align-middle mb-0">
                    <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Nota</th><th>Kasir</th><th>Waktu</th><th>Status</th><th class="text-end">Total</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($dashboard['recent_sales'] as $sale)
                        <tr>
                            <td class="fw-bold text-gray-800">{{ $sale['number'] }}</td>
                            <td>{{ $sale['cashier'] ?: '-' }}</td>
                            <td class="text-nowrap">{{ $sale['completed_at'] ? \Illuminate\Support\Carbon::parse($sale['completed_at'])->format('d/m/Y H:i') : '-' }}</td>
                            <td><x-metronic.status-badge :status="$sale['status']" /></td>
                            <td class="text-end fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($sale['amount']) }}</td>
                            <td class="text-end">@can('pos.view')<a href="{{ route('retail.sales.show', $sale['id']) }}" class="btn btn-sm btn-icon btn-light-primary" title="Lihat detail"><i class="ki-outline ki-eye fs-5"></i></a>@endcan</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-metronic.empty-state title="Belum ada penjualan" description="Aktivitas tampil setelah transaksi POS selesai." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-metronic.card>
    </div>
    <div class="col-xl-4">
        <x-metronic.card title="Shift Berjalan">
            <x-slot:toolbar>@can('cash_shifts.view')<a href="{{ route('retail.shifts.index') }}" class="btn btn-sm btn-light-primary">Semua Shift</a>@endcan</x-slot:toolbar>
            @forelse ($dashboard['active_shifts'] as $shift)
                <div class="d-flex align-items-center border-bottom pb-4 mb-4">
                    <div class="symbol symbol-40px me-3"><span class="symbol-label bg-light-success"><i class="ki-outline ki-user-tick fs-3 text-success"></i></span></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-bold text-gray-800 text-truncate">{{ $shift['cashier'] ?: 'Kasir' }}</div>
                        <div class="text-muted fs-8">{{ $shift['number'] }} · {{ $shift['opened_at'] ? \Illuminate\Support\Carbon::parse($shift['opened_at'])->format('d/m H:i') : '-' }}</div>
                    </div>
                    <x-metronic.status-badge :status="$shift['status']" />
                </div>
            @empty
                <x-metronic.empty-state title="Tidak ada shift aktif" description="Buka shift sebelum memulai transaksi POS." />
            @endforelse
        </x-metronic.card>
    </div>
</div>

<div class="row g-5">
    <div class="col-xl-7">
        <x-metronic.card title="Rincian Metode Pembayaran">
            <div class="table-responsive">
                <table class="table table-row-dashed align-middle mb-0">
                    <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Metode</th><th class="text-end">Nilai</th></tr></thead>
                    <tbody>
                    @forelse ($charts['payment_methods'] ?? [] as $payment)
                        <tr><td class="fw-semibold text-gray-800">{{ str($payment['label'])->replace('_', ' ')->title() }}</td><td class="text-end fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($payment['value']) }}</td></tr>
                    @empty
                        <tr><td colspan="2"><x-metronic.empty-state title="Belum ada pembayaran" description="Metode pembayaran muncul setelah transaksi POS." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-metronic.card>
    </div>
    <div class="col-xl-5">
        <x-metronic.card title="Cara Membaca Data">
            <ul class="text-gray-700 mb-0 ps-5">
                @foreach ($definitions as $definition)
                    <li class="mb-3">{{ $definition }}</li>
                @endforeach
            </ul>
        </x-metronic.card>
    </div>
</div>
