@php
    use App\Support\CurrencyFormatter;

    $summary            = $report['summary'] ?? [];
    $locationBreakdown  = $report['location_breakdown'] ?? [];
    $criticalStockItems = $report['critical_stock_items'] ?? [];
    $topValuableItems   = $report['top_valuable_items'] ?? [];
    $allInventoryRows   = $report['rows'] ?? [];
    $locationList       = $workLocations ?? $locations ?? [];

    $periodLabel = \Carbon\Carbon::parse($filters['start_date'])->translatedFormat('d M Y')
                 .' – '
                 .\Carbon\Carbon::parse($filters['end_date'])->translatedFormat('d M Y');

    $money = fn($v): string => $v === null ? 'Akses terbatas' : CurrencyFormatter::rupiah((string)$v);
    $fmtPercent = fn($v): string => $v === null ? '0%' : str_replace('.', ',', (string)$v).'%';

    $stockVal     = (float) ($summary['stock_value'] ?? 0);
    $totalSku     = (int) ($summary['total_products'] ?? 0);
    $onHandQty    = (float) ($summary['on_hand_quantity'] ?? 0);
    $availQty     = (float) ($summary['available_quantity'] ?? 0);
    $reservedQty  = (float) ($summary['reserved_quantity'] ?? 0);
    $damagedQty   = (float) ($summary['damaged_quantity'] ?? 0);
    $criticalCnt  = (int) ($summary['critical_count'] ?? 0);
    $emptyCnt     = (int) ($summary['empty_count'] ?? 0);
    $openOpname   = (int) ($summary['open_opname'] ?? 0);

    // Calculate composition percentages
    $availPct    = $onHandQty > 0 ? round(($availQty / $onHandQty) * 100, 1) : 0;
    $reservedPct = $onHandQty > 0 ? round(($reservedQty / $onHandQty) * 100, 1) : 0;
    $damagedPct  = $onHandQty > 0 ? round(($damagedQty / $onHandQty) * 100, 1) : 0;
@endphp

@include('reports.profiles.owner._executive-overview-styles')

<style>
/* ══════════════════════════════════════════════════════════════════════════
   CUSTOM STYLES FOR EXECUTIVE INVENTORY VALUATION & STOCK HEALTH
   ══════════════════════════════════════════════════════════════════════════ */
.ei-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 13px;
}
.ei-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 11px;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: .06em;
    padding: 12px 16px;
    border-bottom: 1px solid var(--line);
}
.ei-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #edf2f7;
    color: var(--dark);
    vertical-align: middle;
}
.ei-table tr:hover td {
    background: #fbfcfe;
}
.composition-bar {
    display: flex;
    height: 12px;
    border-radius: 6px;
    overflow: hidden;
    background: #e2e8f0;
}
.composition-segment.available { background: #078455; }
.composition-segment.reserved  { background: #102a4b; }
.composition-segment.damaged   { background: #b44136; }
</style>

<div class="gudang-dashboard-wrap">

    {{-- HERO SECTION --}}
    <header class="hero" aria-labelledby="title">
        <svg class="hero-decor" viewBox="0 0 460 210" aria-hidden="true">
            <defs>
                <pattern id="inventory-grid" width="22" height="22" patternUnits="userSpaceOnUse"><path d="M22 0H0v22" stroke="#b6cce8" stroke-opacity=".12"/></pattern>
            </defs>
            <rect width="460" height="210" fill="url(#inventory-grid)"/>
        </svg>

        <div>
            <div class="eyebrow">
                <i class="ki-outline ki-cube-2 fs-6 text-warning me-1"></i>
                <span>EXECUTIVE INVENTORY &amp; VALUATION</span>
            </div>
            <h1 id="title">Nilai &amp; Kesehatan Stok</h1>
            <p>Valuasi Total Aset Modal Persediaan (HPP), Komposisi Fisik Stok, dan Mitigasi Risiko Stok Kritis.</p>

            {{-- 8-MODULE EXECUTIVE NAVIGATION PILLS --}}
            <div class="d-flex align-items-center gap-2 mt-3 flex-wrap">
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'overview', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash clear py-1 px-3" style="min-height:30px; font-size:11px; background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.22);">
                    <i class="ki-outline ki-home-2 fs-7 me-1"></i> Ringkasan
                </a>
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'income', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash clear py-1 px-3" style="min-height:30px; font-size:11px; background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.22);">
                    <i class="ki-outline ki-chart-line-up fs-7 me-1"></i> Laba Rugi
                </a>
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'profitability', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash clear py-1 px-3" style="min-height:30px; font-size:11px; background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.22);">
                    <i class="ki-outline ki-percentage fs-7 me-1"></i> Margin
                </a>
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'inventory', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash gold py-1 px-3" style="min-height:30px; font-size:11px;">
                    <i class="ki-outline ki-cube-2 fs-7 me-1"></i> Stok
                </a>
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'purchasing', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash clear py-1 px-3" style="min-height:30px; font-size:11px; background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.22);">
                    <i class="ki-outline ki-truck fs-7 me-1"></i> Pembelian
                </a>
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'receivables', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash clear py-1 px-3" style="min-height:30px; font-size:11px; background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.22);">
                    <i class="ki-outline ki-wallet fs-7 me-1"></i> Piutang
                </a>
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'locations', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash clear py-1 px-3" style="min-height:30px; font-size:11px; background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.22);">
                    <i class="ki-outline ki-shop fs-7 me-1"></i> Cabang
                </a>
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'tax', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash clear py-1 px-3" style="min-height:30px; font-size:11px; background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.22);">
                    <i class="ki-outline ki-shield-tick fs-7 me-1"></i> Pajak
                </a>
            </div>
        </div>

        <div class="hero-actions">
            <div class="period-chip">
                <i class="ki-outline ki-calendar fs-6 me-1"></i>
                <span>{{ $periodLabel }}</span>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                <button type="button" class="btn-dash clear" onclick="window.print()">
                    <i class="ki-outline ki-printer fs-6 me-1"></i>
                    <span>Cetak A4</span>
                </button>

                @if($canExport)
                    <form method="POST" action="{{ route('reports.profiles.exports.store', ['profile'=>'owner','report'=>'inventory']) }}" class="d-inline-flex gap-2">
                        @csrf
                        @foreach(request()->except(['format','_token']) as $n => $v)
                            @if(is_scalar($v))<input type="hidden" name="{{ $n }}" value="{{ $v }}">@endif
                        @endforeach
                        <input type="hidden" name="format" value="pdf">
                        <button type="submit" class="btn-dash green">
                            <i class="ki-outline ki-file-down fs-6 me-1"></i>
                            <span>PDF</span>
                        </button>
                    </form>

                    <form method="POST" action="{{ route('reports.profiles.exports.store', ['profile'=>'owner','report'=>'inventory']) }}" class="d-inline-flex">
                        @csrf
                        @foreach(request()->except(['format','_token']) as $n => $v)
                            @if(is_scalar($v))<input type="hidden" name="{{ $n }}" value="{{ $v }}">@endif
                        @endforeach
                        <input type="hidden" name="format" value="xlsx">
                        <button type="submit" class="btn-dash gold" title="Unduh Spreadsheet Excel">
                            <span>Excel</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </header>

    {{-- FILTER CARD --}}
    <form method="GET" action="{{ route('reports.profiles.owner.show', ['report' => 'inventory']) }}" class="filters mb-5">
        <div class="field">
            <label for="f-start">Tanggal Mulai</label>
            <div class="input-wrap">
                <input type="date" id="f-start" name="start_date" value="{{ $filters['start_date'] }}">
            </div>
        </div>

        <div class="field">
            <label for="f-end">Tanggal Selesai</label>
            <div class="input-wrap">
                <input type="date" id="f-end" name="end_date" value="{{ $filters['end_date'] }}">
            </div>
        </div>

        <div class="field">
            <label for="f-loc">Lokasi Persediaan</label>
            <div class="input-wrap">
                <select id="f-loc" name="work_location_id">
                    <option value="">Semua Lokasi (Gudang &amp; Cabang Toko)</option>
                    @foreach($locationList as $loc)
                        <option value="{{ $loc->id }}" @selected((int)($selectedLocation?->id) === $loc->id)>
                            {{ $loc->name }} ({{ strtoupper($loc->type ?? 'LOKASI') }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="field" style="max-width: 140px;">
            <label>&nbsp;</label>
            <button type="submit" class="btn-dash navy w-100 justify-content-center">
                <i class="ki-outline ki-filter fs-6 me-1"></i> Terapkan
            </button>
        </div>
    </form>

    {{-- 4 EXECUTIVE KPI HERO CARDS --}}
    <div class="kpi-grid mb-6">
        {{-- Card 1: Valuasi Aset HPP --}}
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-tag gold">MODAL ASET HPP</span>
                <i class="ki-outline ki-wallet fs-3 text-warning"></i>
            </div>
            <div class="metric-val text-gray-900">{{ $money($stockVal) }}</div>
            <div class="metric-note">
                Total Modal Kerja Tertanam dalam Stok
                <div class="d-flex align-items-center gap-1 mt-1 fs-9 text-muted">
                    <span class="badge badge-light-primary py-0.5 px-1.5">{{ $totalSku }} SKU Terdaftar</span>
                    @if($openOpname > 0)
                        <span class="badge badge-light-warning py-0.5 px-1.5">{{ $openOpname }} Opname Berjalan</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Card 2: Unit Fisik & Tersedia --}}
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-tag green">UNIT SIAP JUAL</span>
                <i class="ki-outline ki-check-circle fs-3 text-success"></i>
            </div>
            <div class="metric-val text-success">{{ qty($availQty) }} <span class="fs-7 text-muted fw-normal">unit</span></div>
            <div class="metric-note">
                Dari total fisik: <strong class="text-gray-800">{{ qty($onHandQty) }} unit</strong>
                <div class="d-flex align-items-center gap-1 mt-1 fs-9 text-muted">
                    <span class="badge badge-light-success py-0.5 px-1.5">{{ $availPct }}% Siap Jual</span>
                    @if($reservedQty > 0)
                        <span class="badge badge-light-info py-0.5 px-1.5">{{ qty($reservedQty) }} Direservasi</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Card 3: Stok Kritis / Menipis --}}
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-tag {{ $criticalCnt > 0 ? 'red' : 'green' }}">PERINGATAN RESTOCK</span>
                <i class="ki-outline ki-shield-cross fs-3 {{ $criticalCnt > 0 ? 'text-danger' : 'text-success' }}"></i>
            </div>
            <div class="metric-val {{ $criticalCnt > 0 ? 'text-danger' : 'text-gray-900' }}">{{ $criticalCnt }} SKU</div>
            <div class="metric-note">
                Stok di Bawah Batas Minimum
                <div class="fs-9 text-muted mt-1">
                    @if($emptyCnt > 0)
                        <strong class="text-danger">{{ $emptyCnt }} SKU</strong> kosong total (out of stock).
                    @else
                        Tidak ada SKU yang kosong total.
                    @endif
                </div>
            </div>
        </div>

        {{-- Card 4: Stok Rusak / Karantina --}}
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-tag {{ $damagedQty > 0 ? 'red' : 'green' }}">KUALITAS FISIK</span>
                <i class="ki-outline ki-trash fs-3 {{ $damagedQty > 0 ? 'text-danger' : 'text-success' }}"></i>
            </div>
            <div class="metric-val {{ $damagedQty > 0 ? 'text-danger' : 'text-gray-900' }}">{{ qty($damagedQty) }} <span class="fs-7 text-muted fw-normal">unit</span></div>
            <div class="metric-note">
                Barang Rusak / Tidak Layak Jual
                <div class="fs-9 text-muted mt-1">
                    {{ $damagedPct }}% dari total persediaan fisik.
                </div>
            </div>
        </div>
    </div>

    {{-- SECTION 1: VISUALISASI KOMPOSISI STOK & PER LOKASI --}}
    <div class="row g-6 mb-6">
        {{-- Card Komposisi Fisik --}}
        <div class="col-12 col-xl-4">
            <div class="card card-flush shadow-xs border border-gray-200 h-100">
                <div class="card-header pt-6">
                    <h4 class="fw-bolder text-gray-900 mb-0">Komposisi Fisik Persediaan</h4>
                </div>
                <div class="card-body pt-3 pb-6">
                    <div class="composition-bar mb-4">
                        <div class="composition-segment available" style="width: {{ $availPct }}%;" title="Tersedia: {{ $availPct }}%"></div>
                        <div class="composition-segment reserved" style="width: {{ $reservedPct }}%;" title="Direservasi: {{ $reservedPct }}%"></div>
                        <div class="composition-segment damaged" style="width: {{ $damagedPct }}%;" title="Rusak: {{ $damagedPct }}%"></div>
                    </div>

                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex align-items-center justify-content-between p-2 rounded bg-light border border-gray-200">
                            <div class="d-flex align-items-center gap-2">
                                <span class="w-12px h-12px rounded-circle bg-success"></span>
                                <span class="fs-8 fw-bold text-gray-700">Tersedia Siap Jual</span>
                            </div>
                            <span class="fw-bolder text-gray-900 fs-8">{{ qty($availQty) }} unit ({{ $availPct }}%)</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between p-2 rounded bg-light border border-gray-200">
                            <div class="d-flex align-items-center gap-2">
                                <span class="w-12px h-12px rounded-circle" style="background:#102a4b;"></span>
                                <span class="fs-8 fw-bold text-gray-700">Direservasi Pesanan B2B</span>
                            </div>
                            <span class="fw-bolder text-gray-900 fs-8">{{ qty($reservedQty) }} unit ({{ $reservedPct }}%)</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between p-2 rounded bg-light border border-gray-200">
                            <div class="d-flex align-items-center gap-2">
                                <span class="w-12px h-12px rounded-circle bg-danger"></span>
                                <span class="fs-8 fw-bold text-gray-700">Rusak Fisik (Karantina)</span>
                            </div>
                            <span class="fw-bolder text-danger fs-8">{{ qty($damagedQty) }} unit ({{ $damagedPct }}%)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card Breakdown per Lokasi Cabang / Gudang --}}
        <div class="col-12 col-xl-8">
            <div class="card card-flush shadow-xs border border-gray-200 h-100">
                <div class="card-header pt-6">
                    <div class="card-title d-flex flex-column">
                        <h4 class="fw-bolder text-gray-900 mb-0">Sebaran Nilai Stok per Lokasi Operasional</h4>
                        <span class="text-muted fs-9 mt-1">Perbandingan jumlah unit dan nilai aset HPP antar gudang dan cabang toko.</span>
                    </div>
                </div>
                <div class="card-body pt-2 pb-4">
                    <div class="table-responsive">
                        <table class="ei-table">
                            <thead>
                                <tr>
                                    <th>Lokasi Operasional</th>
                                    <th>Tipe</th>
                                    <th class="text-center">SKU</th>
                                    <th class="text-center">Total Fisik</th>
                                    <th class="text-center">Tersedia</th>
                                    <th class="text-end">Valuasi HPP (Rp)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($locationBreakdown as $loc)
                                    <tr>
                                        <td>
                                            <span class="fw-bolder text-gray-900 fs-8">{{ $loc['location_name'] }}</span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-primary fs-9 py-0.5 px-2">
                                                {{ strtoupper($loc['location_type'] ?? 'CABANG') }}
                                            </span>
                                        </td>
                                        <td class="text-center text-muted fs-8">{{ $loc['sku_count'] }}</td>
                                        <td class="text-center fw-bold text-gray-800 fs-8">{{ qty($loc['total_qty']) }}</td>
                                        <td class="text-center text-success fw-bold fs-8">{{ qty($loc['available_qty']) }}</td>
                                        <td class="text-end fw-bolder text-gray-900 fs-8">{{ $money($loc['stock_valuation']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-6 text-muted fs-8">Belum ada sebaran lokasi persediaan.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- SECTION 2: PERINGATAN STOK KRITIS & TOP VALUABLE ASSETS --}}
    <div class="row g-6 mb-6">
        {{-- Kiri: Stok Kritis / Menipis --}}
        <div class="col-12 col-xl-6">
            <div class="card card-flush shadow-xs border border-gray-200 h-100">
                <div class="card-header pt-6">
                    <div class="card-title d-flex flex-column">
                        <div class="d-flex align-items-center gap-2">
                            <i class="ki-outline ki-shield-cross text-danger fs-2"></i>
                            <h4 class="fw-bolder text-gray-900 mb-0">Peringatan Stok Kritis (Perlu Restock)</h4>
                        </div>
                        <span class="text-muted fs-9 mt-1">Produk dengan stok fisik di bawah batas minimum pemesanan ulang.</span>
                    </div>
                </div>
                <div class="card-body pt-2 pb-4">
                    <div class="table-responsive">
                        <table class="ei-table">
                            <thead>
                                <tr>
                                    <th>Produk &amp; SKU</th>
                                    <th>Lokasi</th>
                                    <th class="text-center">Sisa Stok</th>
                                    <th class="text-center">Batas Min</th>
                                    <th class="text-end">Kekurangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($criticalStockItems as $item)
                                    <tr>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <strong class="text-gray-900 fs-8">{{ $item['product_name'] }}</strong>
                                                <span class="text-muted fs-9 font-monospace">{{ $item['sku'] }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-secondary text-gray-700 fs-9 py-0.5 px-2">
                                                {{ $item['location_name'] }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-light-danger text-danger fw-bolder fs-9 py-1 px-2">
                                                {{ qty($item['quantity_on_hand']) }}
                                            </span>
                                        </td>
                                        <td class="text-center text-muted fs-8">{{ qty($item['minimum_stock']) }}</td>
                                        <td class="text-end text-danger fw-bold fs-8">
                                            -{{ qty($item['shortage_qty']) }} unit
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-6 text-success fs-8">
                                            <i class="ki-outline ki-check-circle fs-2 text-success me-1"></i> Semua stok produk dalam batas aman.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Kanan: Top 10 Nilai Persediaan Terbesar --}}
        <div class="col-12 col-xl-6">
            <div class="card card-flush shadow-xs border border-gray-200 h-100">
                <div class="card-header pt-6">
                    <div class="card-title d-flex flex-column">
                        <div class="d-flex align-items-center gap-2">
                            <i class="ki-outline ki-crown text-warning fs-2"></i>
                            <h4 class="fw-bolder text-gray-900 mb-0">Top 10 Aset Persediaan Terbesar</h4>
                        </div>
                        <span class="text-muted fs-9 mt-1">Produk dengan modal tertanam (HPP × Stok) paling signifikan.</span>
                    </div>
                </div>
                <div class="card-body pt-2 pb-4">
                    <div class="table-responsive">
                        <table class="ei-table">
                            <thead>
                                <tr>
                                    <th>Produk &amp; SKU</th>
                                    <th class="text-center">Stok</th>
                                    <th class="text-end">HPP Satuan</th>
                                    <th class="text-end">Total Nilai Aset</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topValuableItems as $item)
                                    <tr>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <strong class="text-gray-900 fs-8">{{ $item['product_name'] }}</strong>
                                                <span class="text-muted fs-9 font-monospace">{{ $item['sku'] }}</span>
                                            </div>
                                        </td>
                                        <td class="text-center fw-bold text-gray-800 fs-8">{{ qty($item['quantity_on_hand']) }}</td>
                                        <td class="text-end text-muted fs-8">{{ $money($item['cost_price']) }}</td>
                                        <td class="text-end fw-bolder text-gray-900 fs-8">{{ $money($item['total_value']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-6 text-muted fs-8">Belum ada data valuasi aset stok.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- SECTION 3: DAFTAR LENGKAP STOK PERSIS BARANG --}}
    <div class="card card-flush shadow-xs border border-gray-200 mb-6">
        <div class="card-header pt-6">
            <div class="card-title d-flex flex-column">
                <div class="d-flex align-items-center gap-2">
                    <i class="ki-outline ki-table text-primary fs-2"></i>
                    <h3 class="fw-bolder text-gray-900 mb-0">Rincian Stok Persediaan per Produk</h3>
                </div>
                <span class="text-muted fs-8 mt-1">Daftar fisik stok, alokasi tersedia vs rusak, dan nilai aset persediaan.</span>
            </div>
            <div class="card-toolbar">
                <span class="badge badge-light-primary fw-bold px-3 py-1.5">{{ count($allInventoryRows) }} Baris Persediaan</span>
            </div>
        </div>
        <div class="card-body pt-2 pb-4">
            <div class="table-responsive">
                <table class="ei-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;" class="text-center">#</th>
                            <th>Produk &amp; SKU</th>
                            <th>Lokasi</th>
                            <th class="text-center">Fisik (On Hand)</th>
                            <th class="text-center">Tersedia</th>
                            <th class="text-center">Direservasi</th>
                            <th class="text-center">Rusak</th>
                            <th class="text-end">Total Nilai HPP (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($allInventoryRows as $idx => $r)
                            <tr>
                                <td class="text-center text-muted fw-bold fs-9">{{ $idx + 1 }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @if(!empty($r['image_path']))
                                            <img src="{{ asset('storage/'.$r['image_path']) }}" alt="" class="w-32px h-32px rounded object-fit-cover border border-gray-200">
                                        @endif
                                        <div class="d-flex flex-column">
                                            <span class="fw-bolder text-gray-900 fs-8">{{ $r['product'] }}</span>
                                            <span class="text-muted fs-9 font-monospace">{{ $r['sku'] }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-light-secondary text-gray-700 fs-9 py-0.5 px-2">
                                        {{ $r['location'] ?? '-' }}
                                    </span>
                                </td>
                                <td class="text-center fw-bolder text-gray-900 fs-8">{{ qty($r['quantity_on_hand']) }}</td>
                                <td class="text-center text-success fw-bold fs-8">{{ qty($r['available']) }}</td>
                                <td class="text-center text-muted fs-8">{{ qty($r['quantity_reserved'] ?? 0) }}</td>
                                <td class="text-center {{ (float)($r['quantity_damaged'] ?? 0) > 0 ? 'text-danger fw-bold' : 'text-muted' }} fs-8">
                                    {{ qty($r['quantity_damaged'] ?? 0) }}
                                </td>
                                <td class="text-end fw-bolder text-gray-900 fs-8">
                                    {{ $money($r['cost_value']) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-8 text-muted">
                                    <i class="ki-outline ki-cube-2 fs-2x text-gray-400 d-block mb-2"></i>
                                    Tidak ada data persediaan stok pada lokasi/periode ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
